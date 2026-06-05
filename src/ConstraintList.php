<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class ConstraintList implements ConstraintTypeInterface
{
    private $constraints = [];

    public function __construct($constraints)
    {
        $this->constraints = $constraints;
    }

    public function hasType(string $type): bool
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint->hasType($type)) {
                return true;
            }
        }

        return false;
    }

    public function validate($value, array|null $args = null, $context = null)
    {
        $violations = [];
        foreach ($this->constraints as $constraint) {
            $violation = $constraint->validate($value, $args, $context);
            if ($violation) {
                $violations[] = $violation;
                if ($violation->stopProcessing) {
                    break;
                }
            }
        }

        if (count($violations) > 0) {
            return ConstraintViolation::merge($violations);
        }
    }
}
