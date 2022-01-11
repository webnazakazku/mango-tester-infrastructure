<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure\Container;

use Nette\DI\CompilerExtension;


class CompilerHookExtension extends CompilerExtension
{
	/** @var callable[] */
	public $onBeforeCompile = [];


	public function beforeCompile()
	{
		foreach ($this->onBeforeCompile as $fn) {
			call_user_func($fn, $this->getContainerBuilder());
		}
	}
}
