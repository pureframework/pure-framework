# Router

The router maps URL patterns to PHP handler files. It is intentionally small: ordered regex routes, no middleware stack in core.

## Basic usage

```php
use PureFramework\Router;

$router = new Router();

$router->route('^/users$', 'users/index.php');
$router->route('^/users/new$', 'users/edit/index.php');
$router->route('^/users/([0-9a-f-]{36})$', ['user_uuid'], 'users/details/index.php');

$router->run(); // uses $_SERVER to build Request when no argument passed
```

## Route registration

`route()` supports **three forms** only. Routes are tried **in registration order** — register specific paths before broad patterns.

```php
// GET (default) — pattern, handler
$router->route('^/users$', 'users/index.php');

// HTTP method — method, pattern, handler
$router->route('POST', '^/users$', 'users/edit/index.php');

// Captured groups — method optional; param names map to $REQUEST->param()
$router->route('^/users/([0-9a-f-]{36})$', ['user_uuid'], 'users/details/index.php');
$router->route('POST', '^/users/([0-9a-f-]{36})$', ['user_uuid'], 'users/edit/index.php');
```

| Argument | Description |
|----------|-------------|
| **Pattern** | Regex string starting with `^` (recommended with `$` end anchor), or exact path match |
| **Handler** | PHP file path relative to the front controller working directory (`htdocs/`) |
| **Method** | Optional: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD`, `OPTIONS`, `QUERY` |
| **Param names** | Optional list: `['user_uuid']` maps the first capture group, `['a','b']` the second, etc. |

Match **path only**. Query string, cookies, host, port, and HTTPS belong in **`init.php`** or at the top of handlers (`$REQUEST->query()`, auth checks), not in route definitions.

### Removed (unsupported)

These older `Route` features were removed to keep the API small:

| Removed | Use instead |
|---------|-------------|
| Associative `route(['url' => …, 'query' => …], …)` | Positional `route()` forms above |
| Query/cookie conditions on routes | `$REQUEST->query()` / handler logic |
| `domain`, `port`, `https` on routes | App bootstrap / reverse proxy config |
| `/users/:id` segment syntax | Regex captures: `^/users/([^/]+)$` with `['id']` |
| Callable route handlers | File path only; `setNotFoundHandler()` accepts callable for 404 |

Passing removed forms triggers `E_USER_DEPRECATED` and an `InvalidArgumentException`.

## Handler execution

When a route matches:

1. URL parameters are parsed into the request via `setParams()`
2. The process `chdir`s to the handler's directory so relative includes and templates work
3. The handler file is included; its return value is passed back from `run()`

Handlers read input via `$REQUEST` accessors — route/query/post/cookies for keyed values; scalars for URL and transport metadata.

## Request object

`PureFramework\Request` is populated from the current HTTP request. Keyed bags and scalars use accessors (not public properties).

### Scalar accessors

| Accessor | Setter (tests) | Source |
|----------|----------------|--------|
| `method()` | `setMethod()` | `$_SERVER['REQUEST_METHOD']` |
| `url()` | `setUrl()` | Path without query string |
| `isHttps()` | `setHttps()` | HTTPS / forwarded proto |
| `domain()` | `setDomain()` | `$_SERVER['SERVER_NAME']` |
| `port()` | `setPort()` | `$_SERVER['SERVER_PORT']` |
| `referer()` | `setReferer()` | `HTTP_REFERER` |

### Keyed accessors (route, query, post, cookies)

**Single value:**

```php
$uuid = $REQUEST->uuidParam('client_uuid');
$page = $REQUEST->query('page', 1);
$subtotal = $REQUEST->post('subtotal');
$theme = $REQUEST->cookie('theme', 'light');
```

**Full array** (merged with per-key defaults; request values override defaults for the same key):

```php
$params = $REQUEST->params();
$query = $REQUEST->queryAll(['view' => 'month', 'page' => 1]);
$post = $REQUEST->postAll();
$cookies = $REQUEST->cookies();
```

| Method | Purpose |
|--------|---------|
| `param($name, $default = null)` | One route capture |
| `hasParam($name)` | Whether the matched route registered that capture (e.g. new vs edit on one handler) |
| `uuidParam($name)` | Route capture sanitized with `Util::sanitizeUuid()`; `false` if missing, empty, or invalid |
| `query($name, $default = null)` | One query string value (`$_GET`) |
| `post($name, $default = null)` | One POST field (`$_POST`) |
| `cookie($name, $default = null)` | One cookie value |
| `params(array $defaults = [])` | All route params + defaults |
| `queryAll(array $defaults = [])` | All query params + defaults |
| `postAll(array $defaults = [])` | All POST fields + defaults |
| `cookies(array $defaults = [])` | All cookies + defaults |
| `setParams(?array $params)` | Set route params (router / tests) |
| `setQuery(?array $query)` | Set query params (tests) |
| `setPost(?array $post)` | Set POST body (tests) |
| `setCookie(?array $cookie)` | Set cookies (tests) |

For query-or-post fallbacks (legacy API handlers), read each source explicitly:

```php
$subtotal = $REQUEST->query('subtotal') ?? $REQUEST->post('subtotal');
```

Use `jsonBody()` for JSON request bodies. For route UUIDs, prefer `uuidParam()`; for query/post UUIDs call `Util::sanitizeUuid()` explicitly.

### HTTP method helpers

Prefer instance methods on `$REQUEST` in handlers instead of global helpers such as `is_post()`:

| Method | When true |
|--------|-----------|
| `$REQUEST->isGet()` | GET |
| `$REQUEST->isPost()` | POST |
| `$REQUEST->isPut()` | PUT |
| `$REQUEST->isPatch()` | PATCH |
| `$REQUEST->isDelete()` | DELETE |
| `$REQUEST->isHead()` | HEAD |
| `$REQUEST->isOptions()` | OPTIONS |
| `$REQUEST->isQueryMethod()` | QUERY (non-standard verb for read-with-body) |
| `$REQUEST->isMethod('POST')` | Any verb; comparison is case-insensitive |

### JSON body

For API handlers that read `php://input`:

