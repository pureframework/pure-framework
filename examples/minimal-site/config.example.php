<?php

/**
 * Example constants for a consuming application.
 * Copy to your site config and set real paths.
 */

define('PURE_DB_CONNECTION', 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4');
define('PURE_DB_USERNAME', '');
define('PURE_DB_PASSWORD', '');

define('PURE_LAYOUT_PATH', __DIR__ . '/templates');
define('PURE_HTDOCS_PATH', __DIR__ . '/htdocs');

define('PURE_DB_SQL_PATH', __DIR__ . '/sql');
define('PURE_DB_SQL_CACHE', __DIR__ . '/includes/dbGeneratedClasses.php');
