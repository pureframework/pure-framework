<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class ConstraintEntity implements ConstraintTypeInterface
{
    private array $fields = [];

    public static function isInstance($instance)
    {
        return $instance instanceof static;
    }

    public static function runValidation($value, $args = null, $context = null)
    {
        $instance = new static();
        return $instance->validate($value, $args, $context);
    }

    public function __construct($fields = null)
    {
        if (is_array($fields)) {
            foreach ($fields as $name => $constraints) {
                $this->defineField($name, $constraints);
            }
        }
    }

    public function hasType(string $type): bool
    {
        // Return false for all cases as this would be field dependent
        return false;
    }

    public function defineField($name, ConstraintType|array $constraints)
    {
        if (is_array($constraints)) {
            $constraints = new ConstraintList($constraints);
        }

        $this->fields[$name] = $constraints;
    }

    public function forField($name)
    {
        // Return the constraints for just this field
        // Return empty ConstraintList if field doesn't exist to prevent undefined array key warnings
        if (!isset($this->fields[$name])) {
            return new ConstraintList([]);
        }
        return $this->fields[$name];
    }

    public function validate($values, $args = null, $context = null)
    {
        if (is_object($values)) {
            // We were passed an object, so get the values as an associative array
            $values = get_object_vars($values);
        }

        $violations = [];
        foreach ($values as $field => $value) {
            if (isset($this->fields[$field])) {
                $constraint = $this->fields[$field];
                $fieldViolation = $constraint->validate($value, $args, $context);
                if ($fieldViolation) {
                    $violations[$field] = $fieldViolation;
                }
            }
        }

        if (count($violations) > 0) {
            return $violations;
        }
    }
}
