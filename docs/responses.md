# Response envelope

`SuccessResponse` and `ErrorResponse` wrap entity and API outcomes in a consistent shape. Handlers, CLI scripts, and JSON endpoints can branch on `$result->isSuccess()` without per-function return-type conventions.

Related: [Forms and validation](forms-and-validation.md) · [Architecture](architecture.md) · [Example site](example-site.md)

## Shape

Both types extend `Response` and serialize to JSON as:

```json
{
  "status": "success",
  "data": …,
  "related": …
}
```

| Field | Role |
|-------|------|
| `status` | `"success"` or `"error"` — use `$response->isSuccess()` / `$response->isError()` |
| `data` | Primary payload: UUID string, row object, error message, etc. |
| `related` | Optional secondary payload (see below) |

Constructors accept `(mixed $data = null, mixed $related = null)` for both success and error.

```php
use PureFramework\SuccessResponse;
use PureFramework\ErrorResponse;

$ok = new SuccessResponse($primary);
$bad = new ErrorResponse('Something failed', $context);
```

## The `related` field

`related` is not only for errors. Use it whenever the response has **secondary information** alongside the primary `data` value.

### On success

Attach objects that belong to or elaborate on `data`, often linked by ID:

```php
// Create: primary = new UUID; related = hydrated row or summary
return new SuccessResponse($uuid, ['record' => $row]);

// Fetch: primary = main row; related = child rows or joined entities
return new SuccessResponse($order, ['lines' => $lineItems]);

// List with aggregate meta
return new SuccessResponse($rows, ['total' => $count, 'page' => $page]);
```

JSON consumers treat `data` as the main result and `related` as optional extras (included records, pagination, side-loaded associations).

### On error

Attach structured detail for the caller to interpret:

```php
// Validation: primary = summary message; related = field violations
return new ErrorResponse('Validation failed', $violations);

// DB failure: related = debug context (avoid exposing internals in production APIs)
return new ErrorResponse('Unable to save', ['code' => $pdoCode]);
```

For HTML handlers, map `related` to form field errors or flash context. The example todo app uses helpers like `account_create_error_message(Response $response)` to turn violation maps into user-visible strings.

## Entity layer convention

Recommend **`SuccessResponse|ErrorResponse`** as the return type for entity functions that can fail in a way the caller must handle:

| Operation | Envelope | Typical `data` | Typical `related` |
|-----------|----------|----------------|-------------------|
| Create | Yes | New UUID or row | Optional created row snapshot |
| Update / delete | Yes | UUID or bool | Rare |
| Fetch (public API) | Optional | Row object | Optional joined / child data |
| Internal lookup | Optional plain `?object` | — | — |

**Mutations** (create, update, delete) should always use the envelope so handlers and scripts share one branch pattern:

```php
function todo_create(string $accountUuid, array $data): SuccessResponse|ErrorResponse
{
    $invalid = TodoConstraint::runValidation($data);
    if ($invalid) {
        return new ErrorResponse('Validation failed', $invalid);
    }

    // … insert …

    return new SuccessResponse($todo->todo_uuid);
}
```

**Reads** may return plain `?object` for simple internal checks (`account_fetch_by_username()` → row or `null`). Use the envelope when “not found” or “forbidden” should carry a message, or when the response includes **`related`** side data (e.g. account plus preferences).

Class-based entities (static methods returning the envelope for every public method) and procedural functions (envelope on writes, plain reads) are both valid — pick one style per app and document it.

### Helper wrapper

Applications may wrap the pattern in a small helper:

```php
function success_or_error(bool $test, mixed $data = null, mixed $error = null, mixed $related = null): SuccessResponse|ErrorResponse
{
    return $test
        ? new SuccessResponse($data, $related)
        : new ErrorResponse($error, $related);
}
```

## Handler patterns

### HTML

```php
$result = account_create($form->getValues());

if ($result->isSuccess()) {
    Display::redirect('/todos');
} elseif ($result->isError()) {
    $registerError = account_create_error_message($result);
}
```

Use `$result->data` for the primary value and `$result->related` when building richer error UI or success follow-up.

### JSON

```php
$result = account_create($payload);

if ($result->isSuccess()) {
    HttpResponse::json($result);
    // or: HttpResponse::json($result, 201);
}

HttpResponse::json($result, 400);
```

`HttpResponse::json()` sets `Content-Type: application/json; charset=UTF-8`, the HTTP status code (default 200), encodes the payload, echoes the body, and **exits** by default. Pass `false` for the `$exit` argument to keep running (tests).

For status without a JSON body (rare), use `HttpResponse::status(404)`.

## API summary

| Class / method | Purpose |
|----------------|---------|
| `SuccessResponse($data, $related)` | Successful outcome envelope |
| `ErrorResponse($data, $related)` | Failed outcome envelope |
| `$response->isSuccess()` / `isError()` | Boolean status checks |
| `$response->json()` | JSON string (logging, CLI, tests) |
| `HttpResponse::json($payload, $statusCode, $exit)` | Emit JSON response |
| `HttpResponse::status($statusCode)` | Set HTTP status only |

Both response types implement `JsonSerializable`.

## See also

- [Forms and validation](forms-and-validation.md) — constraints produce violation maps for `ErrorResponse` `related`
- [Templates and layout](templates-and-layout.md) — HTML uses `Display`, not JSON envelopes
- [CSRF](csrf.md) — failed verification: choose 403, redirect, or `ErrorResponse` in JSON handlers

## HTTP vs envelope

| Layer | Class | Role |
|-------|-------|------|
| Envelope | `SuccessResponse` / `ErrorResponse` | Business outcome (`data`, `related`, `JsonSerializable`) |
| HTTP | `HttpResponse` | Status codes, JSON emission |
| HTML | `Display` | Pages, redirects, 404 templates |
