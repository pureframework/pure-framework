# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2026-06-06

### Added

- **Database** — `DB::objectUpdateFactory()` builds partial row objects for UPDATE; honors typed DTO `$updateSkip` (auto-increment PK, `created`, `{table}_uuid`).
- **Codegen** — typed `--typed` output emits `$updateSkip` alongside existing `$insertSkip`.

## [1.3.1] - 2026-06-05

### Fixed

- **Codegen** — `parseSqlToConfig()` no longer treats `PRIMARY KEY`, `UNIQUE KEY`, `KEY`, and similar index lines as table columns (fixes typed `--typed` output when schemas define indexes).

### Changed

- Docs, scaffold, and security policy updated for **1.3.x** (`^1.3`, `1.3.x-dev` branch alias).
- **responses.md** — document explicit `SuccessResponse` / `ErrorResponse` returns instead of a `success_or_error()` helper.

## [1.3.0] - 2026-06-05

### Changed

- **Response** — renamed `success()` / `error()` to `isSuccess()` / `isError()` for clearer boolean predicate naming.

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
