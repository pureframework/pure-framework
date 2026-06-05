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

use PureFramework\Tests\Support\UuidDbTestClass;
use PureFramework\Tests\TestCase;
use PureFramework\Util;
use PureFramework\UuidDbTrait;

final class UuidDbTraitTest extends TestCase
{
	public function testTraitExists(): void
	{
		$this->assertTrue(trait_exists(UuidDbTrait::class));
	}

	public function testEncodeDecodeRoundTrip(): void
	{
		$uuidHex = Util::uuid();
		$packed = UuidDbTestClass::encodeData(['client_uuid' => $uuidHex]);
		$this->assertIsString($packed['client_uuid']);
		$this->assertSame(16, strlen($packed['client_uuid']));

		$packedWhere = UuidDbTestClass::encodeWhereData(['client_uuid' => $uuidHex]);
		$this->assertIsString($packedWhere['client_uuid']);
		$this->assertSame(16, strlen($packedWhere['client_uuid']));

		$rawWhere = ' WHERE id = 1';
		$this->assertSame($rawWhere, UuidDbTestClass::encodeWhereData($rawWhere));

		$row = (object) ['client_uuid' => $packed['client_uuid']];
		$decoded = UuidDbTestClass::decodeRows([$row]);
		$this->assertIsArray($decoded);
		$this->assertSame(strtolower($uuidHex), $decoded[0]->client_uuid);
		$this->assertFalse(UuidDbTestClass::decodeRows(false));
	}
}
