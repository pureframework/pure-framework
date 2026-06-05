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
use PureFramework\Tests\TestCase;
use PureFramework\Util;

final class RequestTest extends TestCase
{
	/** @var array<string, mixed> */
	private array $serverBackup = [];

	protected function setUp(): void
	{
		parent::setUp();
		foreach (['REQUEST_URI', 'QUERY_STRING', 'REQUEST_METHOD'] as $key) {
			$this->serverBackup[$key] = $_SERVER[$key] ?? null;
		}
	}

	protected function tearDown(): void
	{
		foreach ($this->serverBackup as $key => $value) {
			if ($value === null) {
				unset($_SERVER[$key]);
			} else {
				$_SERVER[$key] = $value;
			}
		}
		parent::tearDown();
	}

	public function testUrlStripsQueryWhenQueryStringEmpty(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/foo?bar=1';
		$_SERVER['QUERY_STRING'] = '';

		$this->assertSame('/foo', (new Request())->url());
	}

	public function testUrlStripsQueryWhenQueryStringSet(): void
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/baz?x=1';
		$_SERVER['QUERY_STRING'] = 'x=1';

		$this->assertSame('/baz', (new Request())->url());
	}

	public function testHttpMethodHelpers(): void
	{
		$request = new Request(false);
		$request->setMethod('POST');
		$this->assertTrue($request->isPost());
		$this->assertFalse($request->isGet());

		$request->setMethod('OPTIONS');
		$this->assertTrue($request->isOptions());

		$request->setMethod('QUERY');
		$this->assertTrue($request->isQueryMethod());
		$this->assertTrue($request->isMethod('query'));
	}

	public function testJsonBodyEmptyAndCached(): void
	{
		$request = new Request(false);
		$empty = $request->jsonBody();
		$this->assertSame([], $empty);
		$this->assertSame($empty, $request->jsonBody());
	}

	public function testParamQueryPostCookieAccessors(): void
	{
		$uuid = Util::uuid();
		$request = new Request(false);
		$request->setParams(['client_uuid' => 'abc', 'empty' => null]);
		$request->setQuery(['page' => '2']);

		$this->assertSame('abc', $request->param('client_uuid'));
		$this->assertTrue($request->hasParam('client_uuid'));
		$this->assertFalse($request->hasParam('missing'));
		$this->assertTrue($request->hasParam('empty'));
		$this->assertSame('fallback', $request->param('missing', 'fallback'));
		$this->assertNull($request->param('empty'));

		$uuidRequest = new Request(false);
		$uuidRequest->setParams(['client_uuid' => strtoupper(str_replace('-', '', $uuid))]);
		$this->assertSame($uuid, $uuidRequest->uuidParam('client_uuid'));
		$this->assertFalse($uuidRequest->uuidParam('missing'));

		$invalid = new Request(false);
		$invalid->setParams(['client_uuid' => 'not-a-valid-uuid']);
		$this->assertFalse($invalid->uuidParam('client_uuid'));

		$emptyUuid = new Request(false);
		$emptyUuid->setParams(['client_uuid' => null]);
		$this->assertFalse($emptyUuid->uuidParam('client_uuid'));

		$this->assertSame('2', $request->query('page'));
		$this->assertSame(10, $request->query('missing', 10));
		$this->assertSame(
			['view' => 'list', 'client_uuid' => 'abc', 'empty' => null],
			$request->params(['view' => 'list'])
		);
		$this->assertSame(
			['page' => '2', 'sort' => 'name'],
			$request->queryAll(['page' => 1, 'sort' => 'name'])
		);

		$request->setCookie(['session' => 'abc123']);
		$this->assertSame('abc123', $request->cookie('session'));
		$this->assertSame('none', $request->cookie('missing', 'none'));
		$this->assertSame(
			['theme' => 'light', 'session' => 'abc123'],
			$request->cookies(['theme' => 'light'])
		);

		$request->setPost(['subtotal' => '10', 'flag' => null]);
		$request->setQuery(['subtotal' => 'from-query']);
		$this->assertSame('10', $request->post('subtotal'));
		$this->assertSame(0, $request->post('missing', 0));
		$this->assertSame(
			['tax' => '0', 'subtotal' => '10', 'flag' => null],
			$request->postAll(['tax' => '0'])
		);
	}

	public function testScalarAccessors(): void
	{
		$request = new Request(false);
		$request->setMethod('GET');
		$request->setUrl('/path');
		$request->setHttps(true);
		$request->setDomain('example.test');
		$request->setPort(443);
		$request->setReferer('https://example.test/back');

		$this->assertSame('GET', $request->method());
		$this->assertSame('/path', $request->url());
		$this->assertTrue($request->isHttps());
		$this->assertSame('example.test', $request->domain());
		$this->assertSame(443, $request->port());
		$this->assertSame('https://example.test/back', $request->referer());
	}
}
