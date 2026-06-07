# Database

`PureFramework\DB` provides static PDO helpers: query, select, insert, update, delete, transactions, and SQL construction utilities. Applications **subclass `DB`** and typically **`use UuidDbTrait`** for binary `*_uuid` columns, plus custom `log()` behavior.

## Connection

Define `PURE_DB_CONNECTION`, `PURE_DB_USERNAME`, and `PURE_DB_PASSWORD`, then:

```php
$pdo = DB::connection();
```

The connection is a singleton per request.

## Common operations

```php
$row = DB::fetchSingle('user', ['user_uuid' => $uuid]);
$rows = DB::fetch('user', ['active' => 1]);
$id = DB::insert('user', $object);
DB::update('user', $object, ['user_uuid' => $uuid]);
DB::delete('user', ['user_uuid' => $uuid]);
```

`fetchSingle()` returns `null` when no row exists. `delete()` refuses to run without a WHERE clause.

## Transactions

All writes on the singleton connection share one transaction scope:

```php
DB::beginTransaction();
try {
    DB::insert('order', $order);
    DB::update('inventory', $stock, ['sku' => $sku]);
    DB::commit();
} catch (\Throwable $e) {
    DB::rollback();
    throw $e;
}
```

Convenience wrapper — commits on success, rolls back and re-throws on exception:

```php
$result = DB::transaction(function () {
    DB::insert('order', $order);
    return DB::insert('order_line', $line);
});
```

| Method | Purpose |
|--------|---------|
| `beginTransaction()` | Start a transaction |
| `commit()` | Commit the active transaction |
| `rollback()` | Roll back the active transaction |
| `inTransaction()` | Whether a transaction is open |
| `transaction(callable $callback)` | Run callback inside a transaction |

Returns `false` when the connection is unavailable or PDO rejects the operation. See [Security](security.md) for error-handling guidance in production.

## PDO defaults

On connect, the library sets:

- `ERRMODE_EXCEPTION`
- `FETCH_OBJ` as default fetch mode (`select()` still uses `FETCH_CLASS`)
- `EMULATE_PREPARES` disabled (native prepared statements)

## Subclassing

For sites that store UUIDs as **16-byte binary** in `*_uuid` columns, use the opt-in trait:

```php
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

`UuidDbTrait` overrides `decodeRows()`, `encodeData()`, and `encodeWhereData()` to call `Util::packUuidProperties()` / `unpackUuidProperties()`. Invalid UUID strings on write throw as they do when calling `Util` directly.

Apps without binary UUID columns can subclass `DB` for logging only — no trait required.

To add custom encode/decode behavior, override those methods in your subclass (replacing or extending the trait implementations).

## Utilities

| Method | Purpose |
|--------|---------|
| `objectFactory()` | Build a row object from data; optional explicit UUID column |
| `objectInsertFactory()` | Build a row object for INSERT; honors `$insertSkip`, auto UUID |
| `objectUpdateFactory()` | Build a row object for UPDATE; honors `$updateSkip`, never auto UUID |
| `prepareWhereStatement()` | Build `WHERE` clauses from associative arrays |
| `encodeHexLiteral()` | MySQL `x'...'` literal for binary UUIDs |
| `pagingFor()` | Row/page counts for pagination |

## Row class generation

Generate plain PHP property classes (DTOs) from SQL `CREATE TABLE` files. Pure Framework supports **two ways** to load those classes into your app — pick one and stay consistent across the team.

### Two loading options

| | **Option A: cache file** | **Option B: PSR-4 per-file** |
|--|--------------------------|--------------------------------|
| **Best for** | Small sites, quick start, procedural entities | Larger apps, class-based entities, many tables |
| **Codegen output** | One file: `includes/dbGeneratedClasses.php` | One file per table: `includes/entity/dto/{table}.php` |
| **Class namespace** | None (global `class account`) | e.g. `App\Entity\DTO\account` |
| **How classes load** | `require_once` in `includes/index.php` | Composer PSR-4 autoload |
| **Entity code** | `DB::objectInsertFactory('account', …)` | `DB::objectInsertFactory(\App\Entity\DTO\account::class, …)` |
| **After schema change** | Re-run codegen; commit or regenerate cache | Re-run codegen; `composer dump-autoload` if paths change |

Both options use the same generator (`DbGenerateClasses` / `pure-generate-classes`) and the same optional `--typed` flag. You can switch later, but it requires regenerating output and updating entity code.

**Recommended:** add **`scripts/generate-dto-classes.php`** in your app so paths, namespace, and typed output are defined once — see [Application script](#application-script-scriptsgenerate-dto-classesphp) below. The vendor CLI remains available for ad-hoc runs.

---

### Option A: single cache file (default)

**Generate:**

```bash
vendor/bin/pure-generate-classes ./sql ./includes/dbGeneratedClasses.php
```

**Load** in `includes/index.php`:

```php
require_once __DIR__ . '/dbGeneratedClasses.php';
```

**Use** — class name equals table name:

```php
$row = DB::fetchSingle('account', ['account_uuid' => $uuid]);
$obj = DB::objectInsertFactory('account', [
    'username' => $username,
    'password_hash' => $passwordHash,
]);
```

Example generated class (global namespace):

```php
class account {
  public $id = '';
  public $account_uuid = '';
  public $username = '';
}
```

This is the pattern used by the **example todo app** and `pure-new-site` scaffolds. Re-run codegen when schemas change; commit the cache file or regenerate in CI — pick one policy.

---

### Option B: per-file output with Composer PSR-4

**Generate** one namespaced file per table:

```bash
vendor/bin/pure-generate-classes ./sql \
  --output-dir=./includes/entity/dto \
  --namespace=App\\Entity\\DTO
