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

use PureFramework\ConstraintEntity;

final class NameConstraintEntity extends ConstraintEntity
{
	public function __construct()
	{
		$this->defineField('name', [new RequiredConstraint()]);
	}
}