```php
$input = $REQUEST->jsonBody();
$emailType = $input['email_type'] ?? '';
```

Returns an **associative array**. Empty body, invalid JSON, or JSON that decodes to a non-array yields `[]`. The body is read once per `Request` instance.

Build a request manually for testing:

```php
$request = new Request(false);
$request->setMethod('GET');
$request->setUrl('/users');
$router->run($request);
```

## 404 handling

When no route matches, the router runs a **not-found handler**. By default it calls **`Display::notFound()`**, which walks up from the working directory for the closest `404.php`, then `htdocs/404.php` when `PURE_HTDOCS_PATH` is set, or sends a minimal 404 response.

Override with `setNotFoundHandler()`:

```php
// Custom handler file (optional — default already uses Display::notFound() resolution)
$router->setNotFoundHandler('404.php');
$router->run();
```

| `setNotFoundHandler(...)` | Behavior |
|---------------------------|----------|
| *(not called)* | `Display::notFound()` |
| `'404.php'` or other file path | Same `chdir` + `include` as route handlers; `$REQUEST`, `$HANDLER` (path), `$ROUTE` is `null` |
| `function (Request $request) { ... }` | Callable; `$REQUEST` global; `$ROUTE` and `$HANDLER` are `null` |
| `null` | Restore framework default (`Display::notFound()`) |
| `false` | Disable not-found handling; `run()` returns `false` when nothing matches |

Custom file and callable handlers return through `run()` like matched routes. The default path calls `Display::notFound()`, which **exits** — `run()` does not return in that case.

### `run()` return value

| Outcome | `run()` returns |
|---------|-----------------|
| Route matched | Handler `include` result (`mixed`) |
| Custom not-found (file or callable) | Same as above |
| Not-found disabled (`setNotFoundHandler(false)`) | `false` |
| Default not-found (`Display::notFound()`) | *(does not return — script exits)* |

### When to use `setNotFoundHandler('404.php')` vs the default

**Default (`Display::notFound()`)** is enough for most HTML sites: `index.php` runs from `htdocs/`, you have `htdocs/404.php` and/or `PURE_HTDOCS_PATH`, and optional **section** `404.php` files are found by walking up from the current working directory (for example after the router `chdir`s into `users/edit/`).

**Register a handler file** when you want router semantics before the 404 page:

- Same **`chdir` + `include`** as normal routes (relative paths in the handler match route handlers)
- Explicit bootstrap in one file (logging, session, layout setup) before rendering
- A fixed path that should run even when walk-up would hit a different `404.php` in a parent directory

**Callable** — custom logic without a file (returns through `run()`).

**`false`** — no framework 404; your front controller handles unmatched URLs (common for JSON APIs).

**Alternatives** (still supported):

- `setNotFoundHandler(false)` — handle `false` from `run()` in `index.php` (JSON APIs, tests)
- **Catch-all route last** — `$router->route('^/.*$', '404.php');` matches before the not-found hook; remove the catch-all when using the built-in not-found behavior

