# Getting started

This guide walks through the smallest useful application layout. Pure Framework ships **library code only**; your site provides handlers, templates, entities, and configuration.

## Recommended application layout

```text
my-app/
├── composer.json
├── vendor/
├── init.php                 # require autoload; load config
├── config.php               # PURE_* constants
├── includes/
│   ├── db.php               # class DB extends \PureFramework\DB
│   └── entities/
├── sql/                     # CREATE TABLE files (optional)
├── templates/
│   ├── site.layout.php
│   ├── site.header.php
│   ├── site.footer.php
│   └── partials/
└── htdocs/
    ├── index.php            # front controller
    └── home/
        ├── index.php        # handler
        └── list.tpl.php     # co-located template (optional)
```

## 1. Install the package

```bash
composer require pureframework/pure-framework
```

Or scaffold a new project layout (from the package repo or after `composer require`):

```bash
vendor/bin/pure-new-site ../my-app --name="My App"
cd ../my-app && composer install
```

This creates `init.php`, `config.php`, `includes/db.php`, `templates/`, and `htdocs/` with a sample home route. See [Example site layout](example-site.md) for full conventions (SQL, handlers, `.htaccess`, entity layout).

## 2. Define constants

Copy [`examples/minimal-site/config.example.php`](../examples/minimal-site/config.example.php) into your app and adjust paths. See [Configuration](configuration.md) for every constant.

Bootstrap the session in `init.php` (see [`examples/minimal-site/init.example.php`](../examples/minimal-site/init.example.php)):

```php
use PureFramework\Session;

if (!defined('NO_SESSION')) {
    Session::configureCookieParams(['httponly' => true, 'samesite' => 'Lax']);
    Session::start();
}
```

CLI scripts: `define('NO_SESSION', true)` before requiring `init.php`. Details: [Session](session.md).

## 3. Subclass the database

```php
// includes/db.php
class DB extends \PureFramework\DB
{
    use \PureFramework\UuidDbTrait;

    public static function log($msg)
    {
        if (APP_ENV === 'development') {
            error_log($msg);
        }
    }
}
```

See [Database](database.md) for transactions, codegen, and custom encode/decode overrides.

## 4. Front controller

Define `PURE_LAYOUT_PATH` and optional `PURE_HTDOCS_PATH` in `config.php` before handlers run.

```php
// htdocs/index.php
require dirname(__DIR__) . '/init.php';

$router = new \PureFramework\Router();

$router->route('^/$', 'home/index.php');

$router->run(); // no match → Display::notFound() (closest 404.php on cwd, then htdocs root)
// Optional: $router->setNotFoundHandler('404.php');
```

## 5. Handler

Handlers receive `$REQUEST` (route params, query, body) and usually render HTML through **`Display`**:

```php
// htdocs/home/index.php
use PureFramework\Display;

Display::page('list.tpl.php', [
    'title' => 'Home',
    'items' => $items,
]);
```

In templates, escape user-facing text with `Util::html()` or `html()` — see [Templates and layout](templates-and-layout.md).

Other common `Display` calls:

```php
Display::redirect('/login');           // after POST
Display::notFound();                   // in 404.php handler
Display::partial('nav-primary');       // optional; usually header/layout handles nav
```

Content templates can push vars to the layout shell with `$this->set('pageTitle', 'Home')`. See [Templates and layout](templates-and-layout.md).

Register POST routes **before** GET routes when both match similar paths.

## 6. Generate row classes (optional)

If you keep SQL schemas in `sql/`, add **`scripts/generate-dto-classes.php`** (copy from [`examples/minimal-site/scripts/generate-dto-classes.example.php`](../examples/minimal-site/scripts/generate-dto-classes.example.php)), set constants for your layout, then:

```bash
composer generate-dto
# or: php scripts/generate-dto-classes.php
```

**Option A (default)** — one cache file + `require_once` in `includes/index.php`.

**Option B** — one file per table + Composer PSR-4 (`DTO_LAYOUT = 'psr4'` in the script).

Ad-hoc CLI (without an app script):

```bash
vendor/bin/pure-generate-classes /path/to/sql /path/to/includes/dbGeneratedClasses.php [--typed]
```

See [Database — row class generation](database.md#row-class-generation) and [Application script](database.md#application-script-scriptsgenerate-dto-classesphp) for autoload mappings, typed output, and usage in entity code.

## Next steps

- [Example site layout](example-site.md) — full project conventions for agents and new sites
- [Architecture](architecture.md) — how a request flows through the stack
- [Templates and layout](templates-and-layout.md) — full `Display` API and layout conventions
- [Router](router.md) — regex routes and URL parameters
- [Forms and validation](forms-and-validation.md) — forms, constraints, POST handler walkthrough
- [Session](session.md) — cookies, lazy start, destroy, regenerate
- [Phrase](phrase.md) — optional message catalog and `__()`
- [Router](router.md#uuid-resource-routes-application-pattern) — repeating six routes per UUID resource