```

Add `--typed` for docblocks, typed properties, and `$insertSkip` (see below).

**Map namespaces** in `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "includes/",
      "App\\Entity\\": "includes/entity/",
      "App\\Entity\\DTO\\": "includes/entity/dto/"
    }
  }
}
```

Run `composer dump-autoload` after codegen or when adding hand-written entity classes.

**Bootstrap** — `init.php` needs only `vendor/autoload.php`. You do **not** `require_once` generated DTO files. Hand-written class-based entities under `App\Entity\` load automatically when PSR-4 paths match.

Procedural helpers (`includes/auth.php`, `includes/constraints/`) can stay on `require_once` in `includes/index.php`, or you can add more PSR-4 prefixes (e.g. `App\\Constraint\\` → `includes/constraints/`).

**Use** — explicit class reference:

```php
define('ACCOUNT_DTO_CLASS', \App\Entity\DTO\account::class);

$row = DB::fetchSingle(['account', ACCOUNT_DTO_CLASS], ['account_uuid' => $uuid]);
$obj = DB::objectInsertFactory(ACCOUNT_DTO_CLASS, $fields, $data);
```

Example generated file (`includes/entity/dto/account.php`):

```php
namespace App\Entity\DTO;

class account {
  public $id = '';
  public $account_uuid = '';
}
```

Directory layout for option B:

```text
includes/
├── db.php
├── index.php              # require db, auth, constraints (optional)
├── entity/
│   ├── Account.php        # optional: class-based entity (App\Entity\Account)
│   └── dto/               # generated — do not hand-edit
│       ├── account.php
│       └── todo.php
└── entities/              # option A only — procedural functions
```

See [Example site layout](example-site.md#dto-loading-option-a-vs-option-b) for full site conventions.

---

### Typed output (`--typed`)

Works with **either** option. Adds `@property` docblocks, PHP typed properties, `$uuidProperty`, `$insertSkip` for auto-increment and timestamp columns, and `$updateSkip` for identity and immutable columns:

```bash
# Option A
vendor/bin/pure-generate-classes ./sql ./includes/dbGeneratedClasses.php --typed

# Option B
vendor/bin/pure-generate-classes ./sql --output-dir=./includes/entity/dto --namespace=App\\Entity\\DTO --typed
```

Example typed class:

```php
/**
 * Generated row class for table `account`.
 *
 * @property int $id (primary key; auto-increment)
 * @property string $created (default CURRENT_TIMESTAMP)
 * @property ?string $deleted_at
 */
class account {
  public static string $uuidProperty = 'account_uuid';

  /** @var list<string> Columns omitted on insert (auto-increment / DB defaults) */
  public static array $insertSkip = ['id', 'created', 'updated'];

  /** @var list<string> Columns omitted on update (identity / immutable) */
  public static array $updateSkip = ['id', 'created', 'account_uuid'];

  public int $id = 0;
  public string $created = '';
  public ?string $deleted_at = null;
}
```

Use `DB::objectInsertFactory()` in entity `create()` helpers — it respects `$insertSkip` and auto-populates the UUID from `$uuidProperty` (or `{table}_uuid` when untyped). Pass an explicit `$fields` whitelist when building from HTTP input; omit `$fields` when `$data` is already validated in code.

Use `DB::objectUpdateFactory()` in entity `update()` helpers — it respects `$updateSkip` and never generates a new UUID. Pass an explicit `$fields` whitelist when building from HTTP input. Omit `updated` from the patch to let `ON UPDATE CURRENT_TIMESTAMP` apply, or set it explicitly in entity code when needed.

For general row assembly without skip lists, use `DB::objectFactory()` with `$populateUuidProperty = false` (no auto UUID).

### `objectFactory` / `objectInsertFactory` / `objectUpdateFactory`

```php
// Insert — auto UUID, honors $insertSkip on typed DTOs
$obj = DB::objectInsertFactory('account', $data);
$obj = DB::objectInsertFactory(account::class, $fields, $data);

// Update — honors $updateSkip on typed DTOs; never auto UUID
$obj = DB::objectUpdateFactory('account', $data);
$obj = DB::objectUpdateFactory(account::class, $fields, $data);

