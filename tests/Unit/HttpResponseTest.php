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

use PureFramework\ErrorResponse;
use PureFramework\HttpResponse;
use PureFramework\SuccessResponse;
use PureFramework\Tests\TestCase;

final class HttpResponseTest extends TestCase
{
	public function testJsonEnvelope(): void
	{
		ob_start();
		HttpResponse::json(new SuccessResponse(['count' => 2]), 200, false);
		$parsed = json_decode((string) ob_get_clean(), true);
		$this->assertIsArray($parsed);
		$this->assertSame('success', $parsed['status'] ?? null);
		$this->assertSame(2, $parsed['data']['count'] ?? null);
	}

	public function testJsonErrorEnvelopeWithStatus(): void
	{
		ob_start();
		HttpResponse::json(new ErrorResponse('bad'), 422, false);
		$errorParsed = json_decode((string) ob_get_clean(), true);
		$this->assertSame('error', $errorParsed['status'] ?? null);
		$this->assertSame('bad', $errorParsed['data'] ?? null);
		$this->assertSame(422, http_response_code());
	}

	public function testJsonPlainData(): void
	{
		ob_start();
		HttpResponse::json(['plain' => true], 200, false);
		$this->assertSame(['plain' => true], json_decode((string) ob_get_clean(), true));
	}

	public function testStatus(): void
	{
		HttpResponse::status(404);
		$this->assertSame(404, http_response_code());
	}
}
