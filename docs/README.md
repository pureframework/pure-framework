# Pure Framework documentation

Pure Framework is a thin PHP library for file-based routing, PDO database access, form validation, and templates. It is designed for applications where plain PHP, readable handlers, and minimal magic are priorities.

## Guides

| Document | Description |
|----------|-------------|
| [Getting started](getting-started.md) | Minimal site skeleton and first request |
| [Example site layout](example-site.md) | Directory conventions, config files, SQL, htdocs, `.htaccess` |
| [Installation](installation.md) | Composer install and bootstrap |
| [Configuration](configuration.md) | Application constants and extension points |
| [Architecture](architecture.md) | Request lifecycle and project layout |
| [Router](router.md) | Routes, handlers, and `$REQUEST` |
| [Database](database.md) | `DB` class, subclassing, codegen, DTO loading, `scripts/generate-dto-classes.php` |
| [Forms and validation](forms-and-validation.md) | `Form`, constraints, handler walkthrough, cheat sheet |
| [Responses](responses.md) | `SuccessResponse` / `ErrorResponse` envelope; `HttpResponse` for JSON APIs |
| [CSRF](csrf.md) | Optional `Csrf` tokens for forms and AJAX headers |
| [Session](session.md) | Cookie bootstrap, lazy start, destroy, regenerate |
| [Phrase](phrase.md) | Optional message catalog and `__()` helper |
| [Security](security.md) | HTTPS, production error handling, logging |
| [Templates and layout](templates-and-layout.md) | `Display` API, `html()` escaping, layout shell, co-located templates |
| [License](license.md) | MIT license terms |

## Quick links

- Package: [`pureframework/pure-framework` on Packagist](https://packagist.org/packages/pureframework/pure-framework) — auto-updated from GitHub via webhook
- Repository: [github.com/pureframework/pure-framework](https://github.com/pureframework/pure-framework)
- Namespace: `PureFramework\`
- Example config: [`examples/minimal-site/`](../examples/minimal-site/)
- Run tests: `composer test`

## Philosophy

- **Routes → handler PHP files → entity functions → templates**
- **No container, no ORM, no compile step** beyond Composer autoload
- **Auth and business rules stay in the application**, not in the library
