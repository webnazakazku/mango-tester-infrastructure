<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure\Container;

use Nette\Configurator;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;

class ConfigAppContainerHook implements IAppContainerHook
{

	/** @var array<mixed>|string */
	private $config;

	/**
	 * @param array<mixed>|string $config
	 */
	public function __construct($config)
	{
		$this->config = $config;
	}

	public function getHash(): string
	{
		return self::class;
	}

	public function onConfigure(Configurator $appConfigurator): void
	{
		$appConfigurator->addConfig($this->config);
	}

	public function onCompile(ContainerBuilder $appContainerBuilder): void
	{
	}

	public function onCreate(Container $appContainer): void
	{
	}

}
