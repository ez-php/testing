<?php

declare(strict_types=1);

namespace Tests\Fake;

use EzPhp\Events\EventDispatcher;
use EzPhp\Events\EventInterface;
use EzPhp\Testing\Fake\EventSpy;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

final class EventSpyUserRegistered implements EventInterface
{
    public function __construct(public readonly int $userId = 1)
    {
    }
}

final class EventSpyOtherEvent implements EventInterface
{
}

#[CoversClass(EventSpy::class)]
final class EventSpyTest extends TestCase
{
    public function test_it_records_dispatched_events(): void
    {
        $dispatcher = new EventDispatcher();
        $spy = EventSpy::attachTo($dispatcher);
        $event = new EventSpyUserRegistered(5);

        $dispatcher->dispatch($event);

        self::assertSame([$event], $spy->dispatched());
        self::assertSame([$event], $spy->dispatched(EventSpyUserRegistered::class));
        self::assertSame([], $spy->dispatched(EventSpyOtherEvent::class));
    }

    public function test_real_listeners_still_run(): void
    {
        $dispatcher = new EventDispatcher();
        $spy = EventSpy::attachTo($dispatcher);
        $called = 0;
        $dispatcher->listen(EventSpyUserRegistered::class, static function () use (&$called): void {
            $called++;
        });

        $dispatcher->dispatch(new EventSpyUserRegistered());

        self::assertSame(1, $called);
        $spy->assertDispatchedTimes(EventSpyUserRegistered::class, 1);
    }

    public function test_the_spy_sees_events_even_when_a_listener_stops_propagation(): void
    {
        $dispatcher = new EventDispatcher();
        $spy = EventSpy::attachTo($dispatcher);
        $dispatcher->listen(EventSpyUserRegistered::class, static fn (): bool => false);

        $dispatcher->dispatch(new EventSpyUserRegistered());

        $spy->assertDispatched(EventSpyUserRegistered::class);
    }

    public function test_assert_dispatched_with_callback(): void
    {
        $dispatcher = new EventDispatcher();
        $spy = EventSpy::attachTo($dispatcher);
        $dispatcher->dispatch(new EventSpyUserRegistered(5));

        $spy->assertDispatched(EventSpyUserRegistered::class, static fn (EventInterface $e): bool => $e instanceof EventSpyUserRegistered && $e->userId === 5);
        $spy->assertNotDispatched(EventSpyUserRegistered::class, static fn (EventInterface $e): bool => $e instanceof EventSpyUserRegistered && $e->userId === 6);
        $spy->assertNotDispatched(EventSpyOtherEvent::class);
        $this->addToAssertionCount(1);
    }

    public function test_assert_dispatched_fails_when_missing(): void
    {
        $spy = EventSpy::attachTo(new EventDispatcher());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('was not dispatched');

        $spy->assertDispatched(EventSpyUserRegistered::class);
    }

    public function test_assert_not_dispatched_fails_when_it_was(): void
    {
        $dispatcher = new EventDispatcher();
        $spy = EventSpy::attachTo($dispatcher);
        $dispatcher->dispatch(new EventSpyOtherEvent());

        $this->expectException(AssertionFailedError::class);

        $spy->assertNotDispatched(EventSpyOtherEvent::class);
    }

    public function test_assert_dispatched_times_and_nothing_dispatched(): void
    {
        $dispatcher = new EventDispatcher();
        $spy = EventSpy::attachTo($dispatcher);
        $spy->assertNothingDispatched();

        $dispatcher->dispatch(new EventSpyUserRegistered());
        $dispatcher->dispatch(new EventSpyUserRegistered());
        $spy->assertDispatchedTimes(EventSpyUserRegistered::class, 2);

        try {
            $spy->assertDispatchedTimes(EventSpyUserRegistered::class, 1);
            self::fail('a wrong count must fail');
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AssertionFailedError::class);
        $spy->assertNothingDispatched();
    }
}
