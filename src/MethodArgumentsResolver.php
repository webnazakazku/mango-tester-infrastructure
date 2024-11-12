<?php declare(strict_types = 1);

namespace Webnazakazku\MangoTester\Infrastructure;

use LogicException;
use Nette\DI\Container;
use Nette\DI\Helpers;
use Nette\DI\Resolver;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionMethod;

class MethodArgumentsResolver
{

	/**
	 * @param array<mixed> $args
	 * @return array<mixed>
	 */
	public function resolve(ReflectionMethod $method, Container $appContainer, array $args): array
	{
		$fixedArgs = $this->prepareArguments($method, $appContainer);

		if (method_exists(Helpers::class, 'autowireArguments')) {
			return Helpers::autowireArguments($method, $args + $fixedArgs, $appContainer);
		}

		$ref = new ReflectionClass(Resolver::class);
		$params = $ref->getMethod('autowireArguments')->getParameters();

		if ($params[2]->name === 'resolver') {
			/** @phpstan-var mixed $appContainer */
			return Resolver::autowireArguments($method, $args + $fixedArgs, $appContainer);
		} elseif ($params[2]->name === 'getter') {
			$getter = function (string $type, bool $single) use ($appContainer) {
				/** @var class-string $type */
				return $single
					? $appContainer->getByType($type)
					: array_map([$appContainer, 'getService'], $appContainer->findAutowired($type));
			};

			return Resolver::autowireArguments($method, $args + $fixedArgs, $getter);
		} else {
			throw new LogicException();
		}
	}

	/**
	 * Autowires parametrics arguments by annotation with the following syntax:
	 *   (@)param string %any.param.name%
	 *              The string keyword is required mostly for PhpStorm compatibility.
	 *              Note that only positional arguments are not supported.
	 *
	 * Even though variable name is also allowed in the annotation, such as
	 *   (@)param $name %any.param.name%
	 *              the $name is not used.
	 *
	 * @return mixed[]
	 */
	protected function prepareArguments(ReflectionMethod $method, Container $appContainer): array
	{
		$doc = $method->getDocComment() ?: '';

		$parameters = $appContainer->getParameters();
		$paramAnnotations = Strings::matchAll($doc, '~@param\s+(?P<name>\$\S+)\s+(?P<value>.*?)\s*$~m');

		$args = [];
		foreach ($paramAnnotations as $annotation) {
			$args[ltrim($annotation['name'], '$')] = Helpers::expand($annotation['value'], $parameters);
		}

		return $args;
	}

}
