<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure\Container;

use Nette\Configurator;
use Nette\DI\Container;


interface IAppConfiguratorFactory
{
	public function create(Container $testContainer): Configurator;
}
