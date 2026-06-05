<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class TransformList implements TransformInterface
{
    private $transforms = [];

    public function __construct($transforms)
    {
        $this->transforms = $transforms;
    }

    public function apply(mixed $value): mixed
    {
        foreach ($this->transforms as $transform) {
            $value = $transform->apply($value);
        }

        return $value;
    }
}

