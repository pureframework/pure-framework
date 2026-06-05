# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-06-04

Initial public release of `pureframework/pure-framework`.

### Added

- **Router & Request** — file-based regex routing, `$REQUEST` / `$ROUTE` / `$HANDLER` globals, `uuidParam()` / `hasParam()`, default 404 via `Display::notFound()`.
- **Database** — PDO `DB` layer, optional `UuidDbTrait`, `objectFactory()` / `objectInsertFactory()`, `prepareDataArray()`.
- **Codegen** — `pure-generate-classes` CLI and `DbGenerateClasses` API; single cache file or PSR-4 per-file output; `--typed` for `@property` docblocks, typed properties, `$insertSkip`, and `$uuidProperty`; migration SQL skip rules (`{digits}_*.sql`).
- **Forms & validation** — `Form`, constraint types, transforms, violation messages.
- **Responses** — `SuccessResponse` / `ErrorResponse` envelopes; `HttpResponse::json()` and `HttpResponse::status()` for HTTP emission.
- **Display & templates** — `Display`, `Layout`, `Template`, co-located `.tpl.php` handlers, `html()` helper.
- **Session & CSRF** — cookie bootstrap, lazy start, destroy, regenerate; optional `Csrf` tokens.
- **Phrase** — optional message catalog and `__()` helper.
- **CLI** — `pure-new-site` scaffold; `generate-dto-classes.example.php` template for app-owned DTO codegen.
- **Documentation** — guides under `docs/` (architecture, router, database, forms, responses, example site layout, security).
- **Tests** — PHPUnit suite and GitHub Actions workflow.
