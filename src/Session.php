<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Session bootstrap: cookie params, eager/lazy start, destroy, regenerate.
 * Does not read or write application session state — callers own $_SESSION keys.
 * CSRF rotation on regenerate is the one exception (paired with Csrf).
 * Call configureCookieParams() before start(). Use start(lazy: true) on public sites to avoid creating a session cookie for anonymous visitors.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Session
{
	private static bool $cookieConfigured = false;

	/**
	 * Set session cookie parameters. Call before start(). Safe to call more than once;
	 * the last call wins (PHP behavior).
	 *
	 * @param array{
	 *   lifetime?: int,
	 *   path?: string,
	 *   domain?: string,
	 *   secure?: bool|null,
	 *   httponly?: bool,
	 *   samesite?: string
	 * } $options
	 */
	public static function configureCookieParams(array $options = []): void
	{
		$secure = $options['secure'] ?? self::detectHttps();
		if ($secure === null) {
			$secure = false;
		}

		session_set_cookie_params([
			'lifetime' => $options['lifetime'] ?? 0,
			'path' => $options['path'] ?? '/',
			'domain' => $options['domain'] ?? '',
			'secure' => (bool) $secure,
			'httponly' => $options['httponly'] ?? true,
			'samesite' => $options['samesite'] ?? 'Lax',
		]);

		self::$cookieConfigured = true;
	}

	/**
	 * Align PHP session GC with cookie lifetime (e.g. database-backed sessions).
	 */
	public static function configureGc(int $maxLifetime, ?int $probability = null, ?int $divisor = null): void
	{
		ini_set('session.gc_maxlifetime', (string) $maxLifetime);

		if ($probability !== null) {
			ini_set('session.gc_probability', (string) $probability);
		}

		if ($divisor !== null) {
			ini_set('session.gc_divisor', (string) $divisor);
		}
	}

	/**
	 * Start the PHP session if not already active.
	 *
	 * @param bool $lazy When true, start only when the session cookie already exists
	 *                   (no new cookie for first-time visitors).
	 * @return bool True when the session is active after this call
	 */
	public static function start(bool $lazy = false): bool
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return true;
		}

		if ($lazy) {
			$name = session_name();
			if ($name === '' || !isset($_COOKIE[$name])) {
				return false;
			}
		}

		return session_start();
	}

	public static function isActive(): bool
	{
		return session_status() === PHP_SESSION_ACTIVE;
	}

	/**
	 * End the session. When $clearData is true, empties $_SESSION before destroy.
	 */
	public static function destroy(bool $clearData = true): void
	{
		if ($clearData) {
			$_SESSION = [];
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}
	}

	/**
	 * Regenerate session ID (e.g. after login). Optionally rotates the CSRF token.
	 */
	public static function regenerate(bool $deleteOld = true, bool $regenerateCsrf = true): void
	{
		if (!self::isActive()) {
			self::start();
		}

		session_regenerate_id($deleteOld);

		if ($regenerateCsrf) {
			Csrf::regenerate();
		}
	}

	/** @internal Tests only — reset static configuration flag */
	public static function reset(): void
	{
		self::$cookieConfigured = false;
	}

	public static function cookieConfigured(): bool
	{
		return self::$cookieConfigured;
	}

	private static function detectHttps(): ?bool
	{
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			return true;
		}

		return null;
	}
}
