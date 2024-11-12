<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

use Mockery;
use Nette\DI\CompilerExtension;
use Nette\DI\Container;
use Nette\DI\Definitions\ImportedDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Webnazakazku\MangoTester\Infrastructure\Bridges\Mockery\MockeryContainerHook;
use Webnazakazku\MangoTester\Infrastructure\Container\AppContainerFactory;

/**
 * @property mixed $config
 */
class MangoTesterExtension extends CompilerExtension
{

	public const TAG_REQUIRE = 'mango.tester.require';
	public const TAG_HOOK = 'mango.tester.hook';

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'hooks' => Expect::array(),
			'require' => Expect::array(),
			'appContainer' => Expect::array(),
			'mockery' => Expect::bool()->castTo('bool'),
		]);
	}

	public function loadConfiguration(): void
	{
		/** @var mixed $config */
		$config = $this->config;

		$config->mockery = class_exists(Mockery::class);

		$this->registerRequiredServices($config->require);
		$this->registerHooks($config->hooks);
		$this->registerAppConfiguratorFactory($config->appContainer);

		$builder = $this->getContainerBuilder();

		$this->addDynamic($this->prefix('appContainer'), Container::class)
			->setAutowired(false);

		$builder->addDefinition($this->prefix('containerFactory'))
			->setType(AppContainerFactory::class);

		$builder->addDefinition($this->prefix('methodArgumentResolver'))
			->setType(MethodArgumentsResolver::class);

		$this->addDynamic($this->prefix('testContext'), TestContext::class);

		if ($config->mockery !== false) {
			$builder->addDefinition($this->prefix('mockeryContainerHook'))
				->setType(MockeryContainerHook::class)
				->addTag(self::TAG_HOOK);
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($builder->findByTag(self::TAG_REQUIRE) as $service => $attrs) {
			/** @var ServiceDefinition $def */
			$def = $builder->getDefinition($service);
			if (is_string($attrs) && strpos($attrs, '\\') === false) {
				$def->setFactory(new Statement([$this->prefix('@appContainer'), 'getService'], [$attrs]));
			} elseif (is_string($attrs)) {
				$def->setFactory(new Statement([$this->prefix('@appContainer'), 'getByType'], [$attrs]));
			} else {
				$type = $def->getType();
				$def->setFactory(new Statement([$this->prefix('@appContainer'), 'getByType'], [$type]));
			}
		}
	}

	/**
	 * @param class-string[] $hooks
	 */
	protected function registerHooks(array $hooks): void
	{
		$builder = $this->getContainerBuilder();
		$i = 0;
		foreach ($hooks as $hookClass) {
			$name = $i++ . preg_replace('#\W+#', '_', $hookClass);

			$builder->addDefinition($this->prefix($name))
				->setType($hookClass)
				->addTag(self::TAG_HOOK);
		}
	}

	/**
	 * @param string[] $requiredServices
	 */
	protected function registerRequiredServices(array $requiredServices): void
	{
		foreach ($requiredServices as $class) {
			$this->requireService($class);
		}
	}

	private function requireService(string $class): void
	{
		$builder = $this->getContainerBuilder();
		/** @var string $name */
		$name = preg_replace('#\W+#', '_', $class);
		$builder->addDefinition($this->prefix($name))
			->setType($class)
			->addTag(self::TAG_REQUIRE);
	}

	/**
	 * @param mixed[] $config
	 */
	private function registerAppConfiguratorFactory(array $config): void
	{
		if ($config === []) {
			return;
		}

		$builder = $this->getContainerBuilder();
		$def = $builder->addDefinition($this->prefix('appConfiguratorFactory'))
			->setFactory(DefaultAppConfiguratorFactory::class, [
				'configFiles' => $config['configs'] ?? [],
			]);

		if (($config['overrideDefaultExtensions'] ?? false) !== true) {
			$def->addSetup('disableDefaultExtensionsOverride');
		}
	}

	public function afterCompile(ClassType $class): void
	{
		parent::afterCompile($class);

		$class->addMethod('setAppContainer')
			->setBody('$this->addService(?, $container);', [$this->prefix('appContainer')])
			->addParameter('container');
	}

	private function addDynamic(string $name, string $className): ImportedDefinition
	{
		$builder = $this->getContainerBuilder();

		$def = $builder->addImportedDefinition($name);
		$def->setType($className);

		return $def;
	}

}