// General row build — UUID only when you pass the column name
$obj = DB::objectFactory('account', $data, $fields, 'account_uuid');
$obj = DB::objectFactory('account', $data, $fields, false);
```

| Argument | `objectFactory` | `objectInsertFactory` | `objectUpdateFactory` |
|----------|-----------------|------------------------|------------------------|
| `$data` | Source values (array or object) | Same | Same |
| `$fields` | `null` = keys from `$data`; array = whitelist | Same | Same |
| `$populateUuidProperty` | `string` = generate; `false` = skip; `null` = skip | `null` = auto (`$uuidProperty` or `{name}_uuid`); `string` = explicit; `false` = skip | *(not accepted — never auto UUID)* |
| Skip list | none | `$insertSkip` | `$updateSkip` |

### Application script (`scripts/generate-dto-classes.php`)

Pin codegen policy in one app-owned file instead of remembering CLI flags. The script loads `vendor/autoload.php` only — **no database connection** and no `init.php` required.

**Why use it**

| Benefit | Detail |
|---------|--------|
| Single source of truth | SQL path, output path, namespace, typed flag |
| Composer integration | `"generate-dto": "php scripts/generate-dto-classes.php"` |
| CI / agents | One command documented in your repo README |
| Option B post-steps | Echo reminder to run `composer dump-autoload` |

**Copy the template** from [`examples/minimal-site/scripts/generate-dto-classes.example.php`](../examples/minimal-site/scripts/generate-dto-classes.example.php), or use the [example todo app](https://github.com/pureframework/example-todo-app) script for option A.

**Option A — cache file** (procedural entities, `pure-new-site` default):

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PureFramework\DbGenerateClasses;

const DTO_SQL_PATH   = __DIR__ . '/../sql';
const DTO_CACHE_FILE = __DIR__ . '/../includes/dbGeneratedClasses.php';
const DTO_TYPED      = true;

$generated = DbGenerateClasses::generateFromPath(
    DTO_SQL_PATH,
    DTO_CACHE_FILE,
    true,
    DTO_TYPED,
);

foreach ($generated as $table) {
    echo "Generated {$table}\n";
}
```

Run: `php scripts/generate-dto-classes.php` or `composer generate-dto`.

**Option B — PSR-4 per file** (class-based entities):

```php
const DTO_SQL_PATH   = __DIR__ . '/../sql';
const DTO_OUTPUT_DIR = __DIR__ . '/../includes/entity/dto';
const DTO_NAMESPACE  = 'App\\Entity\\DTO';
const DTO_TYPED      = true;

$generated = DbGenerateClasses::generateToDirectory(
    DTO_SQL_PATH,
    DTO_OUTPUT_DIR,
    DTO_NAMESPACE,
    true,
    DTO_TYPED,
);
```

After option B, run `composer dump-autoload` when output paths or namespaces change.

**`composer.json`**

```json
{
  "scripts": {
    "generate-dto": "php scripts/generate-dto-classes.php"
  }
}
```

**Constants vs CLI**

| Approach | When |
|----------|------|
| App script constants | Default for team projects — documents *this* app's layout |
| `vendor/bin/pure-generate-classes …` | Quick one-off, docs snippets, no script yet |
| `PURE_DB_SQL_PATH` / `PURE_DB_SQL_CACHE` | Zero-arg CLI only if constants exist in the shell environment (uncommon) |

Do **not** pass request input as table names in entity code — use generated class names or constants.

### Programmatic API

```php
use PureFramework\DbGenerateClasses;

// Option A
$classNames = DbGenerateClasses::generateFromPath($sqlPath, $cachePath, typed: true);

// Option B
$classNames = DbGenerateClasses::generateToDirectory($sqlPath, $outputDir, 'App\\Entity\\DTO', typed: true);

$fileContents = DbGenerateClasses::buildClassFileContent($config, 'App\\Entity\\DTO', typed: true);
```

### SQL directory rules

Codegen reads **top-level** `*.sql` files only (not subdirectories). Each file should contain one `CREATE TABLE` statement.

| File pattern | Behavior when not a `CREATE TABLE` |
|--------------|-------------------------------------|
| `{digits}_*.sql` (e.g. `00_database.sql`, `001_add_column.sql`) | **Skipped** — migration/setup files |
| Any other `*.sql` | **`RuntimeException`** — fail fast |

Use a numeric prefix for migrations, `CREATE DATABASE`, and ordered schema changes that are not table definitions. Put seeds in `sql/examples/` (not scanned) or give them a `{digits}_` prefix if they must live beside table files.

**Never pass user input as a table name** to `fetch()` — use constants or generated class names only.

## Error handling

Failed queries call `DB::log()` with PDO error info. Override `log()` in your subclass; do not enable verbose DB output in production. See [Security](security.md) for production logging patterns.
