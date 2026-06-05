<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class FormError
{
	private string $name = '';
	private string $label = '';
	private array $violations = [];

	public static function isInstance($formError)
	{
		return $formError instanceof FormError;
	}

	public function __construct($name = '', $label = '', $violations = null)
	{
		if (!empty($name)) {
			$this->name = $name;
		}

		if (!empty($label)) {
			$this->label = $label;
		}

		if (is_array($violations)) {
			$this->violations = $violations;
		}
	}

	public function __get($prop)
	{
		if (in_array($prop, ['name'])) {
			return $this->{$prop};
		}
	}

	public function hasErrors()
	{
		return count($this->violations) > 0;
	}

	public function getMessages($tokens = [])
	{
		$messages = [];

		foreach ($this->violations as $violation) {
			$tokens = array_merge(['name' => $this->name, 'label' => $this->label], $tokens);
			$violationMessages = $violation->getMessages($tokens);
			if (count($violationMessages) > 0) {
				$messages = array_merge($messages, $violationMessages);
			}
		}

		return $messages;
	}
}

class FormField
{
	private $name = '';
	private $label = '';
	private $transforms = [];
	private $constraints = [];

	public function __construct(string $name, string $label = '', array $transforms = [], array $constraints = [])
	{
		$this->name = $name;
		$this->label = $label;
		$this->transforms = $transforms;

		$tmp = [];
		foreach ($constraints as $constraint) {
			if (ConstraintType::isImplementation($constraint)) {
				$tmp[] = $constraint;
			}
		}
		$constraints = $tmp;

		$this->constraints = $constraints;
	}

	public function __get($prop)
	{
		if ($prop === 'required') {
			foreach ($this->constraints as $constraint) {
				if ($constraint->hasType('required')) {
					return true;
				}
			}
			return false;
		}

		if (in_array($prop, ['name', 'label'])) {
			return $this->{$prop};
		}
	}

	public function setLabel(string $label)
	{
		$this->label = $label;
	}

	public function addConstraint($constraint)
	{
		$this->constraints[] = $constraint;
	}

	public function addTransform($transform)
	{
		$this->transforms[] = $transform;
	}

	public function transform($input)
	{
		$value = $input;

		foreach ($this->transforms as $transform) {
			$value = $transform->apply($value);
		}

		return $value;
	}

	public function validate($value, $context = null)
	{
		$validationResults = [];

		$args = [];
		if (!empty($this->label)) {
			$args['label'] = $this->label;
		}
		foreach ($this->constraints as $constraint) {
			$violation = $constraint->validate($value, $args, $context);
			if ($violation === null || empty($violation)) {
				continue;
			}

			$validationResults[] = $violation;
		}

		if (count($validationResults) > 0) {
			return new FormError($this->name, $this->label, $validationResults);
		}

		return true;
	}
}

class Form
{
	private $fields = [];
	private $originalValues = [];
	private $values = [];
	private $formErrors = [];
	private $fieldErrors = [];

	public function __construct($fields = null)
	{
		if (is_array($fields)) {
			$this->fields = $fields;
		}
	}

	public function __get($name)
	{
		if (isset($this->fields[$name])) {
			return $this->values[$name];
		}

		return null;
	}

	public function addField(string $name, string $label = '', array $transforms = [], array $constraints = [], $defaultValue = null)
	{
		$tmp = [];
		foreach ($constraints as $constraint) {
			if (ConstraintEntity::isInstance($constraint)) {
				$tmp[] = $constraint->forField($name);
			} else {
				$tmp[] = $constraint;
			}
		}
		$constraints = $tmp;

		$this->fields[$name] = new FormField($name, $label, $transforms, $constraints);

		if ($defaultValue !== null) {
			$this->setValue($name, $defaultValue);
		}

		return $this->getField($name);
	}

	public function getField($name): FormField|null
	{
		if (!isset($this->fields[$name])) {
			return null;
		}

		return $this->fields[$name];
	}

	public function hasField($fieldName)
	{
		return isset($this->fields[$fieldName]);
	}

	public function getFieldNames()
	{
		return array_keys($this->fields);
	}

	public function getOriginalValue($fieldName)
	{
		if (!$this->hasField($fieldName)) {
			return null;
		}

		if (!isset($this->originalValues[$fieldName])) {
			return '';
		}

		return $this->originalValues[$fieldName];
	}

	public function getValue($fieldName)
	{
		if ($this->hasField($fieldName)) {
			if (!isset($this->values[$fieldName])) {
				return '';
			}

			return $this->values[$fieldName];
		}

		return null;
	}

