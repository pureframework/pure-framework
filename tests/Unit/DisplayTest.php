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

use PureFramework\Display;
use PureFramework\Tests\TestCase;
use PureFramework\Util;

final class DisplayTest extends TestCase
{
	private string $cwdBackup = '';

	protected function setUp(): void
	{
		parent::setUp();
		$this->cwdBackup = getcwd() ?: '';
		$fixtureRoot = $this->fixturePath('display');
		Display::configure($fixtureRoot . DIRECTORY_SEPARATOR . 'templates', $fixtureRoot);
	}

	protected function tearDown(): void
	{
		Display::reset();
		if ($this->cwdBackup !== '') {
			chdir($this->cwdBackup);
		}
		parent::tearDown();
	}

	public function testConfigureExists(): void
	{
		$this->assertTrue(method_exists(Display::class, 'configure'));
	}

	public function testFetchTemplateAndPartial(): void
	{
		$fixtureRoot = $this->fixturePath('display');
		$bare = Display::fetchTemplate($fixtureRoot . DIRECTORY_SEPARATOR . 'bare.tpl.php', ['value' => 'test']);
		$this->assertStringContainsString('Bare test', $bare);

		$partial = Display::fetchPartial('test-partial', ['label' => 'PART']);
		$this->assertStringContainsString('PART', $partial);
	}

	public function testPageRendersLayout(): void
	{
		chdir($this->fixturePath('display'));
		ob_start();
		Display::page('content.tpl.php', [
			'page' => 'home',
			'title' => 'Home Page',
			'name' => 'Pure',
		]);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('site-header', $html);
		$this->assertStringContainsString('Hello Pure', $html);
		$this->assertStringContainsString('Home Page', $html);
	}

	public function testResolve404HandlerFile(): void
	{
		$resolve404 = new \ReflectionMethod(Display::class, 'resolve404HandlerFile');
		$resolve404->setAccessible(true);

		$resolveBase = Util::path($this->fixturePath('display-resolve'));
		$resolveHtdocs = Util::path($resolveBase, 'htdocs');
		$resolveWalk404 = Util::path($resolveBase, 'walk', '404.php');
		$resolveHtdocs404 = Util::path($resolveHtdocs, '404.php');
		Display::configure(null, $resolveHtdocs);

		$cwdBackup = getcwd() ?: '';
		chdir(Util::path($resolveBase, 'walk', 'sub'));
		$closest = $resolve404->invoke(null, null);
		chdir($cwdBackup);
		$this->assertSame(realpath($resolveWalk404), realpath((string) $closest));

		chdir(Util::path($resolveBase, 'leaf'));
		$htdocsFile = $resolve404->invoke(null, null);
		chdir($cwdBackup);
		$this->assertSame(realpath($resolveHtdocs404), realpath((string) $htdocsFile));
	}

	public function testNotFoundChdirsBeforeRequiringHandler(): void
	{
		$handler404 = Util::path($this->fixturePath('display-notfound-chdir'), '404.php');
		$awayCwd = $this->fixturePath('display');
		$bootstrap = Util::path(self::projectRoot(), 'tests', 'bootstrap.php');
		$script = Util::path(sys_get_temp_dir(), 'pure-display-notfound-test-' . uniqid('', true) . '.php');
		file_put_contents(
			$script,
			<<<PHP
<?php
require '{$bootstrap}';
chdir('{$awayCwd}');
\PureFramework\Display::notFound('{$handler404}');
PHP
		);

		$output = [];
		$exitCode = 0;
		exec(PHP_BINARY . ' ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);
		unlink($script);

		$this->assertSame(0, $exitCode);
		$this->assertSame('NOTFOUND-CHDIR-OK', implode("\n", $output));
	}
}
