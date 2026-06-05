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

use PureFramework\Tests\Support\NamespacedRequiredConstraint;
use PureFramework\Tests\Support\RequiredConstraint;
use PureFramework\Tests\TestCase;

final class ConstraintTypeTest extends TestCase
{
	public function testTypeSlugUsesShortClassName(): void
	{
		$this->assertTrue((new RequiredConstraint())->hasType('required'));
		$this->assertFalse((new NamespacedRequiredConstraint())->hasType('pure_framework'));
		$this->assertTrue((new NamespacedRequiredConstraint())->hasType('namespaced_required'));
	}
}
