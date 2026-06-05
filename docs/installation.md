# Installation

## Requirements

- PHP 8.1 or later
- Composer 2.x
- PDO extension (for database features)

## Install via Composer

Published on [Packagist](https://packagist.org/packages/pureframework/pure-framework). New tags and pushes to `main` on GitHub update Packagist automatically via the GitHub webhook — no manual sync after the package is registered.

```bash
composer require pureframework/pure-framework:^1.3
```

## Local path repository (development)

In your application's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../pure-framework",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "pureframework/pure-framework": "@dev"
  }
}
```

Then run `composer update pureframework/pure-framework`.

## Bootstrap

Load the Composer autoloader once from your application entry point (for example `init.php`):

```php
require __DIR__ . '/vendor/autoload.php';
```

All framework classes are available under the `PureFramework\` namespace:

```php
use PureFramework\Router;
use PureFramework\DB;
use PureFramework\Form;
use PureFramework\Layout;
use PureFramework\Util;

// Optional template helper (autoloaded from src/helpers.php):
use function PureFramework\html;
```

`html()` is a thin alias for `Util::html()` — safe output in `.tpl.php` files.

`__()` is a thin alias for `Phrase::get()` — optional phrase lookup. See **[Phrase catalog](phrase.md)** for bootstrap, phrase files, and i18n.

```php
use function PureFramework\html;
use function PureFramework\__;
```

## IDE support

When developing an application that uses Pure Framework, add the package stub file for `$REQUEST`, `$ROUTE`, and `$HANDLER` type hints. If you depend on the library as a path repo, reference:

```json
"autoload-dev": {
  "files": [
    "vendor/pureframework/pure-framework/stubs/globals.stub.php"
  ]
}
```

Or add `stubs/globals.stub.php` from this repository to your IDE include path. The stub documents `$REQUEST`, `$ROUTE`, and `$HANDLER` set by the router during handler execution.

## Scaffold a new site

```bash
vendor/bin/pure-new-site /path/to/my-app --name="My App"
cd /path/to/my-app
composer install
```

Creates `composer.json`, `init.php`, `config.php`, `includes/db.php`, layout templates, and `htdocs/index.php` with a sample route. Use `--force` to overwrite scaffold files in an existing directory.

## Verify install

```bash
cd vendor/pureframework/pure-framework
composer test
```

Runs PHPUnit (`phpunit.xml.dist`, tests under `tests/Unit/`).

See [Getting started](getting-started.md) for wiring a minimal site.

## Releases (maintainers)

Packagist mirrors this repository through a **GitHub webhook**. To ship a new version:

1. Ensure `main` is green (GitHub Actions runs `composer test`).
2. Update `CHANGELOG.md`.
3. Tag and push:

   ```bash
   git tag -a v1.3.1 -m "Brief release summary"
   git push origin v1.3.1
   ```

4. Packagist picks up the tag within minutes. Optionally draft a [GitHub Release](https://github.com/pureframework/pure-framework/releases) from the same tag.

Consumers install stable versions with `^1.3` (or a specific tag). `dev-main` resolves to `1.3.x-dev` via Composer branch alias.
