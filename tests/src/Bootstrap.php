<?php declare(strict_types = 1);

namespace Tests\Tester\Infrastructure;

use Nette\Configurator;
use Webnazakazku\MangoTester\Infrastructure\MangoTesterExtension;

class Bootstrap
{

	public const FACTORY = [self::class, 'createContainer'];

	public static function createContainer()
	{
		$configurator = new Configurator();
		$configurator->setTempDirectory(__DIR__ . '/../temp');
		$configurator->setDebugMode(true);
		$configurator->enableDebugger(__DIR__ . '/../temp');
		$configurator->addConfig([
			'extensions' => [
				MangoTesterExtension::class,
			],
			'services' => [
				AppConfiguratorFactory::class,
			],
		]);

		return $configurator->createContainer();
	}

}
