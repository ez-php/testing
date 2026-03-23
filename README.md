# ez-php/testing

Framework-independent test utilities for [ez-php](https://github.com/ez-php) — response assertions and an entity factory.

> **Looking for `ApplicationTestCase`, `DatabaseTestCase`, or `HttpTestCase`?**
> Those live in [`ez-php/testing-application`](https://github.com/ez-php/testing-application), which boots the full framework stack.

## Requirements

- PHP 8.5+
- PHPUnit 13+
- ez-php/http
- ez-php/orm

## Installation

```bash
composer require --dev ez-php/testing
```

## Classes

### `TestResponse`

Wraps a `Response` with a fluent PHPUnit assertion API. Returned by `HttpTestCase` helpers in `ez-php/testing-application`.

| Method | Description |
|---|---|
| `assertStatus(int)` | Exact status code |
| `assertOk()` | Status 200 |
| `assertNotFound()` | Status 404 |
| `assertRedirect(?string)` | 3xx; optional Location header |
| `assertSee(string)` | Body contains substring |
| `assertJson(array)` | Body decodes to exact array |
| `assertHeader(string, ?string)` | Header present; optional value |

### `EntityFactory`

Builds and optionally persists Entity instances with default attributes. Callable defaults are invoked once per instance. Persistence is delegated to an `AbstractRepository`.

```php
use EzPhp\Testing\EntityFactory;

$factory = new EntityFactory(User::class, $userRepo, [
    'name'  => 'Alice',
    'email' => fn () => uniqid('user_') . '@example.com',
]);

$user  = $factory->make();             // not persisted
$user  = $factory->create();           // persisted via $userRepo->save()
$users = $factory->makeMany(3);
$users = $factory->createMany(5, ['role' => 'admin']);
```

## Setup (standalone development)

```bash
cp .env.example .env
./start.sh
```
