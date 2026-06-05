# Templates, layout, and Display

HTML rendering uses plain PHP templates. In application handlers, use **`PureFramework\Display`** as the entry point. It reads path constants, delegates to **`Layout`** for wrapped pages, and uses **`Template`** for includes and output buffering.

Handlers should call `Display` static methods — not instantiate `Layout` directly — unless you have a special case (custom layout paths, tests, or advanced composition).

## Setup

Define paths in `config.php` before any handler runs:

```php
define('PURE_LAYOUT_PATH', __DIR__ . '/templates');
define('PURE_HTDOCS_PATH', __DIR__ . '/htdocs'); // optional; used by Display::notFound()
```

| Constant | Used by |
|----------|---------|
| `PURE_LAYOUT_PATH` | `page()`, `partial()`, `fetchPartial()`, `layout()` |
| `PURE_HTDOCS_PATH` | `notFound()` when resolving `htdocs/404.php` |

In tests, override paths without constants:

```php
Display::configure('/fixture/templates', '/fixture/htdocs');
// ...
Display::reset();
```

## Display API

```php
use PureFramework\Display;
```

| Method | Output | Layout? |
|--------|--------|---------|
| `page($template, $vars, $layoutVariant)` | Echoes HTML | Yes — content inside `site.layout.php` |
| `template($file, $vars)` | Echoes HTML | No |
| `fetchTemplate($file, $vars)` | Returns HTML string | No |
| `partial($name, $vars, $parent)` | Echoes HTML | No — file from `templates/partials/` |
| `fetchPartial($name, $vars, $parent)` | Returns HTML string | No |
| `redirect($url, $status = 302)` | HTTP redirect, exits | — |
| `notFound($handler404 = null)` | 404 response, exits | — |
| `layout()` | Returns `Layout` instance | — |
| `configure($layoutPath, $htdocsPath)` | Override paths (tests) | — |
| `reset()` | Clear path overrides | — |

### `page()` — handler pages

The usual choice for HTML handlers. The router `chdir`s to the handler directory, so `$template` is resolved beside the handler file (e.g. `list.tpl.php` next to `index.php`).

```php
// htdocs/users/index.php
Display::page('list.tpl.php', [
    'title' => 'Users',
    'items' => $items,
]);
```

**Arguments:**

| Argument | Description |
|----------|-------------|
| `$template` | Co-located `.tpl.php` filename or path |
| `$vars` | Associative array passed to content, header, footer, and layout |
| `$layoutVariant` | Optional; loads `site.layout-{variant}.php` instead of `site.layout.php` |

```php
Display::page('edit.tpl.php', ['user' => $user], 'admin');
// → templates/site.layout-admin.php
```

**Render order:**

1. Content template runs → HTML captured as `$content` in the layout shell.
2. Vars from the content `Template` (including updates from `$this->set()`) merge into the layout pass.
3. Layout, header, and footer render with merged vars plus `$header`, `$content`, `$footer`.

#### Exporting vars from content to layout

From inside a content `.tpl.php`, `$this` is the active `Template` instance.

**Add or replace a var** for the layout pass:

```php
<?php
// list.tpl.php
$this->set('pageTitle', 'Users');
$this->set('showSidebar', false);
?>
<ul>...</ul>
```

**Update a var the handler already passed** (vars are extracted by reference):

```php
<?php
// details.tpl.php — handler passed 'title' => 'Loading…'
$title = $item->name . ' — Details';
?>
```

| In content template | Available in layout? |
|---------------------|----------------------|
| `$this->set('key', $value)` | Yes |
| `$key = $value` when `$key` was in handler `$vars` | Yes |
| `$key = $value` for a new local only | No |

Use `$this->set()` when the content template computes something the layout shell needs (title, nav state, wrapper classes, etc.).

### `template()` and `fetchTemplate()` — no layout

Render a single file without header, footer, or layout shell. Useful for fragments, emails, or bare pages.

```php
Display::template('bare.tpl.php', ['value' => 1]);

$html = Display::fetchTemplate(__DIR__ . '/email-body.tpl.php', [
    'user' => $user,
]);
```

`$file` may be absolute or relative to the current working directory (usually the handler directory after routing).

### `partial()` and `fetchPartial()` — shared snippets

Render files from `{PURE_LAYOUT_PATH}/partials/`. The `.php` extension is added automatically if omitted.

```php
Display::partial('nav-primary', ['active' => 'users']);

$html = Display::fetchPartial('nav-primary.php', ['active' => 'users']);
```

Pass `$parent` to expose a parent object as `$site` in the partial template.

Partials do not run inside `site.layout.php`. Call them from handlers or from other templates as needed.

