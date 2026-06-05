# Example site layout and conventions

Reference for **application** structure when building a site on Pure Framework. The package ships library code only; your repo provides handlers, SQL, templates, and configuration.

Use this guide when scaffolding manually, reviewing a new project, or instructing an agent to add features consistently.

Related: [Getting started](getting-started.md) · [Configuration](configuration.md) · [Architecture](architecture.md) · [Router](router.md) · [Database](database.md) · [Templates and layout](templates-and-layout.md) · [Security](security.md)

## Quick start

```bash
composer require pureframework/pure-framework
vendor/bin/pure-new-site /path/to/my-app --name="My App"
cd /path/to/my-app
composer install
```

Then author site-specific files described below. `pure-new-site` creates a minimal tree; production sites add `includes/index.php`, `.htaccess`, entity files, and more routes.

Point the **web server document root** at `htdocs/` — not the project root.

---

## Directory layout

```text
my-app/
├── composer.json              # require pureframework/pure-framework; IDE stubs
├── vendor/
├── init.php                   # autoload, config, session (web bootstrap)
├── config.php                 # PURE_* constants, APP_ENV, app settings
├── _env.php                   # optional: local secrets (gitignored)
├── includes/
│   ├── index.php              # require db, auth, entities (optional aggregator)
│   ├── db.php                 # class DB extends \PureFramework\DB
│   ├── auth.php               # optional: Session guards, login helpers
│   ├── dbGeneratedClasses.php # generated from sql/ (do not hand-edit)
│   └── entities/
│       ├── index.php          # optional: require all entity files
│       ├── user.php           # procedural domain functions
│       └── ...
├── sql/
│   ├── 00_database.sql        # optional: CREATE DATABASE / charset
│   ├── user.sql               # one CREATE TABLE per file
│   └── examples/              # optional: seed / fixture SQL
├── lang/                      # optional: Phrase::load() files
│   └── phrases.en.php
├── scripts/                   # CLI: define NO_SESSION before init when using init.php
│   ├── setup-db.php
│   └── generate-dto-classes.php  # DTO codegen (vendor/autoload only — see database.md)
├── templates/
│   ├── site.layout.php        # HTML shell
│   ├── site.header.php
│   ├── site.footer.php
│   ├── site.layout-admin.php  # optional layout variant
│   └── partials/              # reusable fragments (nav, form-errors)
└── htdocs/                    # document root
    ├── .htaccess              # rewrite to index.php (see below)
    ├── index.php              # front controller: routes only
    ├── 404.php                # not-found handler
    ├── 404.tpl.php            # co-located or use Display from 404.php
    ├── assets/                # css, js, images (served directly)
    │   └── styles.css
    └── {resource}/            # URL segment mirrors directory name
        ├── index.php          # list handler
        ├── list.tpl.php
        ├── details/
        │   ├── index.php      # read handler
        │   └── view.tpl.php
        └── edit/
            ├── index.php      # new / edit / POST create / POST update
            └── form.tpl.php
```

### What stays outside `htdocs`

| Path | Reason |
|------|--------|
| `vendor/`, `includes/`, `sql/`, `templates/`, `config.php`, `init.php` | Not web-accessible; loaded via `require` |
| Generated row classes | Application logic, not public |
| CLI scripts under `scripts/` | Run from shell with `NO_SESSION` |

Never place secrets in `htdocs/`.

---

## Files to author when setting up a new site

### 1. `composer.json`

- `"require": { "pureframework/pure-framework": "^1.2", "php": ">=8.1" }`
- `"autoload-dev"` → `vendor/pureframework/pure-framework/stubs/globals.stub.php` for `$REQUEST`, `$ROUTE`, `$HANDLER` IDE hints

**Option A (default):** no application `autoload` section required — load app code via `require_once` in `includes/index.php`.

**Option B (PSR-4):** add `"autoload"` mappings for generated DTOs and optional class-based entities:

