<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Session-based CSRF tokens for HTML forms and optional header verification (e.g. AJAX).
 * Defaults (no configure() required): session key $_SESSION['_csrf'], form field _csrf, header X-CSRF-Token.
 * The application must start the session before calling token(), field(), or verify methods.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Csrf
{
	private const DEFAULT_SESSION_KEY = '_csrf';
	private const DEFAULT_FIELD_NAME = '_csrf';
	private const DEFAULT_HEADER_NAME = 'X-CSRF-Token';

	private static string $sessionKey = self::DEFAULT_SESSION_KEY;
	private static string $fieldName = self::DEFAULT_FIELD_NAME;
	private static string $headerName = self::DEFAULT_HEADER_NAME;

	/**
	 * Override session key, form field name, and/or header name. Omit configure() to use defaults.
	 */
	public static function configure(
		string $sessionKey = self::DEFAULT_SESSION_KEY,
		string $fieldName = self::DEFAULT_FIELD_NAME,
		string $headerName = self::DEFAULT_HEADER_NAME,
	): void {
		self::$sessionKey = $sessionKey;
		self::$fieldName = $fieldName;
		self::$headerName = $headerName;
	}

	public static function reset(): void
	{
		self::$sessionKey = self::DEFAULT_SESSION_KEY;
		self::$fieldName = self::DEFAULT_FIELD_NAME;
		self::$headerName = self::DEFAULT_HEADER_NAME;
	}

	public static function token(): string
	{
		if (empty($_SESSION[self::$sessionKey])) {
			$_SESSION[self::$sessionKey] = bin2hex(random_bytes(32));
		}

		return (string) $_SESSION[self::$sessionKey];
	}

	public static function regenerate(): string
	{
		$_SESSION[self::$sessionKey] = bin2hex(random_bytes(32));
		return (string) $_SESSION[self::$sessionKey];
	}

	public static function field(?string $fieldName = null): string
	{
		$fieldName ??= self::$fieldName;
		$token = Util::html(self::token());
		$name = Util::html($fieldName);

		return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
	}

	public static function verify(?Request $request = null, ?string $fieldName = null): bool
	{
		$fieldName ??= self::$fieldName;

		if ($request !== null) {
			$submitted = (string) $request->post($fieldName, '');
		} else {
			$submitted = (string) ($_POST[$fieldName] ?? '');
		}

		return self::compareSubmitted($submitted);
	}

	public static function verifyHeader(?string $headerName = null): bool
	{
		$headerName ??= self::$headerName;
		$submitted = self::readHeader($headerName);

		return self::compareSubmitted($submitted);
	}

	private static function compareSubmitted(string $submitted): bool
	{
		if ($submitted === '') {
			return false;
		}

		$expected = (string) ($_SESSION[self::$sessionKey] ?? '');

		if ($expected === '') {
			return false;
		}

		return hash_equals($expected, $submitted);
	}

	private static function readHeader(string $headerName): string
	{
		$serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));

		if (isset($_SERVER[$serverKey])) {
			return (string) $_SERVER[$serverKey];
		}

		if (function_exists('getallheaders')) {
			$headers = getallheaders();
			if (is_array($headers)) {
				foreach ($headers as $name => $value) {
					if (strcasecmp($name, $headerName) === 0) {
						return (string) $value;
					}
				}
			}
		}

		return '';
	}
}
