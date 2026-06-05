<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

abstract class ConstraintType implements ConstraintTypeInterface
{
	private string $type = '';
	private array $defaultArgs = [];

	public static function runValidation($value, $args = null, $context = null)
	{
		$instance = new static();
		return $instance->validate($value, $args, $context);
	}

	public static function isImplementation($instance)
	{
		return $instance instanceof ConstraintTypeInterface;
	}

	public function __construct($defaultArgs = [])
	{
		$class = static::class;
		if (str_contains($class, '\\')) {
			$class = substr($class, strrpos($class, '\\') + 1);
		}
		$slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
		if (str_ends_with($slug, '_constraint')) {
			$slug = substr($slug, 0, -strlen('_constraint'));
		}
		$this->type = $slug;
		$this->defaultArgs = $defaultArgs;
	}

	public function hasType(string $name): bool
	{
		return $this->type === $name;
	}

	protected function args($args = null)
	{
		if (!is_array($args)) {
			return $this->defaultArgs;
		}

		return array_merge($this->defaultArgs, $args);
	}

	// Default implementation
	public function validate($value, array|null $args = null, $context = null)
	{
		return ConstraintViolation::merge([]);
	}
}
