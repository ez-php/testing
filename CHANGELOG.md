# Changelog

All notable changes to `ez-php/testing` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Changed
- Updated `ez-php/http` dependency constraint to `^1.2`

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `ApplicationTestCase` — bootstraps the full application container for each test; provides `app()` and `make()` helpers
- `DatabaseTestCase` — extends `ApplicationTestCase`; wraps each test in a database transaction that is rolled back on teardown, keeping the schema clean without truncation
- `HttpTestCase` — extends `ApplicationTestCase`; provides `get()`, `post()`, `put()`, `patch()`, `delete()` helpers that dispatch requests through the full middleware and routing stack
- `TestResponse` — wraps the HTTP `Response` with assertion helpers: `assertStatus()`, `assertJson()`, `assertSee()`, `assertHeader()`, `assertRedirect()`
- `ModelFactory` — fluent factory for creating model fixtures; supports state overrides, sequences, and batch creation
