<?php declare(strict_types = 1);

namespace Tests\Tester\Infrastructure;

use Webnazakazku\MangoTester\Infrastructure\TestCase;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';


/**
 * @testCase
 */
class TestRunTest extends TestCase
{
	public function testEcho(\DateTimeImmutable $containerDependency)
	{
		Assert::true(true);
	}
}

TestRunTest::run(Bootstrap::FACTORY);
