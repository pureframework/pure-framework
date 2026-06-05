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
use PureFramework\Session;
use PureFramework\Tests\TestCase;

final class SessionTest extends TestCase
{
	private mixed $sessionBackup = null;
	private mixed $cookieBackup = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionBackup = $_SESSION ?? null;
		$this->cookieBackup = $_COOKIE ?? null;
		$_SESSION = [];
		Session::reset();
		Csrf::reset();
	}

	protected function tearDown(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}

		Session::reset();
		Csrf::reset();

		if ($this->sessionBackup === null) {
			unset($_SESSION);
		} else {
			$_SESSION = $this->sessionBackup;
		}

		if ($this->cookieBackup === null) {
			unset($_COOKIE);
		} else {
			$_COOKIE = $this->cookieBackup;
		}

		parent::tearDown();
	}

	public function testConfigureCookieParamsSetsFlag(): void
	{
		$this->assertFalse(Session::cookieConfigured());
		Session::configureCookieParams(['secure' => false, 'lifetime' => 3600]);
		$this->assertTrue(Session::cookieConfigured());
	}

	public function testLazyStartWithoutCookieDoesNotActivate(): void
	{
		$name = session_name();
		unset($_COOKIE[$name]);

		$this->assertFalse(Session::start(lazy: true));
		$this->assertFalse(Session::isActive());
	}

	public function testStartEagerActivatesSession(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}

		$this->assertTrue(Session::start(lazy: false));
		$this->assertTrue(Session::isActive());
	}

	public function testDestroyClearsData(): void
	{
		Session::start();
		$_SESSION['temp'] = 'x';
		Session::destroy(clearData: true);
		$this->assertFalse(Session::isActive());
		$this->assertSame([], $_SESSION);
	}

	public function testRegenerateUpdatesCsrfWhenActive(): void
	{
		Session::start();
		$before = Csrf::token();
		Session::regenerate(regenerateCsrf: true);
		$after = Csrf::token();
		$this->assertNotSame($before, $after);
		$this->assertTrue(Session::isActive());
	}
}