```json
"autoload": {
  "psr-4": {
    "App\\Entity\\DTO\\": "includes/entity/dto/",
    "App\\Entity\\": "includes/entity/"
  }
}
```

See [DTO loading: option A vs option B](#dto-loading-option-a-vs-option-b) and [Database — row class generation](database.md#row-class-generation).

For local framework development, use a path repository (see [Installation](installation.md)).

### 2. `config.php`

Define framework constants and application settings **before** any handler runs.

**Required for HTML + DB sites:**

```php
<?php

define('APP_ENV', 'development'); // development | staging | production

define('PURE_DB_CONNECTION', 'mysql:host=127.0.0.1;dbname=my_app;charset=utf8mb4');
define('PURE_DB_USERNAME', 'app');
define('PURE_DB_PASSWORD', 'secret');

define('PURE_LAYOUT_PATH', __DIR__ . '/templates');
define('PURE_HTDOCS_PATH', __DIR__ . '/htdocs');

define('PURE_DB_SQL_PATH', __DIR__ . '/sql');
define('PURE_DB_SQL_CACHE', __DIR__ . '/includes/dbGeneratedClasses.php');
```

**Optional application constants** (your naming; not read by the framework):

| Constant | Typical use |
|----------|-------------|
| `APP_ERROR_LOG` | Path to application error log |
| `APP_OFFLINE` | Maintenance mode flag |
| `PHP_SESSION_TTL` | Cookie lifetime when using persistent sessions |
| Domain-specific paths | Email templates, upload dirs, feature flags |

Split large apps into `config/*.php` included from `config.php` (e.g. `config/mail.php`, `config/product.php`).

**Secrets:** keep credentials out of git. Pattern:

```php
// config.php
if (is_file(__DIR__ . '/_env.php')) {
    require __DIR__ . '/_env.php';
}
// _env.php defines PURE_DB_PASSWORD, etc.
```

### 3. `init.php`

Single web bootstrap entry. Loaded from `htdocs/index.php` and optionally from CLI scripts.

```php
<?php

use PureFramework\Phrase;
use PureFramework\Session;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

if (!defined('NO_SESSION')) {
    $secure = defined('APP_ENV') && APP_ENV === 'production';

    Session::configureCookieParams([
        'lifetime' => 0,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Staff / admin: Session::start();
    // Public marketing site: Session::start(lazy: true);
    Session::start();
}

// Optional phrase catalog
// Phrase::load(__DIR__ . '/lang/phrases.en.php');

// Optional: load application includes for every request
// require __DIR__ . '/includes/index.php';
```

**CLI scripts** must define `NO_SESSION` before requiring `init.php`:

```php
define('NO_SESSION', true);
require dirname(__DIR__) . '/init.php';
```

See [Session](session.md) and [Security](security.md).

### 4. `includes/db.php`

```php
<?php

class DB extends \PureFramework\DB
{
    use \PureFramework\UuidDbTrait; // when *_uuid columns are BINARY(16)

    public static function log($msg): void
    {
        if (defined('APP_ENV') && APP_ENV === 'development') {
            error_log($msg);
        }
        // production: write to APP_ERROR_LOG or monitoring — never echo to browser
    }
}
```

Handlers that use the database must load this class before `DB::` calls — typically via `includes/index.php` or `require_once` in entity files.

### 5. `includes/index.php` (recommended)

Central `require_once` list so handlers stay thin:

```php
<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';           // optional
require_once __DIR__ . '/entities/index.php';
```

Include from `init.php` when most requests need entities, or require from individual handlers when you prefer explicit dependencies.

### 6. `htdocs/index.php`

**Only** bootstrap + route table + `run()`. No business logic.

```php
<?php

require dirname(__DIR__) . '/init.php';

$router = new \PureFramework\Router();

// POST before GET when patterns overlap
$router->route('POST', '^/users$', 'users/edit/index.php');
$router->route('^/users$', 'users/index.php');
$router->route('^/$', 'home/index.php');

$router->run();
```

Register routes in **match order**: specific paths and POST routes before broad GET patterns. See [Router — UUID resource routes](router.md#uuid-resource-routes-application-pattern).

Optional: `$router->setNotFoundHandler('404.php')` when you want router-style `chdir` + include for 404 (default is `Display::notFound()`).

---

## `sql/` directory

### File naming

| Rule | Example |
|------|---------|
| **One table per file** | `user.sql`, `client_purchase.sql` |
| **Filename = table name** | snake_case, matches `CREATE TABLE` name |
| **Ordering prefix** | `{digits}_*.sql` (e.g. `00_database.sql`) for migrations/setup — skipped by codegen when not `CREATE TABLE` |
| **Seed / dev data** | `sql/examples/dev_admin.sql` (subdirectory not scanned) or `{digits}_seed.sql` at top level |

### Table file structure

```sql
-- Brief comment: purpose and dependencies
-- Requires: other_table.sql (if applicable)

DROP TABLE IF EXISTS `user`;

CREATE TABLE IF NOT EXISTS `user` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `user_uuid` BINARY(16) NOT NULL,
    `display_name` VARCHAR(128) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_uuid` (`user_uuid`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

**Conventions:**

- **`{entity}_uuid` as `BINARY(16)`** when using `UuidDbTrait` (public URLs use hyphenated UUID strings; DB stores binary).
- **`created` / `updated`** timestamps on most tables.
- **`deleted_at`** for soft delete when needed.
- **Comments** at top for human readers and agents applying migrations in order.

### Code generation

After SQL files exist, generate DTO row classes. Pure Framework supports **two loading strategies** — see [Database — row class generation](database.md#row-class-generation) for the full comparison.

**Recommended:** add `scripts/generate-dto-classes.php` with your paths, namespace, and `--typed` policy, then run `composer generate-dto`. Template: [`examples/minimal-site/scripts/generate-dto-classes.example.php`](../examples/minimal-site/scripts/generate-dto-classes.example.php). Full guide: [Database — application script](database.md#application-script-scriptsgenerate-dto-classesphp).

**Option A — single cache file (default, used by `pure-new-site` and the example todo app):**

```bash
php scripts/generate-dto-classes.php
# or: vendor/bin/pure-generate-classes ./sql ./includes/dbGeneratedClasses.php [--typed]
```

Load with `require_once __DIR__ . '/dbGeneratedClasses.php'` in `includes/index.php`. Use table name strings in `DB::fetch()` / `DB::objectInsertFactory()`.

**Option B — per-file PSR-4 (class-based entities, larger apps):**

Set `DTO_LAYOUT = 'psr4'` in `scripts/generate-dto-classes.php` (see template), or run:

```bash
vendor/bin/pure-generate-classes ./sql \
  --output-dir=./includes/entity/dto \
  --namespace=App\\Entity\\DTO \
  --typed
```

Configure `"autoload"` in `composer.json` and run `composer dump-autoload`. Use explicit DTO class names or `['table', DTO_CLASS]` tuples in `DB` calls.

Re-run codegen when schemas change. Option A: commit or regenerate the cache file. Option B: commit generated `dto/*.php` files.

**Never** pass request input as a table name to `DB::fetch()` — use constants or generated class names only.

---

## `htdocs/` URL and file naming

### URL ↔ directory mapping

The router matches **path only** (no query string in patterns). Handler paths are **relative to `htdocs/`**.

| Public URL | Handler file |
|------------|--------------|
| `/` | `home/index.php` or route target |
| `/users` | `users/index.php` |
| `/users/new` | `users/edit/index.php` |
| `/users/{uuid}` | `users/details/index.php` |
| `/users/{uuid}/edit` | `users/edit/index.php` |
| `/calendar/day/2026-06-02` | `calendar/day/index.php` with route captures |

Directory names use **lowercase**, **kebab-case** or **single words** (`check-in`, `lesson-planner`). Match the URL segment exactly.

### Handler file names

| File | Role |
|------|------|
| `index.php` | Handler for that URL segment (required entry point) |
| `list.tpl.php` | List page content (used with `Display::page()`) |
| `form.tpl.php` | Create/edit form |
| `view.tpl.php` | Detail/read view |
| `*.tpl.php` | Any co-located content template |

The router **`chdir`s** into the handler’s directory before `include`. Co-locate templates beside the handler so `Display::page('list.tpl.php', $vars)` resolves correctly.

### UUID resource layout (admin CRUD)

For each resource `{handlers}` under `htdocs/` (e.g. `users`):

```text
htdocs/users/
├── index.php           # GET list  → Display::page('list.tpl.php', ...)
├── list.tpl.php
├── details/
│   ├── index.php       # GET one   → Display::page('view.tpl.php', ...)
│   └── view.tpl.php
└── edit/
    ├── index.php       # GET new/edit, POST create/update
    └── form.tpl.php
```

Register six routes per resource (POST before GET). Full table: [Router](router.md#uuid-resource-routes-application-pattern).

In `edit/index.php`:

- Required UUID routes: `$uuid = $REQUEST->uuidParam('user_uuid');` then `if ($uuid === false) { Display::notFound(); }`. **New** record routes omit `user_uuid` from the pattern — use `$REQUEST->hasParam('user_uuid')` before calling `uuidParam()` on edit routes.
- POST: verify CSRF, run `Form`, redirect on success.
- GET: load row or empty form, `Display::page('form.tpl.php', ...)`.

### Static assets

Place under `htdocs/assets/` (or `htdocs/css/`, `htdocs/js/`). Reference from templates as `/assets/styles.css` (root-relative). `.htaccess` must **serve existing files** without rewriting to `index.php`.

### 404 handling

| File | Purpose |
|------|---------|
| `htdocs/404.php` | Handler; often calls `Display::page('404.tpl.php', ...)` |
| `htdocs/404.tpl.php` | User-facing not-found content |
| Section `404.php` | Optional: `htdocs/admin/404.php` found by walking up from cwd ([Templates](templates-and-layout.md)) |

---

## `templates/` directory

| File | Purpose |
|------|---------|
| `site.layout.php` | Outer HTML: `<html>`, `$header`, `$content`, `$footer` |
| `site.header.php` | Nav, branding |
| `site.footer.php` | Footer |
| `site.layout-{variant}.php` | Optional: `Display::page(..., layoutVariant: 'admin')` |
| `partials/{name}.php` | Included via `Display::partial('name')` — no `.tpl.php` suffix |

Layout templates receive merged vars from the content template. Content templates use `$this->set('pageTitle', '…')` to pass data up to the layout ([Templates and layout](templates-and-layout.md)).

Use `html()` on all untrusted text in templates. Layout fragments (`$header`, `$content`) are already HTML — do not double-escape.

---

## DTO loading: option A vs option B

Generated row classes (DTOs) can load in one of two ways. **Option A** is the default for new sites; **option B** suits apps with namespaced class-based entities.

| | Option A: cache file | Option B: PSR-4 per-file |
|--|----------------------|---------------------------|
| Generated output | `includes/dbGeneratedClasses.php` | `includes/entity/dto/{table}.php` |
| Load mechanism | `require_once` in `includes/index.php` | Composer `"autoload"` `"psr-4"` |
| `DB::fetch` target | `'account'` (table name) | `['account', \App\Entity\DTO\account::class]` |
| Hand-written entities | `includes/entities/*.php` procedural functions | `includes/entity/*.php` classes under `App\Entity\` |

### Option A layout (default)

```text
includes/
├── index.php
├── dbGeneratedClasses.php   # generated
└── entities/
    ├── user.php             # function fetch_user_by_uuid(), …
    └── product.php
```

```php
// includes/index.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dbGeneratedClasses.php';
require_once __DIR__ . '/entities/user.php';
```

### Option B layout (PSR-4)

```text
includes/
├── index.php                # optional: auth, constraints only
├── db.php                   # class DB (may live as App\DB with PSR-4)
├── entity/
│   ├── User.php             # class App\Entity\User
│   └── dto/                 # generated
│       ├── user.php         # class App\Entity\DTO\user
│       └── product.php
└── constraints/             # still require_once, or add PSR-4 prefix
```

```json
// composer.json
"autoload": {
  "psr-4": {
    "App\\Entity\\DTO\\": "includes/entity/dto/",
    "App\\Entity\\": "includes/entity/"
  }
}
```

`init.php` loads `vendor/autoload.php` once; generated DTOs and class-based entities resolve without manual `require_once`.

---

## `includes/entities/` (option A) or `includes/entity/` (option B)

### Option A — procedural entities (default)

One PHP file per domain aggregate, **procedural functions** (not a framework ORM):

```text
includes/entities/
├── index.php       # require_once each entity file
├── user.php        # fetch_user_by_uuid(), save_user(), ...
└── product.php
```

**Naming:**

| Kind | Pattern |
|------|---------|
| Fetch one | `get_{entity}_by_uuid(string $uuid)` |
| Fetch list | `fetch_{entities}(...)` |
| Save | `save_{entity}(...)` / `update_{entity}(...)` |
| Delete | `deactivate_{entity}(...)` or `delete_{entity}(...)` |

Return **`SuccessResponse` / `ErrorResponse`** from entity functions that can fail (especially create/update/delete). Use `data` for the primary result and `related` for secondary payloads on both success and error — validation maps, side-loaded rows, pagination, etc. Plain `?object` returns are fine for simple internal lookups if your app documents that choice. Full convention: [responses.md](responses.md).

Constraints live in separate `*Constraint.php` classes extending `ConstraintEntity` ([Forms and validation](forms-and-validation.md)).

Handlers `require_once` entity files (or rely on `includes/index.php`).

### Option B — class-based entities (PSR-4)

Static service classes under `App\Entity\`, with generated DTOs in `App\Entity\DTO\`:

```php
namespace App\Entity;

use App\Entity\DTO\account;
use App\DB;

class Account
{
    public static function create(array $data): SuccessResponse|ErrorResponse
    {
        $obj = DB::objectInsertFactory(account::class, $fields, $data);
        // …
    }

    public static function fetch(string $accountUuid): SuccessResponse|ErrorResponse
    {
        return DB::fetchSingle(['account', account::class], ['account_uuid' => $accountUuid]);
    }
}
```

Use explicit `['table', DTO::class]` tuples when table name and class name differ in casing or namespace. Return types: [responses.md](responses.md).

Procedural option A and class-based option B are both valid — do not mix DTO loading styles in one app.

---

## `.htaccess` (Apache)

Place in **`htdocs/.htaccess`**. `pure-new-site` does not generate this file — add it for every Apache deployment. Copy from [`examples/minimal-site/htdocs.htaccess.example`](../examples/minimal-site/htdocs.htaccess.example).

### Recommended pattern (pretty URLs, block direct `.php` access)

Forces all application URLs through `index.php` while serving CSS/JS/images directly. Prevents users from hitting handler files like `/users/index.php` in the browser.

```apache
Options -MultiViews -Indexes
DirectorySlash Off

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Allowlisted PHP files that must be reachable directly (maintenance, health)
  RewriteRule ^offline\.php$ - [L]

  # Block direct browser requests to other .php files
  RewriteCond %{THE_REQUEST} \s/+[^\s?]*\.php(?:[?\s]|$) [NC]
  RewriteCond %{REQUEST_URI} !^/offline\.php$ [NC]
  RewriteRule ^ - [F,L]

  # Serve existing non-PHP files (assets)
  RewriteCond %{REQUEST_FILENAME} -f
  RewriteCond %{REQUEST_URI} !\.php$ [NC]
  RewriteRule ^ - [L]

  # Everything else → front controller
  RewriteRule ^ index.php [L]
</IfModule>
```

**Requirements:** `mod_rewrite` enabled; document root = `htdocs/`; `AllowOverride All` (or equivalent) so `.htaccess` is honored.

### Simpler pattern (rewrite only)

Use when direct `.php` URLs are acceptable in development:

```apache
Options -MultiViews -Indexes

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_URI} !^/assets/
  RewriteRule ^ index.php [L]
</IfModule>
```

### nginx (equivalent)

No `.htaccess`; configure in the server block:

```nginx
root /var/www/my-app/htdocs;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

Restrict `location ~ \.php$` to `index.php` (and allowlisted files) in production if you mirror the Apache “block direct handlers” policy.

---

## Request flow (agent checklist)

When adding a **new UUID-backed resource** `{name}`:

1. **SQL:** add `sql/{name}.sql` with `{name}_uuid BINARY(16)`.
2. **Codegen:** run `php scripts/generate-dto-classes.php` or `composer generate-dto`.
3. **Entity:** add `includes/entities/{name}.php` with fetch/save/validation.
4. **Handlers:** create `htdocs/{name}/index.php`, `details/index.php`, `edit/index.php` + templates.
5. **Routes:** register six routes in `htdocs/index.php` (POST before GET).
6. **Nav:** update `templates/site.header.php` or partial if needed.
7. **Auth:** call application guards (e.g. `auth_require_logged_in()`) at top of handlers ([Session](session.md)).
8. **POST handlers:** `Csrf::verify($REQUEST)` before mutating state ([CSRF](csrf.md)).
9. **Templates:** `html()` on user-visible values; `Csrf::field()` in forms.

When adding a **one-off page** at `/about`:

1. Create `htdocs/about/index.php` (+ optional `about.tpl.php`).
2. Add `$router->route('^/about$', 'about/index.php');` before catch-all routes.

When adding a **CLI script** under `scripts/`:

1. `define('NO_SESSION', true);` first line after `<?php`.
2. `require dirname(__DIR__) . '/init.php';`
3. Use `DB::` only after `includes/db.php` is loaded.

---

## Environment and production

| Concern | Where |
|---------|--------|
| `APP_ENV === 'production'` | Disable `display_errors`; log server-side ([Security](security.md)) |
| HTTPS | Redirect in `init.php`; `Session` cookie `secure => true` |
| Session | Eager start for staff apps; lazy for public sites ([Session](session.md)) |
| DB errors | Override `DB::log()` — never expose SQL to browsers |
| Offline mode | Optional `htdocs/offline.php` + allowlist in `.htaccess` |

---

## Scaffold vs production site

| Artifact | `pure-new-site` | Production site |
|----------|-----------------|-----------------|
| `init.php`, `config.php`, `includes/db.php` | Yes | Extend |
| `includes/index.php`, `auth.php` | No | Add |
| `htdocs/.htaccess` | No | **Required** (Apache) |
| `includes/entities/*` | Empty dir | Add per domain |
| `sql/*.sql` | Empty dir | Add per table |
| Route table | Home only | Full resource routes |
| `lang/phrases.*.php` | Optional | Optional |

---

## See also

- [Getting started](getting-started.md) — minimal walkthrough
- [`examples/minimal-site/`](../examples/minimal-site/) — config and init samples
- [`scripts/new-site.php`](../scripts/new-site.php) — scaffold source
- [Router](router.md) · [Database](database.md) · [Forms and validation](forms-and-validation.md) · [Session](session.md) · [Phrase](phrase.md)
