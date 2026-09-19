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

### Fakes

Recording doubles that let a test assert *what* the code under test did, without a driver, a mail server or a worker. The packages behind `FakeMailer`, `FakeChannel`, `EventSpy` and `FakeStorage` are optional (`suggest`) — install the one you use.

```php
use EzPhp\Testing\Fake\{FakeQueue, FakeMailer, FakeChannel, EventSpy, FakeStorage};

$queue = new FakeQueue();                       // QueueInterface (ez-php/contracts)
(new Signup($queue))->register('a@b.c');
$queue->assertPushed(SendWelcomeMail::class, fn ($job) => $job->to === 'a@b.c');
$queue->assertPushedTimes(SendWelcomeMail::class, 1);
$queue->assertNothingPushed();                  // or assertNotPushed(Class::class)

$mailer = new FakeMailer();                     // MailerInterface (ez-php/mail)
$mailer->assertSent(WelcomeMail::class, fn ($m) => $m->getToAddress() === 'a@b.c');
$mailer->assertSentCount(1);  $mailer->assertNothingSent();

$channel = new FakeChannel();                   // notification channel (ez-php/notification)
$channel->assertSentTo($user, InvoicePaid::class);

$spy = EventSpy::attachTo($dispatcher);         // ez-php/events — real listeners still run
$spy->assertDispatched(UserRegistered::class);  // assertDispatchedTimes / assertNotDispatched / assertNothingDispatched

$storage = new FakeStorage();                   // StorageInterface over storage's InMemoryDriver
$storage->assertExists('avatars/1.png');        // assertMissing / assertContents / assertEmpty
```

Each `assert*` takes an optional `Closure` to filter on the job/mail/event, and fails with a normal PHPUnit assertion message. `EventDispatcher` is `final`, so events are observed with a spy rather than replaced; use named event classes (anonymous ones are not matched by the dispatcher's wildcard listeners).

## Setup (standalone development)

```bash
cp .env.example .env
./start.sh
```
