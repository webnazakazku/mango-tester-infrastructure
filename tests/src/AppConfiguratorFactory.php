<?php declare(strict_types = 1);

namespace Tests\Tester\Infrastructure;

use DateTimeImmutable;
use Nette\Configurator;
use Nette\DI\Container;
use Webnazakazku\MangoTester\Infrastructure\Container\IAppConfiguratorFactory;

class AppConfiguratorFactory implements IAppConfiguratorFactory
{

	public function create(Container $testContainer): Configurator
	{
		$configurator = new Configurator();
		$configurator->setTempDirectory($testContainer->getParameters()['tempDir']);
		$configurator->addConfig([
			'services' => [
				DateTimeImmutable::class,
			],
		]);

		return $configurator;
	}

}
