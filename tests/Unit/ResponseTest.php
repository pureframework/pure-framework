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
use PureFramework\Response;
use PureFramework\SuccessResponse;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
	public function testSuccessEnvelope(): void
	{
		$response = new SuccessResponse('uuid-1', ['extra' => true]);

		$this->assertTrue($response->isSuccess());
		$this->assertFalse($response->isError());

		$serialized = $response->jsonSerialize();
		$this->assertSame(Response::SUCCESS, $serialized->status);
		$this->assertSame('uuid-1', $serialized->data);
		$this->assertSame(['extra' => true], $serialized->related);
	}

	public function testErrorEnvelopeJson(): void
	{
		$response = new ErrorResponse('failed', ['field' => 'bad']);

		$this->assertTrue($response->isError());
		$parsed = json_decode($response->json(), true);
		$this->assertSame('error', $parsed['status']);
		$this->assertSame('failed', $parsed['data']);
	}
}
