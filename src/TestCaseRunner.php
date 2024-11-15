<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

use ReflectionClass;
use ReflectionMethod;
use Tester\DataProvider;
use Tester\Environment;
use Tester\Helpers;
use Tester\TestCase;
use Tester\TestCaseException;

class TestCaseRunner
{

	private const LIST_METHODS = 'nette-tester-list-methods';
	private const METHOD_PATTERN = '#^test[A-Z0-9_]#';

	/** @var callable */
	private $testContainerFactory;

	/** @var class-string */
	private string $testCaseClass;

	/**
	 * @param class-string $testCaseClass
	 */
	public function __construct(string $testCaseClass, callable $testContainerFactory)
	{
		$this->testContainerFactory = $testContainerFactory;
		$this->testCaseClass = $testCaseClass;
	}

	public function run(): void
	{
		$methods = preg_grep(self::METHOD_PATTERN, array_map(fn (ReflectionMethod $rm) => $rm->getName(), (new ReflectionClass($this->testCaseClass))->getMethods()));
		assert($methods !== false);
		$methods = array_values($methods);

		if (isset($_SERVER['argv']) && ($tmp = preg_filter('#--method=([\w-]+)$#Ai', '$1', $_SERVER['argv']))) {
			assert(is_array($tmp));
			$method = reset($tmp);
			if ($method === self::LIST_METHODS) {
				Environment::$checkAssertions = false;
				header('Content-Type: text/plain');
				if (method_exists(TestCase::class, 'sendMethodList')) {
					echo "\n";
					echo 'TestCase:' . static::class . "\n";
					echo 'Method:' . implode("\nMethod:", $methods) . "\n";
				} else {
					// legacy format
					echo '[' . implode(',', $methods) . ']';
				}

				return;
			}

			$this->runMethod($method);

		} else {
			foreach ($methods as $method) {
				$this->runMethod($method);
			}
		}
	}

	public function runMethod(string $method): void
	{
		if (!method_exists($this->testCaseClass, $method)) {
			throw new TestCaseException(sprintf("Method '%s' does not exist.", $method));
		} elseif (!preg_match(self::METHOD_PATTERN, $method)) {
			throw new TestCaseException(sprintf("Method '%s' is not a testing method.", $method));
		}

		$method = new ReflectionMethod($this->testCaseClass, $method);
		if (!$method->isPublic()) {
			throw new TestCaseException(sprintf('Method %s is not public. Make it public or rename it.', $method->getName()));
		}

		$info = Helpers::parseDocComment($method->getDocComment() ?: '') + ['dataprovider' => null];

		$data = [];
		$defaultParams = [];
		foreach ($method->getParameters() as $param) {
			$defaultParams[$param->getName()] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
		}

		foreach ((array) $info['dataprovider'] as $provider) {
			$res = self::getData($provider);
			foreach ($res as $set) {
				$data[] = is_string(key($set)) ? array_merge($defaultParams, $set) : $set;
			}
		}

		if (!$info['dataprovider']) {
			$data[] = [];
		}

		foreach ($data as $args) {
			$this->callTestMethod($method->getName(), $args);
		}
	}

	/**
	 * @param mixed[] $args
	 */
	private function callTestMethod(string $method, array $args): mixed
	{
		return ($this->testCaseClass)::runMethod($this->testContainerFactory, $method, $args);
	}

	/**
	 * @return iterable<mixed>
	 */
	protected function getData(string $provider): iterable
	{
		if (strpos($provider, '.') === false) {
			return $this->callTestMethod($provider, []);
		}

		$rc = new ReflectionClass($this->testCaseClass);
		$fileName = $rc->getFileName();
		assert($fileName !== false);
		[$file, $query] = DataProvider::parseAnnotation($provider, $fileName);

		return DataProvider::load($file, $query);
	}

}
