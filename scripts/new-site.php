<?php

/**
 * Pure Framework
 *
 * Scaffold a minimal Pure Framework application.
 * Usage: php scripts/new-site.php <target-directory> [--force] [--name="Site Name"]
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

function pure_new_site_main(array $argv): int
{
	$options = parse_new_site_argv($argv);
	if ($options === null) {
		print_new_site_usage();
		return 1;
	}

	$target = $options['target'];
	$force = $options['force'];
	$name = $options['name'];

	if (is_file($target) || (is_dir($target) && !is_dir_empty_or_missing($target) && !file_exists($target . DIRECTORY_SEPARATOR . '.pure-new-site-ok') && !site_looks_empty($target))) {
		if (!$force) {
			fwrite(STDERR, "Target already exists and is not empty: {$target}\n");
			fwrite(STDERR, "Use --force to write files anyway (existing files are skipped).\n");
			return 1;
		}
	}

	if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
		fwrite(STDERR, "Could not create directory: {$target}\n");
		return 1;
	}

	$target = realpath($target) ?: $target;
	$files = new_site_file_map($name);
	$written = 0;
	$skipped = 0;

	foreach ($files as $relativePath => $content) {
		$fullPath = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
		$dir = dirname($fullPath);
		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			fwrite(STDERR, "Could not create directory: {$dir}\n");
			return 1;
		}

		if (is_file($fullPath) && !$force) {
			$skipped++;
			continue;
		}

		if (file_put_contents($fullPath, $content) === false) {
			fwrite(STDERR, "Could not write: {$fullPath}\n");
			return 1;
		}
		$written++;
	}

	$marker = $target . DIRECTORY_SEPARATOR . '.pure-new-site-ok';
	file_put_contents($marker, date('c') . "\n");

	echo "Scaffolded Pure Framework site at: {$target}\n";
	echo "  Files written: {$written}\n";
	if ($skipped > 0) {
		echo "  Files skipped (already exist): {$skipped}\n";
	}
	echo "\nNext steps:\n";
	echo "  cd {$target}\n";
	echo "  composer install\n";
	echo "  Edit config.php (database credentials)\n";
	echo "  Point your web server document root at htdocs/\n";

	return 0;
}

function parse_new_site_argv(array $argv): ?array
{
	$args = array_slice($argv, 1);
	$target = null;
	$force = false;
	$name = 'My App';

	foreach ($args as $arg) {
		if ($arg === '--force') {
			$force = true;
			continue;
		}
		if (str_starts_with($arg, '--name=')) {
			$name = substr($arg, 7);
			continue;
		}
		if ($arg === '--help' || $arg === '-h') {
			return null;
		}
		if (str_starts_with($arg, '-')) {
			fwrite(STDERR, "Unknown option: {$arg}\n");
			return null;
		}
		if ($target !== null) {
			fwrite(STDERR, "Unexpected argument: {$arg}\n");
			return null;
		}
		$target = $arg;
	}

	if ($target === null) {
		return null;
	}

	return [
		'target' => $target,
		'force' => $force,
		'name' => $name,
	];
}

function print_new_site_usage(): void
{
	$script = basename(__FILE__);
	fwrite(STDERR, <<<TXT
Usage: php {$script} <target-directory> [--force] [--name="Site Name"]

Creates a minimal application layout (composer.json, init.php, config.php,
includes/db.php, templates/, htdocs/ with router and sample home handler).

Options:
  --force   Overwrite files that the scaffold provides (skips only when --force is off)
  --name    Site title used in the sample home page (default: My App)

TXT);
}

function is_dir_empty_or_missing(string $path): bool
{
	if (!is_dir($path)) {
		return true;
	}
	$entries = scandir($path);
	if ($entries === false) {
		return true;
	}
	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		return false;
	}
	return true;
}

function site_looks_empty(string $path): bool
{
	$allowed = ['.git', '.pure-new-site-ok'];
	$entries = scandir($path);
	if ($entries === false) {
		return true;
	}
	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		if (!in_array($entry, $allowed, true)) {
			return false;
		}
	}
	return true;
}

/**
 * @return array<string, string> relative path => file contents
 */
