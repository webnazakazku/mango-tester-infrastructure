Mango Tester Infrastructure
======
[![Build Status](https://github.com/webnazakazku/mango-tester-infrastructure/actions/workflows/main.yaml/badge.svg)](https://github.com/webnazakazku/mango-presenter-tester/actions/workflows/main.yaml)

Testing hepler for testing Nette application with easy to use API.

Installation
----

The recommended way to install is via Composer:

```
composer require webnazakazku/mango-tester-infrastructure
```

It requires PHP version 7.1.

Integration & configuration
-----

Example of using:

`tests/bootstra.php`

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator();

// we need to override defaultExtensions because Nette\Configurator registers
// butch of extensions we don't need and that clash with the Mango Tester
$configurator->defaultExtensions = [
	'php' => Nette\DI\Extensions\PhpExtension::class,
	'constants' => Nette\DI\Extensions\ConstantsExtension::class,
	'extensions' => Nette\DI\Extensions\ExtensionsExtension::class,
	'decorator' => Nette\DI\Extensions\DecoratorExtension::class,
	'cache' => [Nette\Bridges\CacheDI\CacheExtension::class, ['%tempDir%']],
	'di' => [Nette\DI\Extensions\DIExtension::class, ['%debugMode%']],
	'database' => [Nette\Bridges\DatabaseDI\DatabaseExtension::class, ['%debugMode%']],
	'tracy' => [Tracy\Bridges\Nette\TracyExtension::class, ['%debugMode%', '%consoleMode%']],
	'inject' => Nette\DI\Extensions\InjectExtension::class,
];

$configurator->setDebugMode(true);
$logDir = __DIR__ . '/../temp/tests/log';
@mkdir($logDir, 0777, true);
$configurator->enableTracy($logDir);
$configurator->setTempDirectory(__DIR__ . '/../temp/tests');

$appDir = __DIR__ . '/../app';

$rb = $configurator->createRobotLoader()
	->addDirectory($appDir)
	->addDirectory(__DIR__)
	->register();

$configurator->addParameters(
	[
		'appDir' => $appDir,
		'wwwDir' => __DIR__ . '/../www',
	]
);

$configurator->addConfig(__DIR__ . '/config/tests.neon');
$configurator->addConfig(__DIR__ . '/../app/config/tests.local.neon');

Tester\Environment::setup();
Tester\Dumper::$maxPathSegments = 32;

return [$configurator, 'createContainer'];
```

`tests/config/app.neon`

```neon
application:
	errorPresenter: System:Error
	scanDirs: []
	mapping:
		*: App\*Module\Presenters\*Presenter

session:
	expiration: 14 days
	save_path: "%tempDir%/sessions"

services:
	nette.mailer:
		class: Nette\Mail\IMailer
		factory: Nextras\MailPanel\FileMailer(%tempDir%/mail-panel-mails)
```

`tests/config/tests.neon`

```neon
extensions:
	mango.tester: Webnazakazku\MangoTester\Infrastructure\MangoTesterExtension
	mango.tester.presenterTester: Webnazakazku\MangoTester\PresenterTester\Bridges\Infrastructure\PresenterTesterExtension
	- Webnazakazku\MangoTester\HttpMocks\Bridges\Infrastructure\HttpExtension

parameters:
	appContainer:
		parameters:
			appDir: %appDir%
			wwwDir: %wwwDir%
			tempDir: %tempDir%
		configFiles:
			- %appDir%/config/config.neon
			- %appDir%/config/local.neon

services:
	- AppTests\AppConfiguratorFactory
```

`src/AppConfiguratorFactory`

```php
<?php declare(strict_types = 1);

namespace AppTests;

use Nette\Configurator;
use Nette\DI\Container as DIContainer;
use Nette\DI\Definitions\Statement as DIStatement;
use Nette\Neon\Neon;
use Nette\Utils\Finder;
use Throwable;
use Webnazakazku\MangoTester\Infrastructure\Container\IAppConfiguratorFactory;

class AppConfiguratorFactory implements IAppConfiguratorFactory
{

	public function create(DIContainer $testContainer): Configurator
	{
		$testContainerParameters = $testContainer->getParameters();

		$configurator = new Configurator();
		$configurator->setDebugMode(true);
		$configurator->setTempDirectory($testContainerParameters['tempDir']);

		$appDir = __DIR__ . '/../../app';
		$wwwDir = __DIR__ . '/../../temp/tests/www';

		$configurator->addParameters(
			[
				'appDir' => $appDir,
				'wwwDir' => $wwwDir,
			]
		);

		$configurator->addConfig(__DIR__ . '/../config/app.neon');

		$configurator->createRobotLoader()
			->addDirectory($appDir)
			->register();

		$configurator->addConfig($appDir . '/config/config.neon');
		$configurator->addConfig($appDir . '/config/tests.local.neon');

		$configurator->addConfig(
			[
				'console' => [
					'url' => null,
				],
			]
		);

		return $configurator;
	}

}
```