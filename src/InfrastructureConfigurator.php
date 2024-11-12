<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

use Closure;
use Nette\DI\Compiler;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\Schema\Helpers;
use Nette\Utils\FileSystem;
use Tester\Dumper;
use Tester\Environment;

class InfrastructureConfigurator
{

	/** @var mixed[] */
	protected array $parameters;

	/** @var array<string|mixed[]> */
	protected array $configs = [];

	public function __construct(string $path)
	{
		$this->parameters = [
			'debugMode' => true,
			'productionMode' => false,
			'consoleMode' => false,
			'tempDir' => $path,
			'appDir' => $path . '/dummyAppDir',
			'wwwDir' => $path . '/dummyWwwDir',
		];
	}

	public function setupTester(): void
	{
		Environment::setup();
		Dumper::$maxPathSegments = 0;
	}

	public function setTimeZone(string $timezone): void
	{
		date_default_timezone_set($timezone);
		@ini_set('date.timezone', $timezone); // @ - function may be disabled
	}

	/**
	 * @param mixed[] $params
	 */
	public function addParameters(array $params): void
	{
		$parameters = Helpers::merge($params, $this->parameters);
		assert(is_array($parameters));

		$this->parameters = $parameters;
	}

	/**
	 * @param mixed[]|string $config file or configuration itself
	 */
	public function addConfig(array|string $config): void
	{
		assert(is_string($config) || is_array($config));
		$this->configs[] = $config;
	}

	public function getContainerFactory(): Closure
	{
		return function (): Container {
			$class = $this->loadContainer();
			/** @var Container $container */
			$container = new $class([]);
			$container->initialize();

			return $container;
		};
	}

	/**
	 * Loads system DI container class and returns its name.
	 */
	protected function loadContainer(): string
	{
		$loader = new ContainerLoader(
			$this->getCacheDirectory() . '/Mango.Tester.Infrastructure',
			$this->parameters['debugMode']
		);

		return $loader->load(
			fn (Compiler $compiler) => $this->generateContainer($compiler),
			[$this->parameters, $this->configs, PHP_VERSION_ID - PHP_RELEASE_VERSION]
		);
	}

	protected function generateContainer(Compiler $compiler): string
	{
		$compiler->addConfig(['parameters' => $this->parameters]);

		$loader = new Loader();
		$fileInfo = [];
		foreach ($this->configs as $config) {
			if (is_string($config)) {
				$fileInfo[] = sprintf('// source: %s', $config);
				$config = $loader->load($config);
			}

			$compiler->addConfig($config);
		}

		$compiler->addDependencies($loader->getDependencies());

		$compiler->addExtension('extensions', new ExtensionsExtension());
		$compiler->addExtension('mango.tester', new MangoTesterExtension());

		$classes = $compiler->compile();

		return implode("\n", $fileInfo) . "\n\n" . $classes;
	}

	protected function getCacheDirectory(): string
	{
		$dir = $this->parameters['tempDir'] . '/cache';
		FileSystem::createDir($dir);

		return $dir;
	}

}
