<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Simple PHP template renderer with variable scope and capture support.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Template
{
	public $file = null;

	private $captured = null;
	private $vars = [];

	public function __construct($file = null, $vars = null)
	{
		$this->file = $file;
		if ($vars !== null) {
			$this->vars = $vars;
		}
	}

	public function set($key, $value)
	{
		$this->vars[$key] = $value;
	}

	public function setByRef($key, &$value)
	{
		$this->vars[$key] = &$value;
	}

	public function getVars(): array|null
	{
		return $this->vars;
	}

	public static function capture()
	{
		$template = new static();
		$template->captureStart();
		return $template;
	}

	public function captureStart()
	{
		ob_start();
		$this->captured = null;
	}

	public function captureEnd()
	{
		$contents = ob_get_contents();
		ob_end_clean();
		$this->captured = $contents;
		return $contents;
	}

	public function fetch($file = null)
	{
		if (($file === null) && ($this->captured !== null)) {
			return $this->captured;
		}

		ob_start();
		$this->display($file);
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	public function display($file = null)
	{
		if ($this->captured !== null) {
			echo $this->captured;
			return true;
		}

		if ($file !== null) {
			$this->file = $file;
		}

		$this->file = Util::path($this->file);

		// Do any processing of other template objects
		$className = get_class($this);
		foreach ($this->vars as $__vars_key => $__vars_value) {
			if (is_object($__vars_value) && is_a($__vars_value, $className)) {
				// We have an object of the same type, lets fetch its contents
				$this->vars[$__vars_key] = $__vars_value->fetch();
			}
			// Manual extract (note we're doing this by reference)
			${$__vars_key} = &$this->vars[$__vars_key];
		}
		// Don't leak this variable into this scope
		$__template_previous_cwd = getcwd();
		chdir(dirname($this->file));

		unset($file);
		unset($className);
		$ret = ((include $this->file) === true);

		// Reset the previous working directory
		chdir($__template_previous_cwd);
		return $ret;
	}
}