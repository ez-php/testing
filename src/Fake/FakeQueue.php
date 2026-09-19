<?php

declare(strict_types=1);

namespace EzPhp\Testing\Fake;

use Closure;
use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Class FakeQueue
 *
 * Recording `QueueInterface` for tests: jobs pushed by the code under test are kept
 * in memory (and never run), so a test can assert *what* was dispatched without a
 * driver or a worker.
 *
 *     $queue = new FakeQueue();
 *     $service = new Signup($queue);
 *     $service->register('a@b.c');
 *     $queue->assertPushed(SendWelcomeMail::class, fn (SendWelcomeMail $job) => $job->to === 'a@b.c');
 *
 * Only depends on `ez-php/contracts`. `pop()` hands the recorded jobs back in FIFO
 * order per queue, so a test can also feed them to a Worker if it wants to.
 *
 * @package EzPhp\Testing\Fake
 */
final class FakeQueue implements QueueInterface
{
    /**
     * Every job ever pushed, in order.
     *
     * @var list<JobInterface>
     */
    private array $pushed = [];

    /**
     * Jobs still waiting to be popped, per queue name.
     *
     * @var array<string, list<JobInterface>>
     */
    private array $pending = [];

    /**
     * @var list<array{job: JobInterface, exception: Throwable}>
     */
    private array $failures = [];

    /**
     * @param JobInterface $job
     *
     * @return void
     */
    public function push(JobInterface $job): void
    {
        $this->pushed[] = $job;
        $this->pending[$job->getQueue()][] = $job;
    }

    /**
     * @param string $queue
     *
     * @return JobInterface|null
     */
    public function pop(string $queue = 'default'): ?JobInterface
    {
        return isset($this->pending[$queue]) ? array_shift($this->pending[$queue]) : null;
    }

    /**
     * @param string $queue
     *
     * @return int
     */
    public function size(string $queue = 'default'): int
    {
        return count($this->pending[$queue] ?? []);
    }

    /**
     * @param JobInterface $job
     * @param Throwable    $exception
     *
     * @return void
     */
    public function failed(JobInterface $job, Throwable $exception): void
    {
        $this->failures[] = ['job' => $job, 'exception' => $exception];
    }

    /**
     * Jobs pushed so far, optionally only instances of one class.
     *
     * @param class-string|null $jobClass
     *
     * @return list<JobInterface>
     */
    public function pushed(?string $jobClass = null): array
    {
        if ($jobClass === null) {
            return $this->pushed;
        }

        return array_values(array_filter($this->pushed, static fn (JobInterface $job): bool => $job instanceof $jobClass));
    }

    /**
     * Jobs reported through `failed()`.
     *
     * @return list<array{job: JobInterface, exception: Throwable}>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Assert that a job of the given class was pushed (at least once, or matching the callback).
     *
     * @param class-string                          $jobClass
     * @param (Closure(JobInterface): bool)|null    $callback Extra condition on the job.
     *
     * @return void
     */
    public function assertPushed(string $jobClass, ?Closure $callback = null): void
    {
        $matching = $this->matching($jobClass, $callback);

        Assert::assertNotSame(
            [],
            $matching,
            "The expected [{$jobClass}] job was not pushed" . ($callback !== null ? ' (no job matched the callback).' : '.'),
        );
    }

    /**
     * Assert that a job of the given class was pushed exactly $times times.
     *
     * @param class-string $jobClass
     * @param int          $times
     *
     * @return void
     */
    public function assertPushedTimes(string $jobClass, int $times): void
    {
        $count = count($this->pushed($jobClass));

        Assert::assertSame($times, $count, "Expected [{$jobClass}] to be pushed {$times} time(s), but it was pushed {$count} time(s).");
    }

    /**
     * Assert that no job of the given class (matching the callback) was pushed.
     *
     * @param class-string                       $jobClass
     * @param (Closure(JobInterface): bool)|null $callback
     *
     * @return void
     */
    public function assertNotPushed(string $jobClass, ?Closure $callback = null): void
    {
        Assert::assertSame([], $this->matching($jobClass, $callback), "The unexpected [{$jobClass}] job was pushed.");
    }

    /**
     * Assert that nothing was pushed at all.
     *
     * @return void
     */
    public function assertNothingPushed(): void
    {
        Assert::assertSame([], $this->pushed, 'Jobs were pushed unexpectedly: ' . implode(', ', array_map(static fn (JobInterface $j): string => $j::class, $this->pushed)));
    }

    /**
     * @param class-string                       $jobClass
     * @param (Closure(JobInterface): bool)|null $callback
     *
     * @return list<JobInterface>
     */
    private function matching(string $jobClass, ?Closure $callback): array
    {
        return array_values(array_filter(
            $this->pushed($jobClass),
            static fn (JobInterface $job): bool => $callback === null || $callback($job) === true,
        ));
    }
}
