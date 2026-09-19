<?php

declare(strict_types=1);

namespace Tests\Fake;

use EzPhp\Contracts\JobInterface;
use EzPhp\Testing\Fake\FakeQueue;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

class FakeQueueTestJob implements JobInterface
{
    public function __construct(public readonly string $payload = '', private readonly string $queue = 'default')
    {
    }

    public function handle(): void
    {
    }

    public function fail(\Throwable $exception): void
    {
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getDelay(): int
    {
        return 0;
    }

    public function getMaxTries(): int
    {
        return 1;
    }

    public function getAttempts(): int
    {
        return 0;
    }

    public function incrementAttempts(): void
    {
    }
}

final class FakeQueueTestOtherJob extends FakeQueueTestJob
{
}

#[CoversClass(FakeQueue::class)]
final class FakeQueueTest extends TestCase
{
    public function test_it_records_pushed_jobs_without_running_them(): void
    {
        $queue = new FakeQueue();
        $job = new FakeQueueTestJob('a');

        $queue->push($job);

        self::assertSame([$job], $queue->pushed());
        self::assertSame(1, $queue->size());
    }

    public function test_assert_pushed_with_and_without_callback(): void
    {
        $queue = new FakeQueue();
        $queue->push(new FakeQueueTestJob('a'));

        $queue->assertPushed(FakeQueueTestJob::class);
        $queue->assertPushed(FakeQueueTestJob::class, static fn (JobInterface $j): bool => $j instanceof FakeQueueTestJob && $j->payload === 'a');
        $queue->assertNotPushed(FakeQueueTestJob::class, static fn (JobInterface $j): bool => $j instanceof FakeQueueTestJob && $j->payload === 'b');
        $queue->assertNotPushed(FakeQueueTestOtherJob::class);
        $this->addToAssertionCount(1);
    }

    public function test_assert_pushed_fails_when_nothing_matches(): void
    {
        $queue = new FakeQueue();
        $queue->push(new FakeQueueTestJob('a'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('was not pushed');

        $queue->assertPushed(FakeQueueTestJob::class, static fn (JobInterface $j): bool => false);
    }

    public function test_assert_not_pushed_fails_when_it_was(): void
    {
        $queue = new FakeQueue();
        $queue->push(new FakeQueueTestOtherJob());

        $this->expectException(AssertionFailedError::class);

        $queue->assertNotPushed(FakeQueueTestOtherJob::class);
    }

    public function test_assert_pushed_times(): void
    {
        $queue = new FakeQueue();
        $queue->push(new FakeQueueTestJob('1'));
        $queue->push(new FakeQueueTestJob('2'));

        $queue->assertPushedTimes(FakeQueueTestJob::class, 2);

        $this->expectException(AssertionFailedError::class);
        $queue->assertPushedTimes(FakeQueueTestJob::class, 1);
    }

    public function test_assert_nothing_pushed(): void
    {
        $queue = new FakeQueue();
        $queue->assertNothingPushed();

        $queue->push(new FakeQueueTestJob());

        $this->expectException(AssertionFailedError::class);
        $queue->assertNothingPushed();
    }

    public function test_pop_returns_jobs_fifo_per_queue_and_keeps_the_push_history(): void
    {
        $queue = new FakeQueue();
        $first = new FakeQueueTestJob('1');
        $second = new FakeQueueTestJob('2');
        $other = new FakeQueueTestJob('x', 'mail');
        $queue->push($first);
        $queue->push($other);
        $queue->push($second);

        self::assertSame($first, $queue->pop());
        self::assertSame($second, $queue->pop());
        self::assertNull($queue->pop());
        self::assertSame($other, $queue->pop('mail'));
        self::assertSame(0, $queue->size('mail'));
        self::assertCount(3, $queue->pushed(), 'popping does not erase what was pushed');
    }

    public function test_pushed_can_filter_by_class(): void
    {
        $queue = new FakeQueue();
        $queue->push(new FakeQueueTestJob());
        $queue->push(new FakeQueueTestOtherJob());

        self::assertCount(1, $queue->pushed(FakeQueueTestOtherJob::class));
        self::assertCount(2, $queue->pushed(FakeQueueTestJob::class));
    }

    public function test_failed_jobs_are_recorded(): void
    {
        $queue = new FakeQueue();
        $job = new FakeQueueTestJob();
        $exception = new \RuntimeException('x');

        $queue->failed($job, $exception);

        self::assertSame([['job' => $job, 'exception' => $exception]], $queue->failures());
    }
}
