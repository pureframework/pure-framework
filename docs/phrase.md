# Phrase catalog

Optional in-memory message catalog for templates and shared copy. **`Phrase`** maps string keys to human-readable text, with optional `{token}` substitution and per-language buckets.

The framework does not load phrase files automatically — register strings in application bootstrap (`init.php` or included config).

Related: [Templates and layout](templates-and-layout.md) · [Forms and validation](forms-and-validation.md) · [Installation](installation.md)

## When to use

| Use `Phrase` / `__()` | Use constraint messages instead |
|----------------------|----------------------------------|
| Shared UI labels, buttons, static copy | Field validation from `ConstraintType` |
| Translating keys to another language | Messages already on `ConstraintViolation` / `$error->getMessages()` |
| Error keys mapped to a catalog in templates | Inline `{label} is required` in constraint classes |

Typical pipeline:

```text
ConstraintViolation  →  human message in PHP (validation)
Phrase / __()         →  optional catalog lookup (presentation / i18n)
html()                →  escape before output in templates
```

Forms work without `Phrase`. Constraints return messages via `ConstraintViolationMessage::getMessage()` — echo those with `html()` directly, or pass constraint **type** keys through `__()` if you maintain a parallel catalog.

## Defaults

| Setting | Default |
|---------|---------|
| Active language | `en` |
| Missing key | Returns the key string unchanged |
| Token syntax | `{name}` replaced by `$params['name']` |

No `configure()` call is required. Use `setLanguage()` and `load()` / `add()` in application code.

## Bootstrap

Load a PHP file that returns an associative array, or register strings programmatically:

```php
use PureFramework\Phrase;

Phrase::setLanguage('en');
Phrase::load(__DIR__ . '/lang/phrases.en.php');
Phrase::load(__DIR__ . '/lang/phrases.es.php', 'es');

// Or ad hoc:
Phrase::add('nav.home', 'Home');
Phrase::add('errors.required', '{label} is required.');
```

**Phrase file** (`lang/phrases.en.php`):

```php
<?php

return [
    'errors.required' => 'This field is required.',
    'errors.max_length' => 'Must be at most {max} characters.',
    'nav.home' => 'Home',
];
```

Later keys from `load()` merge over earlier ones for the same language. `add()` overwrites a single key.

Example file: [`examples/minimal-site/phrases.en.example.php`](../examples/minimal-site/phrases.en.example.php).

### Multiple languages

```php
Phrase::setLanguage('en');
Phrase::load(__DIR__ . '/lang/phrases.en.php', 'en');
Phrase::load(__DIR__ . '/lang/phrases.es.php', 'es');

// Resolve in Spanish explicitly:
Phrase::get('nav.home', [], 'es');

// Or switch default language for subsequent get()/__() calls:
Phrase::setLanguage('es');
```

Language selection (cookie, `Accept-Language`, user profile) stays in application code — the class only stores buckets keyed by language code.

## Templates

Import the namespaced helper (same pattern as `html()`):

```php
<?php use function PureFramework\html;
use function PureFramework\__; ?>

<p class="error"><?= html(__('errors.required', ['label' => $nameField->label])) ?></p>
```

Always wrap catalog output in `html()` when emitting HTML — `Phrase` does not escape.

Class form without the helper:

```php
<?= html(\PureFramework\Phrase::get('errors.required', ['label' => 'Name'])) ?>
```

## Form validation and catalogs

Constraints usually embed the message:

```php
return new ConstraintViolation('required', '{label} is required.', ['label' => $label]);
```

Template (direct message):

```php
<?php foreach ($error->getMessages() as $message): ?>
    <p class="error"><?= html($message) ?></p>
<?php endforeach; ?>
```

Optional catalog by violation type:

```php
<?php use function PureFramework\__; ?>
<p class="error"><?= html(__($error->type, ['label' => $nameField->label])) ?></p>
```

Register matching keys (`required`, `max_length`, etc.) in your phrase files. Constraint `type` slugs come from the constraint class name — see [Forms and validation — ConstraintType](forms-and-validation.md#constrainttype).

## Token replacement

Placeholders use curly braces, matching constraint message tokens:

```php
Phrase::add('greeting', 'Hello {name}.');
Phrase::get('greeting', ['name' => 'Ada']); // "Hello Ada."
```

Unknown tokens are left as-is in the string.

## API

| Method | Purpose |
|--------|---------|
| `get($key, $params = [], $language = null)` | Resolve key; substitute `{token}` values |
| `has($key, $language = null)` | Whether key is registered |
| `add($key, $phrase, $language = null)` | Register or overwrite one phrase |
| `load($file, $language = null)` | Merge phrases from a PHP file returning an array |
| `setLanguage($language)` | Default language for subsequent calls |
| `getLanguage()` | Current default language |
| `getAll($language = null)` | All phrases for a language (tests, debug) |
| `clear($language = null)` | Remove one language bucket or all phrases (tests) |

### Template helper

| Function | Purpose |
|----------|---------|
| `PureFramework\__($key, $params = [], $language = null)` | Alias for `Phrase::get()` — import with `use function PureFramework\__;` |

Autoloaded from `src/helpers.php` (same as `html()`).

## Tests

```php
use PureFramework\Phrase;
use function PureFramework\__;

Phrase::add('test.key', 'Value');
// assert __(...) or Phrase::get(...)
Phrase::clear();
```

Call `Phrase::clear()` in `tearDown()` so in-memory catalogs do not leak between tests.

## Non-goals

- No gettext / `.mo` integration
- No automatic language detection
- No HTML escaping inside `Phrase`
- No integration with `Router` or `Form` — application wires keys to messages

## See also

- [Templates and layout — Escaping](templates-and-layout.md#escaping) — `html()` in `.tpl.php`
- [Forms and validation](forms-and-validation.md) — constraints and field errors
- [Configuration](configuration.md) — where bootstrap hooks live
