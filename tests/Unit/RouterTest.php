<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

namespace PureFramework\Tests\Unit;

use PureFramework\Request;
use PureFramework\Router;
use PureFramework\Tests\Support\TestRouter;
use PureFramework\Tests\TestCase;

final class RouterTest extends TestCase
{
	public function testRejectsAssociativeRouteDefinitions(): void
	{
		$router = new Router();
		$this->expectException(\InvalidArgumentException::class);
		@$router->route(['url' => '^/legacy$'], 'tests/smoke-handler.php');
	}

	public function testMatchesRouteAndExecutesHandler(): void
	{
		$router = new Router();
		$router->route('^/smoke-test$', 'tests/smoke-handler.php');

		$request = $this->makeRequest();
		$request->setMethod('GET');
		$request->setUrl('/smoke-test');

		$this->assertSame('smoke-ok', $router->run($request));
	}

	public function testQueryMethodRoute(): void
	{
		$router = new Router();
		$router->route('QUERY', '^/query-verb$', 'tests/smoke-handler.php');

		$request = $this->makeRequest();
		$request->setMethod('QUERY');
		$request->setUrl('/query-verb');
		$this->assertSame('smoke-ok', $router->run($request));

		$getRequest = $this->makeRequest();
		$getRequest->setMethod('GET');
		$getRequest->setUrl('/query-verb');
		$router->setNotFoundHandler(false);
		$this->assertFalse($router->run($getRequest));
	}

	public function testNotFoundHandling(): void
	{
		$request = $this->makeRequest();
		$request->setMethod('GET');
		$request->setUrl('/no-such-path');

		$disabled = new Router();
		$disabled->setNotFoundHandler(false);
		$this->assertFalse($disabled->run($request));

		TestRouter::$defaultNotFoundInvoked = false;
		$default = new TestRouter();
		try {
			$default->run($request);
			$this->fail('Expected RuntimeException from test default not-found');
		} catch (\RuntimeException $e) {
			$this->assertSame('test-default-not-found', $e->getMessage());
		}
		$this->assertTrue(TestRouter::$defaultNotFoundInvoked);

		$callable = new Router();
		$routeWasNull = false;
		$callable->setNotFoundHandler(function (Request $req) use (&$routeWasNull) {
			global $ROUTE;
			$routeWasNull = $ROUTE === null;

			return $req->url();
		});
		$this->assertSame('/no-such-path', $callable->run($request));
		$this->assertTrue($routeWasNull);

		$file = new Router();
		$file->setNotFoundHandler('tests/fixtures/not-found/handler.php');
		$this->assertSame('not-found-file', $file->run($request));

		$precedence = new Router();
		$precedence->route('^/takes-precedence$', 'tests/smoke-handler.php');
		$precedence->setNotFoundHandler(function () {
			return 'should-not-run';
		});
		$matched = $this->makeRequest();
		$matched->setMethod('GET');
		$matched->setUrl('/takes-precedence');
		$this->assertSame('smoke-ok', $precedence->run($matched));

		$clear = new Router();
		$clear->setNotFoundHandler(function () {
			return 'cleared-should-not-run';
		});
		$clear->setNotFoundHandler(null);
		$clear->setNotFoundHandler(false);
		$this->assertFalse($clear->run($request));
	}
}
