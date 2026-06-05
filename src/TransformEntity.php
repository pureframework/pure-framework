<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class TransformEntity implements TransformInterface
{
    private array $fields = [];

    public static function isInstance($instance)
    {
        return $instance instanceof static;
    }

    public static function runApply($value)
    {
        $instance = new static();
        return $instance->apply($value);
    }

    public function __construct($fields = null)
    {
        if (is_array($fields)) {
            foreach ($fields as $name => $transforms) {
                $this->defineField($name, $transforms);
            }
        }
    }

    public function defineField($name, TransformInterface|array $transforms)
    {
        if (is_array($transforms)) {
            $transforms = new TransformList($transforms);
        }

        $this->fields[$name] = $transforms;
    }

    public function forField($name)
    {
        return $this->fields[$name];
    }

    public function apply($entityObject): mixed
    {
        if (is_object($entityObject)) {
            // Apply transforms to each field and update the object properties
            foreach ($this->fields as $field => $transform) {
                if (isset($entityObject->{$field})) {
                    $originalValue = $entityObject->{$field};
                    $transformedValue = $transform->apply($originalValue);
                    $entityObject->{$field} = $transformedValue;
                }
            }
            return $entityObject;
        } else if (is_array($entityObject)) {
            // Apply transforms to each field and update the array values
            foreach ($this->fields as $field => $transform) {
                if (isset($entityObject[$field])) {
                    $originalValue = $entityObject[$field];
                    $transformedValue = $transform->apply($originalValue);
                    $entityObject[$field] = $transformedValue;
                }
            }
            return $entityObject;
        }

        return $entityObject;
    }
}