function new_site_file_map(string $siteName): array
{
	$siteNameEscaped = addslashes($siteName);

	return [
		'.gitignore' => <<<'GIT'
/vendor/

GIT,
		'composer.json' => <<<'JSON'
{
  "name": "my-org/my-app",
  "description": "Application built with Pure Framework",
  "type": "project",
  "require": {
    "php": ">=8.1",
    "pureframework/pure-framework": "^1.2"
  },
  "autoload-dev": {
    "files": [
      "vendor/pureframework/pure-framework/stubs/globals.stub.php"
    ]
  },
  "minimum-stability": "stable"
}

JSON,
		'init.php' => <<<'PHP'
<?php

use PureFramework\Session;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

if (!defined('NO_SESSION')) {
	Session::configureCookieParams([
		'lifetime' => 0,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	Session::start();
}

PHP,
		'config.php' => <<<'PHP'
<?php

define('APP_ENV', 'development');

define('PURE_DB_CONNECTION', 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4');
define('PURE_DB_USERNAME', '');
define('PURE_DB_PASSWORD', '');

define('PURE_LAYOUT_PATH', __DIR__ . '/templates');
define('PURE_HTDOCS_PATH', __DIR__ . '/htdocs');

define('PURE_DB_SQL_PATH', __DIR__ . '/sql');
define('PURE_DB_SQL_CACHE', __DIR__ . '/includes/dbGeneratedClasses.php');

PHP,
		'includes/db.php' => <<<'PHP'
<?php

class DB extends \PureFramework\DB
{
	use \PureFramework\UuidDbTrait;

	public static function log($msg): void
	{
		if (defined('APP_ENV') && APP_ENV === 'development') {
			error_log($msg);
		}
	}
}

PHP,
		'includes/entities/.gitkeep' => '',
		'sql/.gitkeep' => '',
		'templates/site.layout.php' => <<<'PHP'
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo html($title ?? 'Site'); ?></title>
</head>
<body>
<?php echo $header; ?>
<main id="site-content"><?php echo $content; ?></main>
<?php echo $footer; ?>
</body>
</html>

PHP,
		'templates/site.header.php' => <<<PHP
<header id="site-header">
	<strong>{$siteNameEscaped}</strong>
</header>

PHP,
		'templates/site.footer.php' => <<<'PHP'
<footer id="site-footer">
	<small>Pure Framework</small>
</footer>

PHP,
		'templates/partials/.gitkeep' => '',
		'htdocs/index.php' => <<<'PHP'
<?php

require dirname(__DIR__) . '/init.php';

$router = new \PureFramework\Router();

$router->route('^/$', 'home/index.php');

$router->run();

PHP,
		'htdocs/404.php' => <<<'PHP'
<?php

http_response_code(404);

use PureFramework\Display;

Display::page('404.tpl.php', [
	'title' => 'Page not found',
]);

PHP,
		'htdocs/home/index.php' => <<<PHP
<?php

use PureFramework\Display;

Display::page('list.tpl.php', [
	'title' => '{$siteNameEscaped}',
	'name' => 'Pure Framework',
]);

PHP,
		'htdocs/home/list.tpl.php' => <<<'PHP'
<?php $this->set('pageTitle', $title ?? 'Home'); ?>
<p>Welcome to <strong><?php echo html($name ?? 'the site'); ?></strong>.</p>

PHP,
		'htdocs/404.tpl.php' => <<<'PHP'
<?php $this->set('pageTitle', $title ?? 'Not found'); ?>
<p>The page you requested was not found.</p>

PHP,
	];
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
	exit(pure_new_site_main($argv));
}
