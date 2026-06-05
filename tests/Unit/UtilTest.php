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

use PureFramework\Tests\TestCase;
use PureFramework\Util;
use function PureFramework\html;

final class UtilTest extends TestCase
{
	public function testUuidReturnsV7Format(): void
	{
		$uuid = Util::uuid();
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$uuid
		);
	}

	public function testSanitizeUuidNormalizes(): void
	{
		$uuid = Util::uuid();
		$sanitized = Util::sanitizeUuid(strtoupper(str_replace('-', '', $uuid)));
		$this->assertSame($uuid, $sanitized);
	}

	public function testUuidBinaryRoundTrip(): void
	{
		$uuid = Util::uuid();
		$this->assertSame($uuid, Util::uuidToHex(Util::uuidToBinary($uuid)));
	}

	public function testHtmlEscapesAngleBrackets(): void
	{
		$this->assertSame('&lt;script&gt;', Util::html('<script>'));
	}

	public function testHtmlEscapesQuotes(): void
	{
		$this->assertStringContainsString('&quot;', Util::html('"x"'));
	}

	public function testHtmlNullBecomesEmptyString(): void
	{
		$this->assertSame('', Util::html(null));
	}

	public function testHtmlStringifiesNumbers(): void
	{
		$this->assertSame('42', Util::html(42));
	}

	public function testHtmlArraysBecomeEmptyString(): void
	{
		$this->assertSame('', Util::html(['bad']));
	}

	public function testHtmlBoolsBecomeOneAndZero(): void
	{
		$this->assertSame('1', Util::html(true));
		$this->assertSame('0', Util::html(false));
	}

	public function testGlobalHtmlMatchesUtilHtml(): void
	{
		$this->assertTrue(function_exists('PureFramework\\html'));
		$this->assertSame(Util::html('<a>'), html('<a>'));
	}
}
