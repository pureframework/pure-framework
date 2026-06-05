<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

/**
 * @deprecated Use `composer test` (PHPUnit) or `vendor/bin/phpunit`.
 */
declare(strict_types=1);

$phpunit = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
if (!is_file($phpunit)) {
	fwrite(STDERR, "PHPUnit not installed. Run: composer install\n");
	exit(1);
}

passthru(PHP_BINARY . ' ' . escapeshellarg($phpunit) . ' ' . implode(' ', array_map('escapeshellarg', array_slice($argv, 1))), $exit);
exit($exit);
