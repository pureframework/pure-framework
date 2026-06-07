<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

namespace PureFramework\Tests\Support;

class account
{
    /** @var list<string> */
    public static array $insertSkip = ['id'];

    /** @var list<string> */
    public static array $updateSkip = ['id', 'account_uuid'];

    public static string $uuidProperty = 'account_uuid';

    public int $id = 0;

    public string $account_uuid = '';

    public string $username = '';
}
