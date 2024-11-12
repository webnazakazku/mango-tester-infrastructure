<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure\Container;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;

interface IAppContainerHook
{

	public function getHash(): string;

	public function onConfigure(Configurator $appConfigurator): void;

	public function onCompile(ContainerBuilder $appContainerBuilder): void;

	public function onCreate(Container $appContainer): void;

}
