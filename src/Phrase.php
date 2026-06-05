<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

final class Phrase
{
	private static $phrases = [];
	private static $language = 'en';

	public static function setLanguage($language)
	{
		self::$language = $language;
	}

	public static function getLanguage()
	{
		return self::$language;
	}

	public static function add($key, $phrase, $language = null)
	{
		$lang = $language ?: self::$language;
		if (!isset(self::$phrases[$lang])) {
			self::$phrases[$lang] = [];
		}
		self::$phrases[$lang][$key] = $phrase;
	}

	public static function get($key, $params = [], $language = null)
	{
		$lang = $language ?: self::$language;
		$phrase = isset(self::$phrases[$lang][$key]) ? self::$phrases[$lang][$key] : $key;

		if (!empty($params)) {
			foreach ($params as $param => $value) {
				$phrase = str_replace('{' . $param . '}', (string) $value, $phrase);
			}
		}

		return $phrase;
	}

	public static function load($file, $language = null)
	{
		$lang = $language ?: self::$language;
		if (file_exists($file)) {
			$phrases = include $file;
			if (is_array($phrases)) {
				if (!isset(self::$phrases[$lang])) {
					self::$phrases[$lang] = [];
				}
				self::$phrases[$lang] = array_merge(self::$phrases[$lang], $phrases);
			}
		}
	}

	public static function has($key, $language = null)
	{
		$lang = $language ?: self::$language;
		return isset(self::$phrases[$lang][$key]);
	}

	public static function getAll($language = null)
	{
		$lang = $language ?: self::$language;
		return isset(self::$phrases[$lang]) ? self::$phrases[$lang] : [];
	}

	public static function clear($language = null)
	{
		if ($language) {
			unset(self::$phrases[$language]);
		} else {
			self::$phrases = [];
		}
	}
}
