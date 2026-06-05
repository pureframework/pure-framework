# Database session handler (application pattern)

Pure Framework does not ship a `SessionHandlerInterface` implementation. Multi-server or long-lived deployments often store PHP session blobs in the database.

## Outline

1. Create a `php_session` table (`id`, `data`, `last_activity`).
2. Implement read/write/destroy/gc with your application `DB` class.
3. Register the handler **before** `Session::start()` in `init.php`.

```php
// init.php (after config, before Session::start())
require __DIR__ . '/includes/customSessionHandler.php';

Session::configureCookieParams(['lifetime' => PHP_SESSION_TTL, 'secure' => true]);
Session::configureGc(PHP_SESSION_TTL, probability: 1, divisor: 100);
Session::start();
```

## SQL (MySQL)

```sql
CREATE TABLE php_session (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data BLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Handler sketch

```php
class CustomSessionHandler implements SessionHandlerInterface
{
    public function open($savePath, $sessionName): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string
    {
        // SELECT data FROM php_session WHERE id = ? AND last_activity >= ?
        return '';
    }

    public function write($id, $data): bool
    {
        // INSERT ... ON DUPLICATE KEY UPDATE data, last_activity
        return true;
    }

    public function destroy($id): bool { /* DELETE */ return true; }

    public function gc($max_lifetime): int|false
    {
        // DELETE WHERE last_activity < time() - $max_lifetime
        return 0;
    }
}

session_set_save_handler(new CustomSessionHandler(), true);
```

Use `Session::configureGc()` so PHP’s GC matches your TTL. See [Session](../../docs/session.md).
