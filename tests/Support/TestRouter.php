<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

namespace PureFramework\Tests\Support;

use PureFramework\Request;
use PureFramework\Router;

final class TestRouter extends Router
{
	public static bool $defaultNotFoundInvoked = false;

	protected function executeDefaultNotFoundHandler(Request $request): never
	{
		self::$defaultNotFoundInvoked = true;
		throw new \RuntimeException('test-default-not-found');
	}
}
