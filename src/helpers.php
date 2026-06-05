<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Global helper functions (html, __).
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

if (!function_exists(__NAMESPACE__ . '\\html')) {
	/**
	 * Escape a value for safe output in HTML (see Util::html()).
	 */
	function html(mixed $value, bool $doubleEncode = false): string
	{
		return Util::html($value, $doubleEncode);
	}
}

if (!function_exists(__NAMESPACE__ . '\\__')) {
	/**
	 * Resolve a phrase key to a localized string (see Phrase::get()).
	 *
	 * Register strings with Phrase::add() or Phrase::load() in application bootstrap.
	 */
	function __(string $key, array $params = [], ?string $language = null): string
	{
		return Phrase::get($key, $params, $language);
	}
}
