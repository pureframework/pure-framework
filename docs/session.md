# Session

Optional helpers for session bootstrap (cookie params, start, destroy, regenerate). Pure Framework does not start sessions automatically, enforce HTTPS, or implement application auth — your `init.php` and handlers own policy.

`Session` does not read or write application `$_SESSION` keys. Store login state, wizard steps, and feature data in application helpers (e.g. `includes/auth.php`). CSRF token storage is the exception — see [CSRF](csrf.md) and `Session::regenerate()`.

Related: [Security](security.md) · [CSRF](csrf.md) · [Configuration](configuration.md)

## Bootstrap order

1. `Session::configureCookieParams()` (and optional `Session::configureGc()`)
2. Optional custom `session_set_save_handler()` (application code)
3. `Session::start()` or `Session::start(lazy: true)`
4. `Csrf::token()` / application reads — only after the session is active when you need `$_SESSION`

```php
use PureFramework\Session;

Session::configureCookieParams([
    'lifetime' => 0,
    'secure' => true,   // or omit to auto-detect HTTPS from $_SERVER
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Staff app: session on every request (CSRF, login)
Session::start();

// Public site: only resume existing sessions (no cookie for anonymous visitors)
Session::start(lazy: true);
```

| Mode | `start()` argument | Use when |
|------|-------------------|----------|
| **Eager** | `lazy: false` (default) | Admin/staff apps, anything that issues CSRF on every page |
| **Lazy** | `lazy: true` | Marketing/public site; create session only after login or explicit `start()` |

## CLI and scripts (`NO_SESSION`)

Long-running scripts and DB tooling should not send session cookies. Define a flag **before** including `init.php`:

```php
// scripts/seed.php
define('NO_SESSION', true);
require dirname(__DIR__) . '/init.php';
```

```php
// init.php
if (!defined('NO_SESSION')) {
    Session::configureCookieParams([/* ... */]);
    Session::start();
}
```

## Cookie lifetime and GC

When cookie `lifetime` is greater than zero (persistent login), align PHP’s GC:

```php
define('PHP_SESSION_TTL', 86400);

Session::configureCookieParams(['lifetime' => PHP_SESSION_TTL, 'secure' => true]);
Session::configureGc(PHP_SESSION_TTL, probability: 1, divisor: 100);
Session::start();
```

Database-backed session handlers stay in application code (custom `SessionHandlerInterface` + your schema). See [`examples/db-session-handler/`](../examples/db-session-handler/README.md).

## Login and logout (application code)

After verifying credentials, write your own session keys and rotate the session id:

```php
// includes/auth.php (example — keys and guards are app-defined)
$_SESSION['user_uuid'] = $user->user_uuid;
$_SESSION['display_name'] = $user->display_name;
Session::regenerate(deleteOld: true, regenerateCsrf: true);
```

Logout:

```php
unset($_SESSION['user_uuid'], $_SESSION['display_name']);
Session::destroy(clearData: true);
```

`regenerate()` calls `session_regenerate_id()` and, by default, `Csrf::regenerate()`.

## Auth guards (application code)

Handlers call application helpers at the top; you supply redirect or 403 behavior:

```php
function auth_require_logged_in(): void
{
    if (empty($_SESSION['user_uuid'])) {
        Display::redirect('/login');
    }
}
```

There is no route middleware — repeat guards per handler or wrap shared logic in `includes/auth.php`.

## Workflow state (wizards, multi-step flows)

Feature-specific keys (`login_pending`, form steps, checkout) stay in `$_SESSION` with **namespaced keys** and small helpers in your entity/includes files.

Pattern:

- Prefix keys by feature (`checkin_*`, `release_form_*`)
- `unset()` related keys when the flow completes or is cancelled
- Use flash-style keys (set → redirect → read once → `unset`)

## API summary

| Method | Purpose |
|--------|---------|
| `configureCookieParams($options)` | `session_set_cookie_params` with secure auto-detect when omitted |
| `configureGc($maxLifetime, $probability, $divisor)` | `ini_set` for session GC |
| `start($lazy = false)` | `session_start()` or resume-only when lazy |
| `isActive()` | Session status check |
| `destroy($clearData = true)` | Clear `$_SESSION` and `session_destroy()` |
| `regenerate($deleteOld, $regenerateCsrf)` | Fixation mitigation + optional CSRF rotation |

## See also

- [Security checklist](security.md#checklist-production)
- [CSRF](csrf.md) — requires an active session for token storage
