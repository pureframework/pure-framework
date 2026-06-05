<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

namespace PureFramework\Tests\Support;

use PureFramework\ConstraintType;
use PureFramework\ConstraintViolation;

final class RequiredConstraint extends ConstraintType
{
	public function validate($value, $args = null, $context = null)
	{
		if (!$value || $value === '') {
			$mergedArgs = array_merge($args ? $args : [], ['value' => $value]);

			return new ConstraintViolation('required', '{label} is required', $mergedArgs);
		}
	}
}
