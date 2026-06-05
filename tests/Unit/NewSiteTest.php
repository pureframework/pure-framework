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

use PureFramework\Tests\TestCase;

final class NewSiteTest extends TestCase
{
	private function removeTree(string $path): void
	{
		if (is_file($path) || is_link($path)) {
			unlink($path);

			return;
		}
		if (!is_dir($path)) {
			return;
		}
		foreach (scandir($path) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
		}
		rmdir($path);
	}

	public function testScaffoldsMinimalSite(): void
	{
		require_once self::projectRoot() . '/scripts/new-site.php';

		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pure-new-site-' . bin2hex(random_bytes(4));

		ob_start();
		$exit = pure_new_site_main(['pure-new-site', $dir, '--name=PHPUnit Site']);
		ob_end_clean();

		try {
			$this->assertSame(0, $exit);
			$this->assertFileExists($dir . DIRECTORY_SEPARATOR . 'composer.json');
			$this->assertFileExists($dir . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php');
			$this->assertFileExists($dir . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'index.php');

			$home = file_get_contents($dir . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'index.php');
			$this->assertIsString($home);
			$this->assertStringContainsString('PHPUnit Site', $home);
		} finally {
			$this->removeTree($dir);
		}
	}

	public function testRefusesNonEmptyTargetWithoutForce(): void
	{
		require_once self::projectRoot() . '/scripts/new-site.php';

		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pure-new-site-refuse-' . bin2hex(random_bytes(4));
		mkdir($dir);
		file_put_contents($dir . DIRECTORY_SEPARATOR . 'occupied.txt', 'x');

		try {
			ob_start();
			$exit = pure_new_site_main(['pure-new-site', $dir]);
			ob_end_clean();
			$this->assertSame(1, $exit);
		} finally {
			$this->removeTree($dir);
		}
	}
}
