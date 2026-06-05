<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Opt-in UUID encoding for application DB subclasses.
 * Converts *_uuid properties between hyphenated hex (PHP) and 16-byte binary (PDO/MySQL).
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

trait UuidDbTrait
{
    public static function decodeRows($rows)
    {
        if (!is_array($rows)) {
            return $rows;
        }

        foreach ($rows as $k => $v) {
            $rows[$k] = Util::unpackUuidProperties($v);
        }

        return $rows;
    }

    public static function encodeWhereData($where)
    {
        if (is_array($where)) {
            return Util::packUuidProperties($where);
        }

        return $where;
    }

    public static function encodeData($data, $insert = false)
    {
        return Util::packUuidProperties($data);
    }
}
