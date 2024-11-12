<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

use LogicException;
use Nette\DI\Container;
use Nette\Utils\Strings;
use ReflectionMethod;
use Tester\AssertException;
use Tester\Dumper;
use Throwable;
use Webnazakazku\MangoTester\Infrastructure\Container\AppContainerFactory;
use Webnazakazku\MangoTester\Infrastructure\Container\AppContainerHookList;
use Webnazakazku\MangoTester\Infrastructure\Container\IAppContainerHook;

class TestCase
{

	private bool $handleErrors = false;

	/** @var callable|FALSE|NULL */
	private $prevErrorHandler = false;

	private Container $testContainer;

	private Container $applicationContainer;

	public static function run(callable $testContainerFactory): void
	{
		$runner = new TestCaseRunner(static::class, $testContainerFactory);
		$runner->run();
	}

	/**
	 * @param mixed[] $args
	 */
	public static function runMethod(callable $testContainerFactory, string $method, array $args): mixed
	{
		$testContainer = $testContainerFactory();
		assert($testContainer instanceof Container);
		$testContainer->addService($testContainer->findByType(TestContext::class)[0], new TestContext(static::class, $method));

		$rm = new ReflectionMethod(static::class, $method);
		$appContainer = static::createApplicationContainer($testContainer, $rm);
		$testContainer->setAppContainer($appContainer);

		$testCase = $testContainer->createInstance(static::class);
		assert($testCase instanceof self);
		$testCase->testContainer = $testContainer;
		$testCase->applicationContainer = $appContainer;
		$result = $testCase->execute($rm, $args);
		unset($testContainer, $appContainer, $testCase);
		gc_collect_cycles();

		return $result;
	}

	protected static function createApplicationContainer(Container $testContainer, ReflectionMethod $rm): Container
	{
		$hooks = [];
		$hooks[] = static::getContainerHook($testContainer);
		$doc = $rm->getDocComment() ?: '';
		$hookNames = Strings::matchAll($doc, '~\*\s+@hook\s+([\w_\\\\]+)(?:\s+.*)?$~m', PREG_PATTERN_ORDER);
		foreach ($hookNames[1] as $hookName) {
			if (class_exists($hookName)) {
				$hooks[] = $testContainer->createInstance($hookName);
			} elseif (method_exists(static::class, $hookName)) {
				$hookRm = new ReflectionMethod(static::class, $hookName);
				assert($hookRm->isStatic());
				$methodCallback = [static::class, $hookName];
				assert(is_callable($methodCallback));
				$hooks[] = $testContainer->callMethod($methodCallback);
			} else {
				throw new LogicException(sprintf('Hook %s not found', $hookName));
			}
		}

		$factory = $testContainer->getByType(AppContainerFactory::class);
		assert($factory instanceof AppContainerFactory);

		return $factory->create($testContainer, new AppContainerHookList(array_filter($hooks)));
	}

	/**
	 * Override to add test case specific app hook
	 */
	protected static function getContainerHook(Container $testContainer): ?IAppContainerHook
	{
		return null;
	}

	/**
	 * This method is called before a test is executed.
	 */
	protected function setUp(): void
	{
	}

	protected function executeSetupListeners(): void
	{
		foreach ($this->testContainer->findByType(ITestCaseListener::class) as $serviceName) {
			$service = $this->testContainer->getService($serviceName);
			assert($service instanceof ITestCaseListener);
			$service->setUp($this);
		}
	}

	/**
	 * This method is called after a test is executed.
	 */
	protected function tearDown(): void
	{
	}

	protected function executeTearDownListeners(): void
	{
		foreach ($this->testContainer->findByType(ITestCaseListener::class) as $serviceName) {
			$service = $this->testContainer->getService($serviceName);
			assert($service instanceof ITestCaseListener);
			$service->tearDown($this);
		}
	}

	/**
	 * @param mixed[] $args
	 */
	protected function execute(ReflectionMethod $method, array $args): mixed
	{
		if ($this->prevErrorHandler === false) {
			$this->prevErrorHandler = set_error_handler(function ($severity) {
				if ($this->handleErrors && ($severity & error_reporting()) === $severity) {
					$this->handleErrors = false;
					$this->silentTearDown();
				}

				return $this->prevErrorHandler ? call_user_func_array($this->prevErrorHandler, func_get_args()) : false;
			});
		}

		try {
			$this->applicationContainer->callInjects($this);

			$this->executeSetupListeners();
			$this->setUp();

			$this->handleErrors = true;
			try {
				$result = $this->invoke($method, $args);
			} catch (Throwable $e) {
				$this->handleErrors = false;
				$this->silentTearDown();

				throw $e;
			}

			$this->handleErrors = false;

			$this->tearDown();
			$this->executeTearDownListeners();

			return $result;
		} catch (AssertException $e) {
			//throw $e->setMessage("$e->origMessage in {$method->getName()}(" . (substr(Dumper::toLine($args), 1, -1)) . ')');
			throw $e->setMessage($e->origMessage . ' in ' . $method->getName() . '(' . (substr(Dumper::toLine($args), 1, -1)) . ')');
		} finally {
			restore_error_handler();
			$this->prevErrorHandler = false;
		}
	}

	private function silentTearDown(): void
	{
		set_error_handler(fn (): bool => true);
		try {
			$this->tearDown();
		} catch (Throwable $e) { // phpcs:ignore
		}

		restore_error_handler();
	}

	/**
	 * @param mixed[] $args
	 */
	protected function invoke(ReflectionMethod $method, array $args): mixed
	{
		if (count($method->getParameters()) > 0) {
			$resolver = $this->testContainer->getByType(MethodArgumentsResolver::class);
			assert($resolver instanceof MethodArgumentsResolver);
			$args = $resolver->resolve($method, $this->applicationContainer, $args);
		}

		$callback = [$this, $method->getName()];
		assert(is_callable($callback));

		return call_user_func_array($callback, $args);
	}

}