	public function getValues(array $fieldNames = [])
	{
		if (empty($fieldNames)) {
			return $this->values;
		}

		$result = [];
		foreach ($fieldNames as $fieldName) {
			if (!$this->hasField($fieldName)) {
				continue;
			}

			$result[$fieldName] = $this->getValue($fieldName);
		}

		return $result;
	}

	public function setValuesAndTransform(array $values)
	{
		$this->setValues($values);
		$this->transformValues();
	}

	public function setValue($fieldName, $value)
	{
		if ($this->hasField($fieldName)) {
			$this->originalValues[$fieldName] = $value;
			$this->values[$fieldName] = $value;
			return true;
		}

		return false;
	}

	public function setValues(array $values)
	{
		$ret = true;
		foreach ($values as $fieldName => $value) {
			if (!$this->setValue($fieldName, $value)) {
				$ret = false;
			}
		}

		return $ret;
	}

	public function setValuesFromObject($object, $fieldNames = null)
	{
		if ($fieldNames === null) {
			$fieldNames = array_keys($this->fields);
		}

		$values = [];
		foreach ($fieldNames as $name) {
			if (isset($object->{$name})) {
				$values[$name] = $object->{$name};
			}
		}

		return $this->setValues($values);
	}

	public function copyValuesToObject($object, $fieldNames = null)
	{
		if ($fieldNames === null) {
			$fieldNames = array_keys($this->fields);
		}

		foreach ($fieldNames as $name) {
			if (property_exists($object, $name)) {
				if (array_key_exists($name, $this->values)) {
					$object->{$name} = $this->values[$name];
				}
			}
		}
	}

	public function setFormErrors(array|string $errors, $append = false): void
	{
		if (is_string($errors)) {
			$errors = [$errors];
		}

		if ($append) {
			$this->formErrors = array_merge($this->formErrors, $errors);
			return;
		}

		$this->formErrors = $errors;
	}

	public function getFormErrors()
	{
		return $this->formErrors;
	}

	public function hasFormErrors(): bool
	{
		return count($this->formErrors) > 0;
	}

	public function clearFormErrors(): void
	{
		$this->formErrors = [];
	}

	public function setFieldError($name, $error, $overwrite = false)
	{
		if ($overwrite) {
			if (!is_array($error)) {
				$error = [$error];
			}
			$this->fieldErrors[$name] = $error;
			return;
		}

		if (!is_array($this->fieldErrors[$name])) {
			$this->fieldErrors[$name] = [];
		}

		$this->fieldErrors[$name][] = $error;
	}

	public function getFieldErrors($name = null)
	{
		if ($name !== null) {
			if (isset($this->fieldErrors[$name])) {
				return $this->fieldErrors[$name];
			}

			return [];
		}

		return $this->fieldErrors;
	}

	public function hasFieldErrors($fieldName = null): bool
	{
		if (isset($this->fields[$fieldName])) {
			return isset($this->fieldErrors[$fieldName]) && count($this->fieldErrors[$fieldName]) > 0;
		}

		if ($fieldName === null) {
			foreach ($this->getFieldNames() as $fieldName) {
				if ($this->hasFieldErrors($fieldName)) {
					return true;
				}
			}
		}

		return false;
	}

	public function clearFieldErrors($fieldName = null): void
	{
		if ($fieldName === null) {
			$this->fieldErrors = [];
			return;
		}

		if ($this->hasField($fieldName)) {
			unset($this->fieldErrors[$fieldName]);
		}
	}

	public function hasErrors(): bool
	{
		return $this->hasFormErrors() || $this->hasFieldErrors();
	}

	public function clearErrors(): void
	{
		$this->clearFormErrors();
		$this->clearFieldErrors();
	}

	public function transformValues($fieldNames = []): void
	{
		if (empty($fieldNames)) {
			$fieldNames = $this->getFieldNames();
		}

		foreach ($fieldNames as $fieldName) {
			$field = $this->getField($fieldName);
			$inputValue = $this->getOriginalValue($fieldName);
			$value = $field->transform($inputValue);
			if ($value !== $inputValue) {
				$this->values[$fieldName] = $value;
			}
		}
	}

	public function validate($fieldNames = [], $context = null): bool
	{
		if (empty($fieldNames)) {
			$fieldNames = $this->getFieldNames();
		}

		foreach ($fieldNames as $fieldName) {
			if (!$this->hasField($fieldName)) {
				return false;
			}

			$this->clearFieldErrors($fieldName);

			$field = $this->getField($fieldName);
			$value = $this->getValue($fieldName);

			$response = $field->validate($value, $context);
			if (FormError::isInstance($response)) {
				$this->setFieldError($fieldName, $response, true);
			}
		}

		return !$this->hasErrors();
	}
}
