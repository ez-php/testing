<?php

declare(strict_types=1);

namespace EzPhp\Testing\Fake;

use Closure;
use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use PHPUnit\Framework\Assert;

/**
 * Class EventSpy
 *
 * Records every event that goes through an `EventDispatcher`, so a test can assert what
 * was dispatched. `EventDispatcher` is a final class without an interface, so this is a
 * spy attached to the real dispatcher (a wildcard listener) rather than a replacement for
 * it — the real listeners still run.
 *
 *     $dispatcher = new EventDispatcher();
 *     $spy = EventSpy::attachTo($dispatcher);
 *     (new Signup($dispatcher))->register('a@b.c');
 *     $spy->assertDispatched(UserRegistered::class);
 *
 * Limitation: anonymous-class events are not matched by wildcard listeners (see
 * `EventDispatcher`), so they are not recorded — use named event classes in tests.
 *
 * `ez-php/events` is a soft dependency (`suggest`): only autoloaded when used.
 *
 * @package EzPhp\Testing\Fake
 */
final class EventSpy
{
    /**
     * @var list<EventInterface>
     */
    private array $dispatched = [];

    /**
     * Attach a new spy to a dispatcher.
     *
     * @param EventDispatcher $dispatcher
     *
     * @return self
     */
    public static function attachTo(EventDispatcher $dispatcher): self
    {
        $spy = new self();
        $dispatcher->listen('*', static function (EventInterface $event) use ($spy): void {
            $spy->dispatched[] = $event;
        }, PHP_INT_MAX);

        return $spy;
    }

    /**
     * Events seen so far, optionally only instances of one class.
     *
     * @param class-string<EventInterface>|null $eventClass
     *
     * @return list<EventInterface>
     */
    public function dispatched(?string $eventClass = null): array
    {
        if ($eventClass === null) {
            return $this->dispatched;
        }

        return array_values(array_filter($this->dispatched, static fn (EventInterface $e): bool => $e instanceof $eventClass));
    }

    /**
     * Assert that an event of the given class was dispatched (matching the callback, when given).
     *
     * @param class-string<EventInterface>          $eventClass
     * @param (Closure(EventInterface): bool)|null  $callback
     *
     * @return void
     */
    public function assertDispatched(string $eventClass, ?Closure $callback = null): void
    {
        Assert::assertNotSame(
            [],
            $this->matching($eventClass, $callback),
            "The expected [{$eventClass}] event was not dispatched" . ($callback !== null ? ' (no event matched the callback).' : '.'),
        );
    }

    /**
     * Assert that an event of the given class was dispatched exactly $times times.
     *
     * @param class-string<EventInterface> $eventClass
     * @param int                          $times
     *
     * @return void
     */
    public function assertDispatchedTimes(string $eventClass, int $times): void
    {
        $count = count($this->dispatched($eventClass));

        Assert::assertSame($times, $count, "Expected [{$eventClass}] to be dispatched {$times} time(s), but it was dispatched {$count} time(s).");
    }

    /**
     * Assert that no event of the given class (matching the callback) was dispatched.
     *
     * @param class-string<EventInterface>         $eventClass
     * @param (Closure(EventInterface): bool)|null $callback
     *
     * @return void
     */
    public function assertNotDispatched(string $eventClass, ?Closure $callback = null): void
    {
        Assert::assertSame([], $this->matching($eventClass, $callback), "The unexpected [{$eventClass}] event was dispatched.");
    }

    /**
     * Assert that no event was dispatched at all.
     *
     * @return void
     */
    public function assertNothingDispatched(): void
    {
        Assert::assertSame([], $this->dispatched, 'Events were dispatched unexpectedly: ' . implode(', ', array_map(static fn (EventInterface $e): string => $e::class, $this->dispatched)));
    }

    /**
     * @param class-string<EventInterface>         $eventClass
     * @param (Closure(EventInterface): bool)|null $callback
     *
     * @return list<EventInterface>
     */
    private function matching(string $eventClass, ?Closure $callback): array
    {
        return array_values(array_filter(
            $this->dispatched($eventClass),
            static fn (EventInterface $e): bool => $callback === null || $callback($e) === true,
        ));
    }
}
