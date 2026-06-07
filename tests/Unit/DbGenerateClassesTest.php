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

use PureFramework\DbGenerateClasses;
use PureFramework\Tests\TestCase;

final class DbGenerateClassesTest extends TestCase
{
	private string $tmpRoot;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pure-dto-gen-' . bin2hex(random_bytes(8));
		mkdir($this->tmpRoot, 0777, true);
	}

	protected function tearDown(): void
	{
		$this->removeDir($this->tmpRoot);
		parent::tearDown();
	}

	public function testBuildClassFileContentWithNamespace(): void
	{
		$sql = <<<'SQL'
CREATE TABLE `widget` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `widget_uuid` BINARY(16) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL;

		$config = DbGenerateClasses::parseSqlToConfig($sql);
		$this->assertIsArray($config);

		$content = DbGenerateClasses::buildClassFileContent($config, 'App\\Entity\\DTO');

		$this->assertStringContainsString('namespace App\\Entity\\DTO;', $content);
		$this->assertStringContainsString('class widget {', $content);
		$this->assertStringContainsString('public $widget_uuid', $content);
	}

	public function testGenerateToDirectoryWritesOneFilePerTable(): void
	{
		$sqlDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'sql';
		$outDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'dto';
		mkdir($sqlDir, 0777, true);

		file_put_contents($sqlDir . DIRECTORY_SEPARATOR . 'widget.sql', <<<'SQL'
CREATE TABLE `widget` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `widget_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL);

		$generated = DbGenerateClasses::generateToDirectory($sqlDir, $outDir, 'App\\Entity\\DTO');
		$this->assertSame(['widget'], $generated);

		$file = $outDir . DIRECTORY_SEPARATOR . 'widget.php';
		$this->assertFileExists($file);

		$contents = file_get_contents($file);
		$this->assertIsString($contents);
		$this->assertStringContainsString('namespace App\\Entity\\DTO;', $contents);
		$this->assertStringContainsString('class widget {', $contents);
	}

	public function testGenerateFromPathStillWritesCacheFile(): void
	{
		$sqlDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'sql-cache';
		$cacheFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'generated.php';
		mkdir($sqlDir, 0777, true);

		file_put_contents($sqlDir . DIRECTORY_SEPARATOR . 'item.sql', <<<'SQL'
CREATE TABLE `item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL);

		$generated = DbGenerateClasses::generateFromPath($sqlDir, $cacheFile);
		$this->assertSame(['item'], $generated);
		$this->assertFileExists($cacheFile);

		$contents = file_get_contents($cacheFile);
		$this->assertIsString($contents);
		$this->assertStringContainsString('class item {', $contents);
		$this->assertStringNotContainsString('namespace ', $contents);
	}

	public function testNormalizeNamespaceRejectsInvalidValue(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		DbGenerateClasses::generateToDirectory($this->tmpRoot, $this->tmpRoot, '123-invalid');
	}

	public function testParseSqlToConfigExtractsColumnMetadata(): void
	{
		$sql = <<<'SQL'
CREATE TABLE `account` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `account_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL;

		$config = DbGenerateClasses::parseSqlToConfig($sql);
		$this->assertIsArray($config);
		$this->assertTrue($config['props']['id']['autoIncrement']);
		$this->assertTrue($config['props']['id']['pk']);
		$this->assertSame('CURRENT_TIMESTAMP', $config['props']['created']['defaultExpr']);
		$this->assertTrue($config['props']['deleted_at']['nullable']);
		$this->assertFalse($config['props']['account_uuid']['nullable']);
	}

	public function testGenerateClassTypedEmitsDocblocksPropertiesAndInsertSkip(): void
	{
		$sql = <<<'SQL'
CREATE TABLE `account` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `account_uuid` BINARY(16) NOT NULL,
  `username` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL;

		$config = DbGenerateClasses::parseSqlToConfig($sql);
		$this->assertIsArray($config);

		$response = DbGenerateClasses::generateClass($config, typed: true);

		$this->assertStringContainsString('@property int $id', $response->class);
		$this->assertStringContainsString('auto-increment', $response->class);
		$this->assertStringContainsString('@property ?string $deleted_at', $response->class);
		$this->assertStringContainsString("public static array \$insertSkip = ['id', 'created', 'updated']", $response->class);
		$this->assertStringContainsString("public static array \$updateSkip = ['id', 'created', 'account_uuid']", $response->class);
		$this->assertStringContainsString("public static string \$uuidProperty = 'account_uuid'", $response->class);
		$this->assertStringContainsString('public int $id = 0;', $response->class);
		$this->assertStringContainsString('public ?string $deleted_at = null;', $response->class);
		$this->assertStringContainsString('public string $username = \'\';', $response->class);
	}

	public function testGenerateFromPathTypedWritesCacheFile(): void
	{
		$sqlDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'sql-typed';
		$cacheFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'typed.php';
		mkdir($sqlDir, 0777, true);

		file_put_contents($sqlDir . DIRECTORY_SEPARATOR . 'item.sql', <<<'SQL'
CREATE TABLE `item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL);

		$generated = DbGenerateClasses::generateFromPath($sqlDir, $cacheFile, typed: true);
		$this->assertSame(['item'], $generated);

		$contents = file_get_contents($cacheFile);
		$this->assertIsString($contents);
		$this->assertStringContainsString('@property int $id', $contents);
		$this->assertStringContainsString('$insertSkip', $contents);
		$this->assertStringContainsString('$updateSkip', $contents);
	}

	public function testIsMigrationSqlFileMatchesNumericPrefix(): void
	{
		$this->assertTrue(DbGenerateClasses::isMigrationSqlFile('00_database.sql'));
		$this->assertTrue(DbGenerateClasses::isMigrationSqlFile('001_add_column.sql'));
		$this->assertFalse(DbGenerateClasses::isMigrationSqlFile('account.sql'));
		$this->assertFalse(DbGenerateClasses::isMigrationSqlFile('bad_seed.sql'));
	}

	public function testGenerateFromPathSkipsMigrationPrefixedSql(): void
	{
		$sqlDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'sql-migrate';
		$cacheFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'with-migrate.php';
		mkdir($sqlDir, 0777, true);

		file_put_contents($sqlDir . DIRECTORY_SEPARATOR . '00_database.sql', <<<'SQL'
CREATE DATABASE IF NOT EXISTS `my_app` DEFAULT CHARACTER SET utf8mb4;
SQL);
		file_put_contents($sqlDir . DIRECTORY_SEPARATOR . 'widget.sql', <<<'SQL'
CREATE TABLE `widget` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `widget_uuid` BINARY(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL);

		$generated = DbGenerateClasses::generateFromPath($sqlDir, $cacheFile);
		$this->assertSame(['widget'], $generated);
	}

	public function testGenerateFromPathThrowsOnUnparseableNonMigrationSql(): void
	{
		$sqlDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'sql-bad';
		$cacheFile = $this->tmpRoot . DIRECTORY_SEPARATOR . 'bad.php';
		mkdir($sqlDir, 0777, true);

		file_put_contents($sqlDir . DIRECTORY_SEPARATOR . 'seed_data.sql', 'INSERT INTO account VALUES (1);');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Failed to parse seed_data.sql');
		DbGenerateClasses::generateFromPath($sqlDir, $cacheFile);
	}

	public function testParseSqlToConfigIgnoresIndexDefinitions(): void
	{
		$sql = <<<'SQL'
CREATE TABLE `account` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_uuid` BINARY(16) NOT NULL,
    `username` VARCHAR(32) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_account_uuid` (`account_uuid`),
    KEY `idx_username` (`username`)
) ENGINE=InnoDB;
SQL;

		$config = DbGenerateClasses::parseSqlToConfig($sql);
		$this->assertIsArray($config);
		$this->assertSame(['id', 'account_uuid', 'username'], array_keys($config['props']));
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) {
				$this->removeDir($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}
}
