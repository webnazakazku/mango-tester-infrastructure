<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\DI\Extensions\ExtensionsExtension;
use Webnazakazku\MangoTester\Infrastructure\Container\IAppConfiguratorFactory;

class DefaultAppConfiguratorFactory implements IAppConfiguratorFactory
{

	private const COPIED_PARAMETERS = [
		'logDir',
		'tempDir',
		'appDir',
		'wwwDir',
		'consoleMode',
	];

	/** @var string[] */
	private array $configFiles;

	/** @var string[] */
	private array $copiedParameters;

	private bool $defaultExtensionsOverride = true;

	/**
	 * @param string[]          $configFiles
	 * @param string[] $copiedParameters
	 */
	public function __construct(array $configFiles, array $copiedParameters = self::COPIED_PARAMETERS)
	{
		$this->configFiles = $configFiles;
		$this->copiedParameters = $copiedParameters;
	}

	public function disableDefaultExtensionsOverride(bool $disable = true): void
	{
		$this->defaultExtensionsOverride = !$disable;
	}

	public function create(Container $testContainer): Configurator
	{
		$params = $testContainer->getParameters();

		$configurator = new Configurator();
		if ($this->defaultExtensionsOverride) {
			$configurator->defaultExtensions = [
				'extensions' => ExtensionsExtension::class,
			];
		}

		$configurator->setDebugMode(true);
		$configurator->setTempDirectory($params['tempDir']);

		$parameters = array_intersect_key($params, array_fill_keys($this->copiedParameters, true));

		$configurator->addStaticParameters($parameters);
		foreach ($this->configFiles as $file) {
			$configurator->addConfig($file);
		}

		return $configurator;
	}

}
