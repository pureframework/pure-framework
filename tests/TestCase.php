<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

namespace PureFramework\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PureFramework\Util;

abstract class TestCase extends PHPUnitTestCase
{
	protected static function projectRoot(): string
	{
		return dirname(__DIR__);
	}

	protected function fixturePath(string ...$parts): string
	{
		return Util::path(self::projectRoot(), 'tests', 'fixtures', ...$parts);
	}

	protected function makeRequest(): \PureFramework\Request
	{
		$request = new \PureFramework\Request(false);
		$request->setQuery([]);
		$request->setParams(null);
		$request->setPost([]);
		$request->routingData = [];

		return $request;
	}
}
