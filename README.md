# ez-php/testing

Test utilities for the [ez-php framework](https://github.com/ez-php) — base test cases, HTTP request helpers, response assertions, and a model factory.

## Requirements

- PHP 8.5+
- PHPUnit 13+
- ez-php/framework
- ez-php/http
- ez-php/orm

## Installation

```bash
composer require --dev ez-php/testing
```

## Classes

### `ApplicationTestCase`

Bootstraps the Application for each test. Override `getBasePath()` to point to your application root and `configureApplication()` to register providers or middleware before bootstrap.

```php
use EzPhp\Testing\ApplicationTestCase;

final class MyTest extends ApplicationTestCase
{
    protected function getBasePath(): string
    {
        return dirname(__DIR__);
    }

    protected function configureApplication(Application $app): void
    {
        $app->register(MyTestProvider::class);
    }

    public function testSomething(): void
    {
        $router = $this->app()->make(Router::class);
        // …
    }
}
```

### `DatabaseTestCase`

Extends `ApplicationTestCase`. Wraps each test in a database transaction that is rolled back on teardown — no table truncation required.

```php
use EzPhp\Testing\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    protected function getBasePath(): string
    {
        return dirname(__DIR__);
    }

    public function testUserIsSaved(): void
    {
        // writes during this test are rolled back after tearDown
        User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertCount(1, User::all());
    }
}
```

### `HttpTestCase`

Extends `ApplicationTestCase`. Provides `get()`, `post()`, `put()`, `delete()`, and `request()` helpers that dispatch fake HTTP requests through the full application stack (middleware, router, handlers) and return a `TestResponse`.

```php
use EzPhp\Testing\HttpTestCase;

final class HomeControllerTest extends HttpTestCase
{
    protected function getBasePath(): string
    {
        return dirname(__DIR__);
    }

    public function testHomePage(): void
    {
        $this->get('/')->assertOk()->assertSee('Welcome');
    }
}
```

### `TestResponse`

Returned by `HttpTestCase` helpers. Fluent assertion API:

| Method | Description |
|---|---|
| `assertStatus(int)` | Exact status code |
| `assertOk()` | Status 200 |
| `assertNotFound()` | Status 404 |
| `assertRedirect(?string)` | 3xx; optional Location header |
| `assertSee(string)` | Body contains substring |
| `assertJson(array)` | Body decodes to exact array |
| `assertHeader(string, ?string)` | Header present; optional value |

### `ModelFactory`

Builds and optionally persists Model instances with default attributes. Callable defaults are invoked once per instance.

```php
use EzPhp\Testing\ModelFactory;

$factory = new ModelFactory(User::class, [
    'name'  => 'Alice',
    'email' => fn () => uniqid('user_') . '@example.com',
]);

$user  = $factory->make();             // not persisted
$user  = $factory->create();           // persisted via Model::save()
$users = $factory->makeMany(3);
$users = $factory->createMany(5, ['role' => 'admin']);
```

## Setup (standalone development)

```bash
cp .env.example .env
./start.sh
```
