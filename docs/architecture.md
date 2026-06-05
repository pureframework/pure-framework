# Architecture

Pure Framework is intended to keep the core concepts of web site and app development close at hand to the developer. One of PHP's strengths is to deploy a .php file and visit the URL. It just runs. PHP was originally developed as a lightweight templating language. Pure Framework leverages that. There is no special templating language, just some light weight helpers for working with echo'd output.

The database layer is intended to keep you close at hand to the database. Define your SQL tables and Pure Framework will generate some simple DTO classes. Additional helpers allow for your to define constraints for these classes for validation. These same constraint configurations can also be leveraged for form validation.

## Request lifecycle

```text
htdocs/index.php
    → application init.php (autoload, config, session)
    → PureFramework\Router::run()
    → handler PHP file (chdir to handler directory)
    → includes/entities/*.php
    → PureFramework\Display (Layout / Template)
```

1. **`htdocs/index.php`** constructs a `Router`, registers routes in order, optionally overrides `setNotFoundHandler()` (default: `Display::notFound()`), and calls `run()`.
2. The **first matching route** wins. POST routes for a path should be registered before GET routes when patterns overlap.
3. **`executeHandler`** sets `$REQUEST`, `$ROUTE`, and `$HANDLER`, changes the working directory to the handler's folder, and `include`s the handler file. **`run()`** returns the include result (`mixed`), `false` when not-found handling is disabled, or does not return when the default `Display::notFound()` path runs.
4. Handlers use **procedural entity functions** and your app's `DB` subclass — not a built-in ORM.
5. Entity functions return **`SuccessResponse` / `ErrorResponse`** where the caller must handle failure; JSON handlers emit them with **`HttpResponse::json()`**, HTML handlers branch and use **`Display`** — see [responses.md](responses.md).

## Layer responsibilities

| Layer        | Package                                         | Application                              |
| ------------ | ----------------------------------------------- | ---------------------------------------- |
| Routing      | `Router`, `Request`                             | Route table in `htdocs/index.php`        |
| Persistence  | `DB`, optional `UuidDbTrait`                    | `class DB extends \PureFramework\DB`     |
| Validation   | `Form`, `ConstraintEntity`, `ConstraintType`    | Entity-specific constraint classes       |
| Presentation | `Display`, `Layout`, `Template`, `Util::html()` | `*.tpl.php`, `templates/site.layout.php` |
| Domain       | —                                               | `includes/entities/`, SQL, auth          |

## Design constraints

- **No dependency injection container** — globals and explicit `require` in app code.
- **No template engine requirement** — plain PHP templates.
- **No auth policy in core** — optional `Session` helpers for bootstrap only; login state and guards stay in application code ([session.md](session.md)).
- **Generated row classes** — optional plain PHP classes from SQL; business logic stays in entity functions.

## Generated vs hand-written code

| Artifact             | Source                                              |
| -------------------- | --------------------------------------------------- |
| Row property classes | `scripts/generate-dto-classes.php` or `vendor/bin/pure-generate-classes` — [database.md](database.md#application-script-scriptsgenerate-dto-classesphp) |
| Entity functions     | Hand-written in `includes/entities/` (option A) or `includes/entity/` classes (option B) |
| Handlers             | Hand-written next to templates under `htdocs/`      |

See [Templates and layout](templates-and-layout.md), [Database](database.md), [Forms and validation](forms-and-validation.md), and [Example site layout](example-site.md) for application structure and API detail.
