<?php

/**
 * Example application bootstrap. Copy to init.php and adjust.
 *
 * CLI scripts: define('NO_SESSION', true) before requiring this file.
 */

use PureFramework\Session;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

if (!defined('NO_SESSION')) {
	$secure = APP_ENV === 'production';

	Session::configureCookieParams([
		'lifetime' => 0,
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);

	// Staff app: Session::start();
	// Public site: Session::start(lazy: true);
	Session::start();
}
