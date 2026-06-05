<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

namespace PureFramework\Tests\Unit;

use PureFramework\DB;
use PureFramework\DbGenerateClasses;
use PureFramework\Tests\TestCase;

final class DBTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		DB::connection('sqlite::memory:', '', '');
		DB::rawQuery('CREATE TABLE tx_smoke (id INTEGER PRIMARY KEY AUTOINCREMENT, val TEXT NOT NULL)');
	}

	public function testTransactionApiExists(): void
	{
		$this->assertTrue(method_exists(DB::class, 'fetchSingle'));
		$this->assertTrue(method_exists(DB::class, 'beginTransaction'));
		$this->assertTrue(method_exists(DB::class, 'transaction'));
		$this->assertTrue(method_exists(DbGenerateClasses::class, 'generateFromPath'));
		$this->assertTrue(method_exists(DbGenerateClasses::class, 'generateToDirectory'));
		$this->assertNotFalse(DB::connection());
	}

	public function testCommitAndRollback(): void
	{

		$this->assertTrue(DB::beginTransaction());
		$this->assertTrue(DB::inTransaction());
		$this->assertNotFalse(DB::insert('tx_smoke', ['val' => 'committed']));
		$this->assertTrue(DB::commit());
		$this->assertFalse(DB::inTransaction());

		$committed = DB::fetchSingle('tx_smoke', ['val' => 'committed']);
		$this->assertNotNull($committed);
		$this->assertSame('committed', $committed->val);

		$this->assertTrue(DB::beginTransaction());
		DB::insert('tx_smoke', ['val' => 'rolled-back']);
		$this->assertTrue(DB::rollback());
		$this->assertNull(DB::fetchSingle('tx_smoke', ['val' => 'rolled-back']));
	}

	public function testTransactionWrapper(): void
	{
		$result = DB::transaction(function () {
			DB::insert('tx_smoke', ['val' => 'via-wrapper']);

			return 'ok';
		});
		$this->assertSame('ok', $result);
		$this->assertNotNull(DB::fetchSingle('tx_smoke', ['val' => 'via-wrapper']));

		$failed = false;
		try {
			DB::transaction(function () {
				DB::insert('tx_smoke', ['val' => 'should-not-exist']);
				throw new \RuntimeException('rollback me');
			});
		} catch (\RuntimeException) {
			$failed = true;
		}
		$this->assertTrue($failed);
		$this->assertNull(DB::fetchSingle('tx_smoke', ['val' => 'should-not-exist']));
	}
}
