# Security

Pure Framework does not enforce a security policy. Applications configure sessions, HTTPS, error visibility, and logging in bootstrap code (`init.php` or equivalent). This guide covers recommended defaults for production sites using the library.

Related: [Session](session.md) · [CSRF](csrf.md) · [Database](database.md) · [Configuration](configuration.md)

## Session cookies

Start the session **after** setting cookie parameters and **before** any output or CSRF calls. Use `PureFramework\Session` for bootstrap helpers, or call PHP’s session API directly.

```php
use PureFramework\Session;

Session::configureCookieParams([
    'lifetime' => 0,
    'secure' => true,   // omit to auto-detect HTTPS from $_SERVER
    'httponly' => true,
    'samesite' => 'Lax',
]);

Session::start();              // staff app: every request
// Session::start(lazy: true); // public site: only if session cookie exists
```

| Option | Recommendation |
|--------|----------------|
| `httponly` | Always `true` — keeps the session ID out of JavaScript |
| `secure` | `true` when the site is served over HTTPS |
| `samesite` | `Lax` for most sites; `Strict` if you never need cross-site POST navigation |
| `lifetime` | `0` (browser session) unless you need persistent login cookies |

CLI scripts should define `NO_SESSION` before `init.php` so bootstrap skips `Session::start()`. See [Session](session.md#cli-and-scripts-no_session).

After a successful login, regenerate the session ID to limit fixation:

```php
Session::regenerate(deleteOld: true, regenerateCsrf: true);
```

The framework does not call `session_start()` for you unless your `init.php` does. Full bootstrap API: [session.md](session.md). CSRF: [csrf.md](csrf.md).

## HTTPS

Serve production traffic over HTTPS. In handlers or bootstrap, redirect when appropriate:

```php
if (APP_ENV === 'production' && !$REQUEST->isHttps()) {
    header('Location: https://' . $REQUEST->domain() . $REQUEST->url(), true, 301);
    exit;
}
```

Set `secure => true` on session cookies when HTTPS is enforced.

## Production vs development errors

### PHP error display

In **production**, do not expose stack traces or internal paths to browsers:

```php
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
```

Log errors to the server error log or your logging pipeline instead of rendering them in HTML.

### User-facing error pages

Return generic pages for unexpected failures in production (`500.php`, `404.php` via `Display::notFound()`). Reserve detailed messages for development and structured JSON API responses you control.

### Database logging

Failed queries call `DB::log()` with PDO error info and the SQL text. The base implementation is a no-op. **Override `log()` in your application `DB` subclass:**

```php
class DB extends \PureFramework\DB
{
    public static function log($msg)
    {
        if (APP_ENV === 'development') {
            error_log($msg);
        }
        // Production: write to a log file, syslog, or monitoring — not the HTTP response
    }
}
```

Never echo `DB::log()` output or raw query failures to end users in production.

## Input and output

| Concern | Approach |
|---------|----------|
| SQL injection | Use `DB::query()` / `insert()` / `update()` with bound parameters — never concatenate user input into SQL |
| Table names | Use constants or generated class names only — never pass request parameters as table names |
| XSS in templates | `PureFramework\Util::html()` or `html()` when echoing untrusted text — see [Templates and layout](templates-and-layout.md) |
| CSRF on POST | Call `Csrf::verify()` or `Csrf::verifyHeader()` in state-changing handlers — see [csrf.md](csrf.md) |
| Request input | Use `$REQUEST->post()`, `query()`, and `param()` — treat all values as untrusted until validated |

Validate and sanitize in entity functions or `Form` constraints before persistence.

## Transactions and consistency

Use `DB::beginTransaction()`, `commit()`, and `rollback()` (or `DB::transaction()`) when a handler performs multiple writes that must succeed or fail together. On exception, roll back before returning an error response.

```php
DB::beginTransaction();
try {
    DB::insert('order', $order);
    DB::insert('order_line', $line);
    DB::commit();
} catch (\Throwable $e) {
    DB::rollback();
    throw $e;
}
```

Or:

```php
DB::transaction(function () use ($order, $line) {
    DB::insert('order', $order);
    DB::insert('order_line', $line);
});
```

## Environment constant

Define a single application environment flag in config (name is up to you):

```php
define('APP_ENV', 'production'); // or 'development', 'staging'
```

Use it for error display, `DB::log()`, and optional debug tooling — not for security checks alone. Authorization still belongs in explicit handler and entity code.

## Checklist (production)

- [ ] HTTPS enforced; session `secure` cookie flag set
- [ ] Session cookies: `HttpOnly`, appropriate `SameSite`
- [ ] `display_errors` off; errors logged server-side
- [ ] `DB::log()` overridden — no query details sent to browsers
- [ ] CSRF verified on POST handlers that mutate state
- [ ] Auth checks at top of protected handlers (application helpers — no route middleware)
- [ ] User input validated before database writes
