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

use PureFramework\Phrase;
use PureFramework\Tests\TestCase;
use function PureFramework\__;

final class PhraseTest extends TestCase
{
	protected function tearDown(): void
	{
		Phrase::clear();
		parent::tearDown();
	}

	public function testPhraseHelperResolvesRegisteredKey(): void
	{
		Phrase::add('errors.required', 'This field is required.');
		$this->assertSame('This field is required.', __('errors.required'));
	}

	public function testPhraseHelperSubstitutesTokens(): void
	{
		Phrase::add('greeting', 'Hello {name}.');
		$this->assertSame('Hello Ada.', __('greeting', ['name' => 'Ada']));
	}

	public function testPhraseHelperReturnsKeyWhenMissing(): void
	{
		$this->assertSame('errors.unknown', __('errors.unknown'));
	}
}
