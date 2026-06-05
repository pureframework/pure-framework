<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

namespace PureFramework\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PureFramework\DB;
use PureFramework\Tests\Support\account;
use PureFramework\Tests\Support\plain;
use PureFramework\Tests\Support\todo;
use PureFramework\Util;

class ObjectFactoryTest extends TestCase
{
    public function testObjectFactoryUsesFieldWhitelist(): void
    {
        $obj = DB::objectFactory(plain::class, [
            'name' => 'alpha',
            'extra' => 'ignored',
        ], ['name']);

        $this->assertSame('alpha', $obj->name);
        $this->assertObjectNotHasProperty('extra', $obj);
    }

    public function testObjectFactoryUsesDataKeysWhenFieldsNull(): void
    {
        $obj = DB::objectFactory(plain::class, ['name' => 'beta']);

        $this->assertSame('beta', $obj->name);
    }

    public function testObjectFactoryDoesNotAutoPopulateUuid(): void
    {
        $obj = DB::objectFactory(todo::class, [
            'account_uuid' => '00000000-0000-0000-0000-000000000001',
            'title' => 'Task',
        ]);

        $vars = get_object_vars($obj);
        $this->assertArrayNotHasKey('todo_uuid', $vars);
    }

    public function testObjectFactoryPopulatesUuidWhenExplicit(): void
    {
        $obj = DB::objectFactory(todo::class, [
            'account_uuid' => '00000000-0000-0000-0000-000000000001',
            'title' => 'Task',
        ], null, 'todo_uuid');

        $this->assertNotSame('', $obj->todo_uuid);
        $this->assertTrue(Util::sanitizeUuid($obj->todo_uuid) !== false);
    }

    public function testObjectFactorySkipsUuidWhenFalse(): void
    {
        $obj = DB::objectFactory(todo::class, [
            'todo_uuid' => '00000000-0000-0000-0000-000000000099',
            'title' => 'Task',
        ], null, false);

        $this->assertSame('00000000-0000-0000-0000-000000000099', $obj->todo_uuid);
    }

    public function testObjectInsertFactoryHonorsInsertSkip(): void
    {
        $obj = DB::objectInsertFactory(account::class, [
            'id' => 99,
            'username' => 'alice',
        ]);

        $vars = get_object_vars($obj);
        $this->assertArrayNotHasKey('id', $vars);
        $this->assertSame('alice', $obj->username);
    }

    public function testObjectInsertFactoryAutoPopulatesUuid(): void
    {
        $obj = DB::objectInsertFactory(todo::class, [
            'account_uuid' => '00000000-0000-0000-0000-000000000001',
            'title' => 'Task',
        ]);

        $this->assertNotSame('', $obj->todo_uuid);
        $this->assertTrue(Util::sanitizeUuid($obj->todo_uuid) !== false);
    }

    public function testObjectInsertFactoryUsesUuidPropertyStatic(): void
    {
        $obj = DB::objectInsertFactory(account::class, [
            'username' => 'bob',
        ]);

        $this->assertNotSame('', $obj->account_uuid);
        $this->assertTrue(Util::sanitizeUuid($obj->account_uuid) !== false);
    }

    public function testObjectInsertFactorySkipsUuidWhenFalse(): void
    {
        $obj = DB::objectInsertFactory(todo::class, [
            'todo_uuid' => '00000000-0000-0000-0000-000000000099',
            'title' => 'Task',
        ], null, false);

        $this->assertSame('00000000-0000-0000-0000-000000000099', $obj->todo_uuid);
    }
}
