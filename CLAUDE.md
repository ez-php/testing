# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3311** | **6383** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/testing

Framework-independent test utilities for ez-php — `TestResponse` and `EntityFactory`.

This module is a **dev-time dependency**. Users add it to `require-dev` in their application or module. It has **no dependency on `ez-php/framework`** — it can be used by standalone modules (ORM, validation, cache, …) without pulling in the full framework stack.

The framework-coupled base classes (`ApplicationTestCase`, `DatabaseTestCase`, `HttpTestCase`) live in the companion package `ez-php/testing-application`, which does depend on `ez-php/framework`.

---

## Source Structure

```
src/
├── TestResponse.php   — Wraps Response with fluent PHPUnit assertion helpers
└── EntityFactory.php  — Builds and optionally persists Entity instances with default and override attributes

tests/
├── TestCase.php              — Minimal PHPUnit base
├── EntityFactoryTest.php     — Tests make/create/makeMany/createMany, callable defaults, overrides
└── TestResponseTest.php      — Tests all assertion methods — passing and failing cases
```

---

## Key Classes and Responsibilities

### TestResponse (`src/TestResponse.php`)

Final wrapper around `Response`. All assertion methods delegate to `PHPUnit\Framework\Assert` and return `$this` for fluent chaining.

| Method | Asserts |
|---|---|
| `assertStatus(int)` | `$response->status() === $status` |
| `assertOk()` | `status === 200` |
| `assertNotFound()` | `status === 404` |
| `assertRedirect(?string)` | `300 <= status < 400`; if location given, also checks `Location` header |
| `assertSee(string)` | body contains the substring |
| `assertJson(array)` | body decodes to JSON and equals the array via `assertSame` |
| `assertHeader(string, ?string)` | header key exists; if value given, also checks exact match |

---

### EntityFactory (`src/EntityFactory.php`)

Generic factory. `@template T of Entity`. Default attribute values may be scalar or callable; callables are invoked once per instance. Persistence is delegated to an injected `AbstractRepository`.

| Method | Behaviour |
|---|---|
| `make(overrides)` | Creates entity instance without persisting |
| `create(overrides)` | Creates and calls `$repo->save()` on the entity |
| `makeMany(count, overrides)` | Returns `list<T>` without persisting |
| `createMany(count, overrides)` | Returns `list<T>`, each persisted via the repository |

**Requires a repository** before `create()` or `createMany()` is called. `make()` and `makeMany()` do not need a database connection.

---

## Design Decisions and Constraints

- **PHPUnit in `require` (not `require-dev`)** — `TestResponse` uses `PHPUnit\Framework\Assert`. Since this package is consumed exclusively in test suites, PHPUnit is a first-class runtime dependency (from the test runner's perspective, this package IS production code that users consume).
- **No `ez-php/framework` dependency** — This package must remain usable by standalone modules (ORM, validation, cache, …) without pulling in the full Application stack. The framework-coupled base classes live in `ez-php/testing-application`.
- **`EntityFactory` defaults are `array<string, mixed>`** — Callable detection uses `is_callable()`. This avoids a separate `Closure` type union while still supporting any callable (closure, invokable, etc.).

---

## Testing Approach

- **No external infrastructure required** — All module tests run in-process. No MySQL, Redis, or Application bootstrap needed.
- **`EntityFactoryTest` uses a `PdoTestDatabase` helper** — Mirrors the `PdoDatabase` helper in `ez-php/orm` tests. Avoids depending on `ez-php/framework`'s `Database` class from the test infrastructure.
- **`#[CoversClass]` required** — Only this module's own `src/` classes are tracked for coverage.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| `ApplicationTestCase`, `DatabaseTestCase`, `HttpTestCase` | `ez-php/testing-application` |
| Fixture data for a specific application | Application's own test directory |
| Database seeders | Application layer |
| Mocking framework or test doubles | PHPUnit's built-in mocking, or application test directory |
| Request / Response value objects | `ez-php/http` |
| Application lifecycle / bootstrap | `ez-php/framework` |
| ORM / Model logic | `ez-php/orm` |
| Assertion helpers for domain-specific types | Application test layer |
| HTTP fake client (for outgoing HTTP) | `ez-php/http-client` mock transport |
