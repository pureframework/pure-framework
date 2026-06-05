<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

interface ConstraintTypeInterface
{
    public function hasType(string $type): bool;
    public function validate($value, array|null $args = null, $context = null);
}