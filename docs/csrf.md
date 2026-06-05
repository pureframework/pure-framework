# CSRF protection

Optional session-based CSRF for state-changing **POST forms** and **AJAX** requests. The framework does not verify automatically — call `Csrf::verify()` or `Csrf::verifyHeader()` in handlers.

Auth and session configuration stay in the application; this class only manages the token.

## Defaults (no setup required)

Out of the box:

| Setting | Default |
|---------|---------|
| Session key | `_csrf` |
| Form field | `_csrf` |
| Header | `X-CSRF-Token` |

Use `Csrf::token()`, `field()`, `verify()`, and `verifyHeader()` directly. Call `configure()` only when you need different names.

## Requirements

1. Start the PHP session in application `init.php` before any CSRF call:

```php
use PureFramework\Session;

Session::configureCookieParams([/* ... */]);
Session::start();
```

See [Session](session.md) and [Security](security.md#session-cookies).

3. For form POST handlers, check CSRF only when handling a submission:

```php
use PureFramework\Csrf;

if ($REQUEST->isPost() && !Csrf::verify($REQUEST)) {
    http_response_code(403);
    exit;
}
```

## HTML forms

In `.tpl.php`:

```php
<?php echo PureFramework\Csrf::field(); ?>
```

Renders:

```html
<input type="hidden" name="_csrf" value="...">
```

Handler:

```php
if ($REQUEST->isPost() && !Csrf::verify($REQUEST)) {
    http_response_code(403);
    exit;
}
```

## AJAX / JSON POST

Send the token in the `X-CSRF-Token` header. Expose the token to JavaScript from the page (e.g. meta tag or inline script reading `Csrf::token()`).

Handler:

```php
if ($REQUEST->isPost() && !Csrf::verifyHeader()) {
    http_response_code(403);
    exit;
}
```

You may use **either** `verify()` (form field) **or** `verifyHeader()` (header), or require both for stricter checks.

## Regenerate after login

```php
PureFramework\Csrf::regenerate();
```

Issues a new token for the authenticated session.

## Overrides (optional)

When defaults are not suitable, call once at bootstrap:

```php
PureFramework\Csrf::configure(
    sessionKey: 'my_csrf',
    fieldName: 'csrf_token',
    headerName: 'X-Custom-Token',
);
```

Tests may call `Csrf::reset()` to restore defaults.

## API

| Method | Purpose |
|--------|---------|
| `token()` | Return current token; create in session if missing |
| `regenerate()` | Replace session token (e.g. after login) |
| `field($fieldName = null)` | Hidden `<input>` HTML for templates |
| `verify(?Request $request = null, ?string $fieldName = null)` | Compare POST field to session token |
| `verifyHeader(?string $headerName = null)` | Compare HTTP header to session token |
| `configure($sessionKey, $fieldName, $headerName)` | Override defaults (optional) |
| `reset()` | Restore defaults (tests) |

## Failure responses

The library returns `true` / `false` only. Choose application behavior on failure: 403, redirect, flash message, or JSON error via `ErrorResponse` — [responses.md](responses.md).

## Non-goals

- No automatic verification in `Router`
- No session start inside the framework
- JSON body token field not supported in v1 — use header or form field
