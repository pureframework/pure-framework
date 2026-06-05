<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Application-facing facade for HTTP emission: status codes, headers, and response bodies.
 * Use with {@see SuccessResponse} / {@see ErrorResponse} envelopes for JSON APIs.
 * Use {@see Display} for HTML pages, redirects, and 404 templates.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class HttpResponse
{
	public static function status(int $statusCode): void
	{
		if (!headers_sent()) {
			http_response_code($statusCode);
		}
	}

	/**
	 * Send JSON (Content-Type, status code, body). Exits by default.
	 *
	 * Accepts Response envelopes or any json_encode-able value.
	 */
	public static function json(mixed $payload, int $statusCode = 200, bool $exit = true): void
	{
		if ($payload instanceof Response) {
			$body = $payload->json();
		} else {
			$body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		}

		self::emit($body, $statusCode, 'application/json; charset=UTF-8', $exit);
	}

	private static function emit(string $body, int $statusCode, string $contentType, bool $exit): void
	{
		if (!headers_sent()) {
			http_response_code($statusCode);
			header('Content-Type: ' . $contentType);
		}

		echo $body;

		if ($exit) {
			exit;
		}
	}
}