### `redirect()` and `notFound()` — HTTP outcomes

```php
Display::redirect('/login');
Display::redirect('/users/' . $id, 301);

Display::notFound();                    // resolve 404.php automatically
Display::notFound('/path/to/404.php');  // explicit handler
```

**`notFound()` resolution order:**

1. `$handler404` argument, if provided and the file exists
2. Walk up from the current working directory (closest `404.php` first — e.g. after the router `chdir`s into a handler directory)
3. `{PURE_HTDOCS_PATH}/404.php` (htdocs root)
4. Fallback: `404` status and minimal HTML

Typical router setup — unmatched URLs call **`Display::notFound()`** automatically. Place `404.php` in `htdocs/` (or a parent of the handler cwd) and set `PURE_HTDOCS_PATH`, or override:

```php
$router->setNotFoundHandler('404.php');
```

A custom `404.php` can render directly or delegate again with `Display::notFound()`. A catch-all route (`^/.*$`) still works but is optional.

### `layout()` — direct Layout access

Returns a cached `Layout` instance for the configured `PURE_LAYOUT_PATH`. Rare in application code; useful for tests or custom composition.

```php
$layout = Display::layout();
$layout->setDefaultLayoutFile('/custom/site.layout.php');
```

## Layout directory

Under `PURE_LAYOUT_PATH`:

| File | Purpose |
|------|---------|
| `site.layout.php` | Main layout wrapper; receives `$header`, `$content`, `$footer` |
| `site.header.php` | Header HTML injected as `$header` |
| `site.footer.php` | Footer HTML injected as `$footer` |
| `partials/*.php` | Shared partials via `partial()` / `fetchPartial()` |

Example layout shell:

```php
<!DOCTYPE html>
<html>
<head><title><?php use function PureFramework\html; echo html($title ?? 'App'); ?></title></head>
<body>
<?php echo $header; ?>
<main><?php echo $content; ?></main>
<?php echo $footer; ?>
</body>
</html>
```

Variant layouts: `Display::page(..., layoutVariant: 'admin')` loads `site.layout-admin.php`.

## Co-located templates

Because the router changes directory to the handler folder before `include`, `Display::page('list.tpl.php')` resolves templates next to the handler:

```text
htdocs/users/
├── index.php       ← handler
└── list.tpl.php    ← co-located template
```

## Template engine (lower level)

`PureFramework\Template` handles `chdir`, variable scope, nested templates, and output buffering. Application code should prefer `Display` for consistency.

In `.tpl.php` files included by `Template`, `$this` refers to that `Template` instance. See [Exporting vars from content to layout](#exporting-vars-from-content-to-layout) for `$this->set()`.

Direct `Layout::template()` is available but bypasses `Display` path resolution; use only when you manage paths yourself.

## Escaping

Escape dynamic output in templates with **`PureFramework\Util::html()`** or **`html()`** (autoloaded helper):

```php
<?php use function PureFramework\html; ?>
<p><?= html($user->name) ?></p>
<input value="<?= html($search) ?>">
```

`Util::html($value, $doubleEncode = false)` accepts strings and numbers; `null` and non-scalars become `''`. Equivalent to `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. The framework does not auto-escape.

Do not pass layout fragments (`$header`, `$content`, `$footer`) through `html()` — they are already HTML from `Template`. Only escape **untrusted** strings (user input, DB text shown as plain text).

## Phrase lookup

For catalog keys and i18n, use **`__()`** or **`Phrase::get()`** — always combine with `html()` in HTML output:

```php
<?php use function PureFramework\html;
use function PureFramework\__; ?>
<p><?= html(__('errors.required')) ?></p>
```

Register phrases in bootstrap; full guide: **[Phrase catalog](phrase.md)**.

## Common patterns

| Goal | Approach |
|------|----------|
| Standard site page | `Display::page('foo.tpl.php', $vars)` |
| Escape user text | `html($value)` or `Util::html($value)` |
| Localized / catalog text | `__($key, $params)` — [phrase.md](phrase.md) |
| Set title from content | `$this->set('pageTitle', '…')` in `.tpl.php` |
| Admin layout | `Display::page(..., layoutVariant: 'admin')` |
| JSON API | `HttpResponse::json()` with `SuccessResponse` / `ErrorResponse` — [responses.md](responses.md) |
| After POST | `Display::redirect('/users')` |
| Unknown URL | Default `Display::notFound()` from router; optional `setNotFoundHandler('404.php')` |
| Email / PDF body | `Display::fetchTemplate(...)` |
| Nav in layout | `$this->set()` or pass vars from handler; render nav in `site.header.php` |
