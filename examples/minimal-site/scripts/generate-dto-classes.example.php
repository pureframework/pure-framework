<?php

declare(strict_types=1);

/**
 * Regenerate DTO row classes from sql/*.sql.
 *
 * Copy to your project as scripts/generate-dto-classes.php and adjust the constants.
 * Requires vendor/autoload.php (composer install).
 *
 * Usage:
 *   php scripts/generate-dto-classes.php
 *   composer generate-dto
 *
 * See docs/database.md — "Application script (scripts/generate-dto-classes.php)".
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use PureFramework\DbGenerateClasses;

/** `cache` = option A (one file + require_once). `psr4` = option B (per-file + Composer autoload). */
const DTO_LAYOUT = 'cache';

const DTO_SQL_PATH = __DIR__ . '/../sql';
const DTO_TYPED    = true;

// Option A — single cache file
const DTO_CACHE_FILE = __DIR__ . '/../includes/dbGeneratedClasses.php';

// Option B — one namespaced file per table (used when DTO_LAYOUT === 'psr4')
const DTO_OUTPUT_DIR = __DIR__ . '/../includes/entity/dto';
const DTO_NAMESPACE  = 'App\\Entity\\DTO';

if (DTO_LAYOUT === 'cache') {
    $generated = DbGenerateClasses::generateFromPath(
        DTO_SQL_PATH,
        DTO_CACHE_FILE,
        true,
        DTO_TYPED,
    );

    if ($generated === []) {
        echo "No classes generated.\n";
        exit(0);
    }

    echo 'Wrote ' . DTO_CACHE_FILE . "\n";
    foreach ($generated as $table) {
        echo "  - {$table}\n";
    }

    exit(0);
}

if (DTO_LAYOUT === 'psr4') {
    $generated = DbGenerateClasses::generateToDirectory(
        DTO_SQL_PATH,
        DTO_OUTPUT_DIR,
        DTO_NAMESPACE,
        true,
        DTO_TYPED,
    );

    if ($generated === []) {
        echo "No classes generated.\n";
        exit(0);
    }

    echo 'Wrote ' . count($generated) . ' file(s) to ' . DTO_OUTPUT_DIR . "\n";
    foreach ($generated as $table) {
        echo "  - {$table}.php\n";
    }

    echo "Run composer dump-autoload if PSR-4 paths changed.\n";
    exit(0);
}

fwrite(STDERR, "Unknown DTO_LAYOUT: " . DTO_LAYOUT . " (use 'cache' or 'psr4')\n");
exit(1);
