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

use PureFramework\Csrf;
use PureFramework\Request;
use PureFramework\Tests\TestCase;

final class CsrfTest extends TestCase
{
	private mixed $sessionBackup = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionBackup = $_SESSION ?? null;
		$_SESSION = [];
	}

	protected function tearDown(): void
	{
		Csrf::reset();
		if ($this->sessionBackup === null) {
			unset($_SESSION);
		} else {
			$_SESSION = $this->sessionBackup;
		}
		parent::tearDown();
	}

	public function testTokenFieldVerifyHeaderRegenerateConfigure(): void
	{
		$token = Csrf::token();
		$this->assertSame(64, strlen($token));
		$this->assertSame($token, Csrf::token());
		$this->assertStringContainsString('name="_csrf"', Csrf::field());
		$this->assertStringContainsString($token, Csrf::field());

		$request = new Request(false);
		$request->setPost(['_csrf' => $token]);
		$this->assertTrue(Csrf::verify($request));

		$request->setPost(['_csrf' => 'invalid']);
		$this->assertFalse(Csrf::verify($request));

		$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
		$this->assertTrue(Csrf::verifyHeader());
		$_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid';
		$this->assertFalse(Csrf::verifyHeader());
		unset($_SERVER['HTTP_X_CSRF_TOKEN']);

		$before = Csrf::token();
		$after = Csrf::regenerate();
		$this->assertNotSame($before, $after);
		$this->assertSame($after, Csrf::token());

		Csrf::configure('custom_csrf', 'csrf_field', 'X-Custom-Token');
		$_SESSION = ['custom_csrf' => bin2hex(random_bytes(32))];
		$customToken = Csrf::token();
		$customRequest = new Request(false);
		$customRequest->setPost(['csrf_field' => $customToken]);
		$this->assertTrue(Csrf::verify($customRequest));
		$_SERVER['HTTP_X_CUSTOM_TOKEN'] = $customToken;
		$this->assertTrue(Csrf::verifyHeader());
		unset($_SERVER['HTTP_X_CUSTOM_TOKEN']);
	}
}
