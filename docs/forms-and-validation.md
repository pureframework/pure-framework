# Forms and validation

Pure Framework validates input with **`Form`**, **`FormField`**, and a composable **constraint** stack. Define constraints per entity in classes extending `ConstraintEntity`. The library does not ship domain validators (email, UUID, etc.) — those live in your application.

## Form basics

```php
use PureFramework\Form;
use PureFramework\TrimTransform;

$form = new Form();
$form->addField('name', 'Name', [new TrimTransform()], [new UserConstraint()->forField('name')]);
$form->setValues($REQUEST->postAll());
$form->transformValues();

if ($form->validate()) {
    $values = $form->getValues();
} else {
    $errors = $form->getFieldErrors('name');
}
```

| Step | Method | Purpose |
|------|--------|---------|
| 1 | `addField($name, $label, $transforms, $constraints)` | Register field |
| 2 | `setValues($array)` | Load `$_POST` (or test data) |
| 3 | `transformValues()` | Run transforms before validation |
| 4 | `validate()` | Run constraints; returns `true` / `false` |
| 5 | `getValues()` / `getFieldErrors($name)` | Read results |

## Handler walkthrough (GET + POST)

Typical edit handler co-located with `form.tpl.php`. Routes are registered explicitly in `htdocs/index.php` — see [Router — UUID resource routes](router.md#uuid-resource-routes-application-pattern).

```php
// htdocs/users/edit/index.php
use PureFramework\Csrf;
use PureFramework\Display;
use PureFramework\Form;
use PureFramework\TrimTransform;
use function PureFramework\html;

require_once dirname(__DIR__, 2) . '/../includes/entities/user.php';

$isNew = !$REQUEST->hasParam('user_uuid');
$uuid = $isNew ? false : $REQUEST->uuidParam('user_uuid');
if (!$isNew && $uuid === false) {
    Display::notFound();
}

if ($REQUEST->isPost()) {
    if (!Csrf::verify($REQUEST)) {
        http_response_code(403);
        exit;
    }

    $form = new Form();
    $form->addField('name', 'Name', [new TrimTransform()], [new UserConstraint()->forField('name')]);
    $form->addField('email', 'Email', [new TrimTransform()], [new UserConstraint()->forField('email')]);
    $form->setValues($REQUEST->postAll());
    $form->transformValues();

    if ($form->validate()) {
        user_save($isNew ? '' : $uuid, $form->getValues());
        Display::redirect($isNew ? '/users' : '/users/' . $uuid);
    }

    Display::page('form.tpl.php', [
        'title' => $isNew ? 'New user' : 'Edit user',
        'form' => $form,
    ]);
    exit;
}

// GET — new or edit
$row = !$isNew ? user_fetch($uuid) : null;
if (!$isNew && $row === null) {
    Display::notFound();
}

$form = new Form();
$form->addField('name', 'Name', [new TrimTransform()], [new UserConstraint()->forField('name')]);
$form->addField('email', 'Email', [new TrimTransform()], [new UserConstraint()->forField('email')]);
if ($row !== null) {
    $form->setValues(['name' => $row->name, 'email' => $row->email]);
}

Display::page('form.tpl.php', [
    'title' => $isNew ? 'New user' : 'Edit user',
    'form' => $form,
]);
```

**Template** (`form.tpl.php`):

```php
<?php use function PureFramework\html; ?>
<form method="post">
    <?php echo PureFramework\Csrf::field(); ?>
    <?php $nameField = $form->getField('name'); ?>
    <label><?= html($nameField->label) ?></label>
    <input name="name" value="<?= html($form->getValue('name')) ?>"<?= $nameField->required ? ' required' : '' ?>>
    <?php foreach ($form->getFieldErrors('name') as $error): ?>
        <?php foreach ($error->getMessages() as $message): ?>
            <p class="error"><?= html($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <button type="submit">Save</button>
</form>
```

**Checklist for POST handlers**

- Verify CSRF when the form mutates state — [CSRF](csrf.md)
- `setValues($REQUEST->postAll())` — POST only, not query string
- `transformValues()` then `validate()` before any DB write
- `html()` on every user-controlled value echoed in HTML — [Templates and layout](templates-and-layout.md)
- `Display::redirect()` after successful save

## Template access

Use explicit accessors in templates — not magic properties:

| Access | Meaning |
|--------|---------|
| `$form->getValue('field_name')` or `$form->field_name` | Current value (`__get` reads values only) |
| `$form->getField('field_name')->label` | Field label |
| `$form->getField('field_name')->required` | Whether a `required` constraint exists on the field |
| `$form->getFieldErrors('field_name')` | List of `FormError` objects after validation |
| `$error->getMessages()` | Human-readable strings from each `FormError` |

Messages come from constraint definitions (`ConstraintViolationMessage`). They are ready to echo with `html()` — no catalog required. To map violation **types** or custom keys through a phrase file, use `Phrase` / `__()` — [Phrase catalog](phrase.md).

Form-level errors from entity/API responses use `$form->getFormErrors()` and `$form->hasFormErrors()`.

## ConstraintEntity

Define field rules in entity-specific classes:

```php
use PureFramework\ConstraintEntity;

class UserConstraint extends ConstraintEntity
{
    public function __construct()
    {
        $this->defineField('email', [new EmailConstraint()]);
        $this->defineField('name', [new RequiredConstraint()]);
    }
}
```

Pass constraints into `addField()`:

- **Whole entity** — `[new UserConstraint()]` (validates all defined fields present in values)
- **Single field** — `[new UserConstraint()->forField('name')]` (typical for forms)

## ConstraintType

Extend `ConstraintType` for reusable validators. Implement `validate($value, $args, $context)`:

- Return **`null`** on success
- Return **`ConstraintViolation`** (or let `ConstraintViolation::merge()` combine lists) on failure

```php
use PureFramework\ConstraintType;
use PureFramework\ConstraintViolation;

class RequiredConstraint extends ConstraintType
{
    public function validate($value, $args = null, $context = null)
    {
        if ($value === null || $value === '') {
            $args = $this->args($args);
            return new ConstraintViolation('required', '{label} is required', $args);
        }

        return null;
    }
}
```

`ConstraintType` derives a short **`type`** name from the class basename (`RequiredConstraint` → `required`, including when the class is namespaced). Use `$form->getField('name')->required` or `hasType('required')` on the field’s constraints.

### Context and args

`Form::validate($fieldNames, $context)` passes `$context` through to each constraint. Use it for labels or related row data:

```php
$form->validate([], ['label' => 'Email address']);
```

Merge default args in the constraint constructor (`parent::__construct(['label' => 'Email'])`) or in `validate()` via `$this->args($args)`.

### Constraint cheat sheet

| Task | Approach |
|------|----------|
| Required field | `ConstraintViolation` when empty; type `required` |
| Email format | App `EmailConstraint` with `filter_var(FILTER_VALIDATE_EMAIL)` |
| UUID format | `Util::sanitizeUuid($value) === false` → violation |
| Max length | `strlen($value) > $max` with tokens `{max}` in message |
| Allowed values | `in_array($value, $allowed, true)` |
| Stop after first failure | `new ConstraintViolation(..., $stopProcessing = true)` or violation `stopProcessing` flag |
| Multiple rules per field | Several classes in `defineField()` array → `ConstraintList` runs in order |
| Cross-field rules | Validate in entity function after `Form::validate()`, or custom `ConstraintEntity::validate()` override |

| Class | Role |
|-------|------|
| `ConstraintViolation` | One or more messages; `merge()` combines list results |
| `ConstraintViolationMessage` | Single message with `{token}` replacement via `getMessage()` |
| `ConstraintList` | Ordered constraints for one field |
| `ConstraintEntity` | Map of field name → `ConstraintList` |

Application projects typically keep shared constraints in `includes/constraints.php` or `includes/validation/`.

## Transforms

Transforms normalize input **before** validation. The same `TransformInterface` implementations (for example `TrimTransform`) appear in two **separate** places:

| Layer | Class | When | Input |
|-------|-------|------|--------|
| **HTTP forms** | `Form` / `FormField` | `transformValues()` after `setValues()` | One field value at a time from POST |
| **Entity / API** | `TransformEntity` | Before `ConstraintEntity::runValidation()` in entity functions | Whole object or array of fields |

They are **not** wired together — registering transforms on `addField()` does not affect `TransformEntity`, and vice versa. Share transform **classes** (`TrimTransform`, your own `TransformInterface` types), not configuration.

### Form transforms

Pass transforms as the third argument to `addField()`:

```php
$form->addField('slug', 'Slug', [new TrimTransform()], [new SlugConstraint()]);
```

`transformValues()` runs before `validate()`.

Chain several transforms with a raw array or `TransformList`:

```php
use PureFramework\TransformList;

$form->addField('notes', 'Notes', [
    new TrimTransform(),
    new MyNormalizeNewlinesTransform(),
], [new NotesConstraint()]);

// equivalent:
$form->addField('notes', 'Notes', [
    new TransformList([new TrimTransform(), new MyNormalizeNewlinesTransform()]),
], [new NotesConstraint()]);
```

The framework ships **`TrimTransform`** only. Add application classes that implement `TransformInterface` for other rules.

### Entity transforms

Use **`TransformEntity`** in entity save/update paths — not in the form layer:

```php
$obj = MyEntityTransform::runApply($obj);
$errors = MyEntityConstraint::runValidation($obj);
```

Define per-field transforms with `defineField()` the same way as `ConstraintEntity`.

**Do not** HTML-escape in transforms — escaped strings are wrong for storage and for rich text. Escape untrusted output in templates with `Util::html()` or `html()`.

## API responses

See **[Responses](responses.md)** for the entity envelope convention, `data` vs `related`, and HTML/JSON handler patterns.

Quick reference for JSON handlers:

```php
$response = new SuccessResponse($data, $related);
HttpResponse::json($response);

HttpResponse::json(new ErrorResponse('Validation failed', $violations), 400);
```

`HttpResponse::json()` sets `Content-Type: application/json; charset=UTF-8`, the HTTP status code (default 200), echo the body, and **exit** by default. Pass `false` for the `$exit` argument to keep running (tests).

Both response types extend `Response`, which implements `JsonSerializable`.

## Components

| Class | Role |
|-------|------|
| `ConstraintEntity` | Field definitions for an entity |
| `ConstraintList` | Ordered constraints for one field |
| `ConstraintType` | Single validator (subclass) |
| `ConstraintViolation` | Error tokens and messages |
| `Form` / `FormField` / `FormError` | HTTP form state and errors |
| `TransformList` / `TrimTransform` | Input normalization (chain or trim whitespace) |
| `TransformEntity` | Per-field transforms on objects/arrays in entity functions |
| `Phrase` | Optional message catalog — [phrase.md](phrase.md) |

## See also

- [Router](router.md) — `$REQUEST->post()`, `uuidParam()`, route registration
- [CSRF](csrf.md) — POST form tokens
- [Phrase](phrase.md) — optional catalog for template copy and error keys
- [Security](security.md) — session and XSS
- [Templates and layout](templates-and-layout.md) — `Display::page()`, `html()`