In a custom `404.php`, call `Display::notFound()` only if you need its resolution chain again, or render your own response. See [Templates and layout](templates-and-layout.md).

## Debug

```php
$router->debug(true);
$router->run();
```

Core `Router` does not print anything when debug is on — `debugRequest()` is an intentional **extension hook**. Subclass and override `debugRequest(Request $request, array $routes)` (or wrap `run()`) to log the request, dump the route table, or show which pattern matched. Use `$this->_debug` in the subclass when you only want output while debug is enabled.

```php
class AppRouter extends Router
{
	protected function debugRequest($request, $routes): void
	{
		// development-only logging
	}
}
```

## UUID resource routes (application pattern)

Many admin sites use the same **six routes** per UUID-backed resource (list, read, new, edit, create, update). The framework does **not** register these automatically — explicit `route()` calls (or your own helper) keep `index.php` readable and avoid hidden behavior.

### Route table (register in this order)

Let `$prefix` = `/users`, `$param` = `user_uuid`, `$handlers` = `users` (handler directory under `htdocs/`), and `$uuid` = `([0-9a-f]{8}-[^/]+)` (or a stricter pattern).

| # | Method | Pattern | Handler | Purpose |
|---|--------|---------|---------|---------|
| 1 | POST | `^{$prefix}$` | `{handlers}/edit/index.php` | Create |
| 2 | POST | `^{$prefix}/{$uuid}$` | `{handlers}/edit/index.php` | Update |
| 3 | GET | `^{$prefix}/new$` | `{handlers}/edit/index.php` | New form |
| 4 | GET | `^{$prefix}/{$uuid}/edit$` | `{handlers}/edit/index.php` | Edit form |
| 5 | GET | `^{$prefix}/{$uuid}$` | `{handlers}/details/index.php` | Detail view |
| 6 | GET | `^{$prefix}$` | `{handlers}/index.php` | List |

POST routes must come **before** GET routes. Register `…/new` before the bare `…/{uuid}` pattern so `new` is not captured as a UUID.

### Example: explicit registration

```php
$uuid = '([0-9a-f]{8}-[^/]+)';

$router->route('POST', '^/users$', 'users/edit/index.php');
$router->route('POST', "^/users/{$uuid}$", ['user_uuid'], 'users/edit/index.php');
$router->route('^/users/new$', 'users/edit/index.php');
$router->route("^/users/{$uuid}/edit$", ['user_uuid'], 'users/edit/index.php');
$router->route("^/users/{$uuid}$", ['user_uuid'], 'users/details/index.php');
$router->route('^/users$', 'users/index.php');
```

In handlers that require a UUID, use `$uuid = $REQUEST->uuidParam('user_uuid')` and `if ($uuid === false) { Display::notFound(); }`. For combined new/edit handlers, detect “new” with `!$REQUEST->hasParam('user_uuid')` — see [Forms and validation](forms-and-validation.md).

### Optional application helper

If you repeat the same block for many resources, add a function in **your** codebase (not in the package):

```php
function register_uuid_resource(Router $router, string $prefix, string $uuidParam, string $handlers): void
{
    $prefix = '/' . trim($prefix, '/');
    $uuid = '([0-9a-f]{8}-[^/]+)';
    $map = [$uuidParam];

    $router->route('POST', "^{$prefix}$", "{$handlers}/edit/index.php");
    $router->route('POST', "^{$prefix}/{$uuid}$", $map, "{$handlers}/edit/index.php");
    $router->route("^{$prefix}/new$", "{$handlers}/edit/index.php");
    $router->route("^{$prefix}/{$uuid}/edit$", $map, "{$handlers}/edit/index.php");
    $router->route("^{$prefix}/{$uuid}$", $map, "{$handlers}/details/index.php");
    $router->route("^{$prefix}$", "{$handlers}/index.php");
}
```

Omit routes you do not need (for example read-only resources without POST) by calling `route()` manually instead of the helper.

Required handler layout under `htdocs/{handlers}/`:

```text
index.php           ← list
details/index.php   ← read
edit/index.php      ← new, edit, create, update
```

Nested prefixes work the same way: `$prefix = '/calendar/day/slots'`, `$handlers = 'calendar/day/slots'`.

## Conventions

- Use **UUID capture groups** for public IDs; normalize in handlers with `uuidParam()` or `Util::sanitizeUuid()`
- Register **POST** handlers before **GET** for the same resource when patterns could overlap
- Prefer the **built-in default** or **`setNotFoundHandler('404.php')`** over a catch-all `^.*$` route when possible
- Keep handler paths obvious — co-locate `list.tpl.php` / `form.tpl.php` beside `index.php`
