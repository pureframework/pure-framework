# Configuration

Pure Framework does not load `.env` files or ship site bootstrap code. Your application defines **constants** and optional **class `configure()` overrides** before handlers run.

## Two configuration styles

| Style | When to use | Examples |
|-------|-------------|----------|
| **`PURE_*` constants** | Required app wiring: database, layout paths, SQL codegen | `PURE_DB_CONNECTION`, `PURE_LAYOUT_PATH` |
| **`Class::configure()`** | Optional tuning or **tests** when constants are inconvenient | `Display::configure()`, `Csrf::configure()` |

**Rule of thumb:** production `config.php` sets `PURE_*` constants once. Call `configure()` only when you need non-default names (CSRF) or path overrides (tests). Call `reset()` in tests after `configure()` to restore defaults.

Application-only settings (environment name, auth, session cookies, error display) live in **your** `init.php` / `config.php` — not in the framework. See [Security](security.md).

## Bootstrap order (recommended)

```php
// init.php
use PureFramework\Session;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';   // define PURE_* and APP_ENV

if (!defined('NO_SESSION')) {
    Session::configureCookieParams([/* ... */]);
    Session::start(); // or start(lazy: true) on public sites
}

// Optional: Csrf::configure(...) only if defaults are not suitable

// htdocs/index.php
require dirname(__DIR__) . '/init.php';

$router = new \PureFramework\Router();
// routes...
$router->run(); // unmatched URLs → Display::notFound() unless you override setNotFoundHandler()
```

Define **`PURE_LAYOUT_PATH`** (and database constants if the request uses `DB`) before any `Display` or `DB` call.

## Database constants

| Constant | Required | Description |
|----------|----------|-------------|
| `PURE_DB_CONNECTION` | When using `DB` | PDO DSN, e.g. `mysql:host=127.0.0.1;dbname=app;charset=utf8mb4` |
| `PURE_DB_USERNAME` | When using `DB` | Database username |
| `PURE_DB_PASSWORD` | When using `DB` | Database password |

Used by `PureFramework\DB::connection()`. Subclass `DB` in the application for logging and UUID handling — see [Database](database.md).

## Layout and display paths

| Constant | Required | Description |
|----------|----------|-------------|
| `PURE_LAYOUT_PATH` | For HTML via `Display` | Directory with `site.layout.php`, `site.header.php`, `site.footer.php`, `partials/` |
| `PURE_HTDOCS_PATH` | No | Helps `Display::notFound()` find `htdocs/404.php` |

| API | Purpose |
|-----|---------|
| `Display::configure($layoutPath, $htdocsPath)` | Override paths without constants (tests) |
| `Display::reset()` | Clear overrides |

Without `PURE_LAYOUT_PATH` or `configure()`, `Display::page()` and related methods throw. Details: [Templates and layout](templates-and-layout.md).

## Code generation constants

| Constant | Required | Description |
|----------|----------|-------------|
| `PURE_DB_SQL_PATH` | For codegen | Directory of `.sql` `CREATE TABLE` files |
| `PURE_DB_SQL_CACHE` | Option A codegen | Output PHP cache file (single-file mode). Option B uses `--output-dir` instead — [database.md](database.md#row-class-generation) |

Used by `vendor/bin/pure-generate-classes` when run without CLI arguments (option A cache path).

## CSRF (optional)

No constants. Defaults work without setup.

| API | Purpose |
|-----|---------|
| `Csrf::configure($sessionKey, $fieldName, $headerName)` | Override session key, form field, or header name |
| `Csrf::reset()` | Restore defaults (tests) |

Requires an active session before `Csrf::token()` or `verify()` — use `Session::start()` in `init.php`. See [CSRF](csrf.md).

## Session (optional)

| API | Purpose |
|-----|---------|
| `Session::configureCookieParams($options)` | Cookie defaults before `start()` |
| `Session::configureGc($maxLifetime, ...)` | Align GC with cookie lifetime |
| `Session::start($lazy)` | Eager or lazy (cookie-only) start |
| `Session::destroy($clearData)` | End session |
| `Session::regenerate(...)` | Post-login ID rotation + optional CSRF |

## Phrase catalog (optional)

No constants. Register phrases in application bootstrap.

| API | Purpose |
|-----|---------|
| `Phrase::load($file, $language)` | Merge phrases from a PHP array file |
| `Phrase::add($key, $phrase, $language)` | Register one string |
| `Phrase::setLanguage($language)` | Default language for `get()` / `__()` |
| `__($key, $params, $language)` | Template helper — [phrase.md](phrase.md) |

## Example constants file

[`examples/minimal-site/config.example.php`](../examples/minimal-site/config.example.php) · [`init.example.php`](../examples/minimal-site/init.example.php) · [`htdocs.htaccess.example`](../examples/minimal-site/htdocs.htaccess.example) · [`scripts/generate-dto-classes.example.php`](../examples/minimal-site/scripts/generate-dto-classes.example.php)

Full layout and naming conventions: [example-site.md](example-site.md).

## Extension points (application code)

| Concern | Where it lives |
|---------|----------------|
| UUID pack/unpack | `use PureFramework\UuidDbTrait` on application `DB` — [database.md](database.md) |
| Error logging | Subclass `DB::log()` |
| Authentication | Application `includes/auth.php` and `$_SESSION` keys — [session.md](session.md) |
| CSRF | `PureFramework\Csrf` — [csrf.md](csrf.md) |
| Session bootstrap | `PureFramework\Session` — [session.md](session.md) |
| Phrase catalog | `PureFramework\Phrase` in bootstrap — [phrase.md](phrase.md) |
| HTTPS / errors | Application `init.php` — [security.md](security.md) |
| Route guards | Top of handlers or before `$router->run()` — no route middleware |
| Entity logic | `includes/entities/*.php` |
| DTO codegen | `scripts/generate-dto-classes.php` — [database.md](database.md#application-script-scriptsgenerate-dto-classesphp) |
| Constraint types | Classes extending `ConstraintType` |
| JSON API responses | `SuccessResponse` / `ErrorResponse` + `HttpResponse::json()` — [responses.md](responses.md) |
| HTML escaping in templates | `Util::html()` / `html()` — [templates and layout](templates-and-layout.md) |

## Globals at runtime

| Global | Set by | Purpose |
|--------|--------|---------|
| `$REQUEST` | Router | Current `PureFramework\Request` in handlers |
| `$ROUTE` | Router | Matched `Route`, or `null` in not-found handler |
| `$HANDLER` | Router | Handler file path, or `null` for callable not-found |

IDE stubs: [`stubs/globals.stub.php`](../stubs/globals.stub.php) defines `$REQUEST`, `$ROUTE`, and `$HANDLER` for autocomplete and static analysis.
