<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Util
{
	public static function uuid($binary = false)
	{
		// v7 implmentation

		// Get current time in milliseconds
		$time = (int) (microtime(true) * 1000);

		// Split into high/low parts
		$timeHigh = ($time >> 16) & 0xFFFFFFFFFFFF; // top 48 bits
		$timeLow = $time & 0xFFFF;                 // lower 16 bits

		// Random 62 bits
		$rand = random_bytes(8);
		$rand = unpack('J', $rand)[1] & 0x3FFFFFFFFFFFFFFF;

		// Assemble fields
		$timeHex = str_pad(dechex($timeHigh), 12, '0', STR_PAD_LEFT)
			. str_pad(dechex($timeLow), 4, '0', STR_PAD_LEFT);

		$randHex = str_pad(dechex($rand), 16, '0', STR_PAD_LEFT);

		// Insert version (7) and variant (10xx)
		$timeHex[12] = '7'; // version
		$randHex[0] = dechex((hexdec($randHex[0]) & 0x3) | 0x8); // variant

		// Return canonical string
		$uuid = sprintf(
			'%s-%s-%s-%s-%s',
			substr($timeHex, 0, 8),
			substr($timeHex, 8, 4),
			substr($timeHex, 12, 4),
			substr($randHex, 0, 4),
			substr($randHex, 4)
		);
		$uuid = strtolower($uuid);

		if ($binary === true) {
			return self::uuidToBinary($uuid);
		}

		return $uuid;
	}

	public static function sanitizeUuid($uuid): string|false
	{
		if (preg_match('/^([0-9a-f]{8})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{4})-?([0-9a-f]{12})$/i', $uuid)) {
			$uuid = str_replace('-', '', $uuid);
			$uuid = strtolower($uuid);
			return implode('-', [
				substr($uuid, 0, 8),
				substr($uuid, 8, 4),
				substr($uuid, 12, 4),
				substr($uuid, 16, 4),
				substr($uuid, 20, 12)
			]);
		}

		return false;
	}

	/**
	 * Escape a value for safe output in HTML text or double-quoted attributes.
	 */
	public static function html(mixed $value, bool $doubleEncode = false): string
	{
		if ($value === null) {
			return '';
		}

		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		if (!is_string($value) && !is_int($value) && !is_float($value)) {
			return '';
		}

		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
	}

	public static function uuidToBinary($uuid)
	{
		if (empty($uuid)) {
			// Return zero UUID for empty values
			return pack('H*', str_repeat('0', 32));
		}

		// Check if UUID is already in binary format (16 bytes)
		if (is_string($uuid) && strlen($uuid) === 16 && !ctype_print($uuid)) {
			// Already binary, return as-is
			return $uuid;
		}

		// Remove dashes and validate hex characters
		$hex = str_replace('-', '', $uuid);

		// Validate that all characters are hexadecimal and length is correct
		if (!ctype_xdigit($hex) || strlen($hex) !== 32) {
			return null;
		}

		return pack('H*', $hex);
	}

	public static function uuidToHex($uuid)
	{
		$hex = unpack('H*', $uuid);
		$hex = $hex[1];
		$hex = preg_replace('/([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})/', "$1-$2-$3-$4-$5", $hex);
		$hex = strtolower($hex);
		return $hex;
	}

	public static function packUuidProperties(object|array $data)
	{
		if (is_object($data)) {
			$data = clone $data;
			$properties = array_keys(get_object_vars($data));
		} else {
			$properties = array_keys($data);
		}

		foreach ($properties as $k) {
			if (substr($k, -5) == '_uuid') {
				$isObject = is_object($data);
				$value = $isObject ? $data->{$k} : $data[$k];
				$binary = Util::uuidToBinary($value);

				if ($binary === null) {
					throw new \Exception("Invalid UUID for property {$k}: '" . ($data->{$k} ?? 'null') . "'");
				}

				if ($isObject) {
					$data->{$k} = $binary;
				} else {
					$data[$k] = $binary;
				}
			}
		}

		return $data;
	}

	public static function unpackUuidProperties(object|array $data)
	{
		if (\is_object($data)) {
			$data = clone $data;
			$properties = \array_keys(\get_object_vars($data));
		} else {
			$properties = \array_keys($data);
		}

		foreach ($properties as $k) {
			if (\substr($k, -5) == '_uuid') {
				$isObject = \is_object($data);
				$value = $isObject ? $data->{$k} : $data[$k];
				if (empty($value)) {
					continue;
				}

				$hex = Util::uuidToHex($value);
				if ($isObject) {
					$data->{$k} = $hex;
				} else {
					$data[$k] = $hex;
				}
			}
		}

		return $data;
	}

	public static function uuidOrValue($uuid, $val = null)
	{
		if (empty($uuid) || $uuid == '00000000-0000-0000-0000-000000000000' || $uuid === 0) {
			return $val;
		}
		return $uuid;
	}

	public static function isUuidZero($uuid)
	{
		return $uuid == '00000000-0000-0000-0000-000000000000' || $uuid === 0;
	}

	public static function zeroUuid()
	{
		return '00000000-0000-0000-0000-000000000000';
	}


	// This safely maps values in an array only to an objects properties
	public static function mapArrayToObject($array, $object, $props = null)
	{
		if ($props === null) {
			$props = array_keys(get_object_vars($object));
		}
		foreach ($props as $p) {
			if (array_key_exists($p, $array)) {
				$object->{$p} = $array[$p];
			}
		}

		return $object;
	}

	public static function mapObjectToArray($object, $props = null)
	{
		if ($props === null) {
			$props = array_keys(get_object_vars($object));
		}
		$ret = [];
		foreach ($props as $p) {
			$ret[$p] = $object->{$p};
		}

		return $ret;
	}

	public static function unsetObjectKeysExcept($obj, $keysToKeep)
	{
		$keys = array_keys(get_object_vars($obj));
		foreach ($keys as $k) {
			if (!in_array($k, $keysToKeep)) {
				unset($obj->{$k});
			}
		}
		return $obj;
	}

	public static function arrayKeysExist($keys, $array)
	{
		foreach ($keys as $k) {
			if (!isset($array[$k])) {
				return false;
			}
		}
		return true;
	}

	public static function extractFieldsToArray($object, $props = null)
	{
		if ($props === null) {
			$props = array_keys(get_object_vars($object));
		}
		$ret = [];
		foreach ($props as $p) {
			$ret[$p] = $object->{$p};
		}

		return $ret;
	}

	/**
	 * Flattens a multimentional array.
	 *
	 * Takes a multi-dimentional array as input and returns a flattened
	 * array as output. Implemented using a non-recursive algorithm.
	 * Example:
	 * <code>
	 * $in = array('John', 'Jim', array('Jane', 'Jasmine'), 'Jake');
	 * $out = array_flatten($in);
	 * // $out = array('John', 'Jim', 'Jane', 'Jasmine', 'Jake');
	 * </code>
	 *
	 * @author        Jonathan Sharp <jdsharp.com>
	 * @var           array
	 * @return        array
	 */
	public static function arrayFlatten($array)
	{
		$tmp = [];
		while (is_array($array) && (count($array) > 0)) {
			$value = array_shift($array);
			if (is_array($value)) {
				$array = array_merge($value, $array);
			} else {
				$tmp[] = $value;
			}
		}
		return $tmp;
	}

	/**
	 * Private utility method for sanitizing and constructing a path
	 *
	 * <code>
	 * self::path('some', './number', array('of', 'path'), 'segments')
	 * </code>
	 *
	 * @author  Jonathan Sharp <jdsharp.com>
	 * @var     mixed
	 * @return  string
	 */
	public static function path()
	{
		$args = func_get_args();
		$tmp = self::arrayFlatten($args);
		$path = implode(DIRECTORY_SEPARATOR, $tmp);

		$_path = $path;
		$path = realpath($path);
		if (empty($path)) {
			// The path may not exist or be complete yet, return the currently composed path
			$path = $_path;
		}

		if (substr($path, -1) == DIRECTORY_SEPARATOR) {
			$path = substr($path, 0, -1);
		}

		return $path;
	}

	public static function isFunction($f)
	{
		// http://stackoverflow.com/questions/2835627/php-is-function-to-determine-if-a-variable-is-a-function
		return (is_string($f) && function_exists($f)) || (is_object($f) && ($f instanceof \Closure));
	}

	public static function generateRandomString($characterSet, $length)
	{
		$charactersLength = strlen($characterSet);
		$random = [];
		for ($i = 0; count($random) < $length; $i++) {
			$char = $characterSet[random_int(0, $charactersLength - 1)];
			if (!in_array($char, $random)) {
				array_push($random, $char);
			}
		}
		return implode('', $random);
	}

	public static function toStdClass($data)
	{
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$obj[$k] = self::toStdClass($v);
			}

			return array_map('self::toStdClass', $data);
		}

		if (is_object($data)) {
			$stdObj = new \stdClass();
			foreach (get_object_vars($data) as $k => $v) {
				$stdObj->$k = self::toStdClass($v);
			}
			return $stdObj;
		}

		return $data;
	}
}
