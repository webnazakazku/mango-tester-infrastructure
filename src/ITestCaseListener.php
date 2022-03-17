<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

interface ITestCaseListener
{

	public function setUp(TestCase $testCase): void;

	public function tearDown(TestCase $testCase): void;

}
