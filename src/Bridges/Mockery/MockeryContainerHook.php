<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure\Bridges\Mockery;

use Mockery;
use Mockery\MockInterface;
use Nette\DI\Container;
use Nette\Utils\Reflection;
use Nette\Utils\Strings;
use ReflectionClass;
use Webnazakazku\MangoTester\Infrastructure\Container\AppContainerHook;
use Webnazakazku\MangoTester\Infrastructure\TestContext;

class MockeryContainerHook extends AppContainerHook
{

	private TestContext $testContext;

	public function __construct(TestContext $testContext)
	{
		$this->testContext = $testContext;
	}

	public function onCreate(Container $applicationContainer): void
	{
		$rc = new ReflectionClass($this->testContext->getTestCaseClass());
		$rm = $rc->getMethod($this->testContext->getTestMethod());
		$doc = $rm->getDocComment() ?: '';
		$params = Strings::matchAll($doc, '~\*\s+@param\s+([\w_\\\\|]+)\s+(\$[\w_]+)(?:\s+.*)?$~Um');

		foreach ($params as [, $types, $paramName]) {
			$types = explode('|', $types);

			if (count($types) !== 2) {
				continue;
			}

			[$requiredType, $mockeryType] = $types;
			$requiredType = Reflection::expandClassName($requiredType, $rc);
			$mockeryType = Reflection::expandClassName($mockeryType, $rc);

			if ($mockeryType !== MockInterface::class) {
				continue;
			}

			$applicationContainer->addService($applicationContainer->findByType($requiredType)[0], Mockery::mock($requiredType));
		}
	}

}
