<?php

declare(strict_types=1);

namespace EzPhp\Testing\Fake;

use Closure;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use PHPUnit\Framework\Assert;

/**
 * Class FakeMailer
 *
 * Recording `MailerInterface`: nothing is delivered, every `send()` is kept so a test
 * can assert which mail the code under test sent.
 *
 *     $mailer = new FakeMailer();
 *     (new Signup($mailer))->register('a@b.c');
 *     $mailer->assertSent(WelcomeMail::class, fn (Mailable $m) => $m->getToAddress() === 'a@b.c');
 *
 * `ez-php/mail` is a soft dependency (`suggest`): this class is only autoloaded when used.
 *
 * @package EzPhp\Testing\Fake
 */
final class FakeMailer implements MailerInterface
{
    /**
     * @var list<Mailable>
     */
    private array $sent = [];

    /**
     * @param Mailable $mailable
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $this->sent[] = $mailable;
    }

    /**
     * Mails sent so far, optionally only instances of one class.
     *
     * @param class-string<Mailable>|null $mailableClass
     *
     * @return list<Mailable>
     */
    public function sent(?string $mailableClass = null): array
    {
        if ($mailableClass === null) {
            return $this->sent;
        }

        return array_values(array_filter($this->sent, static fn (Mailable $m): bool => $m instanceof $mailableClass));
    }

    /**
     * Assert that a mail of the given class was sent (matching the callback, when given).
     *
     * @param class-string<Mailable>            $mailableClass
     * @param (Closure(Mailable): bool)|null    $callback
     *
     * @return void
     */
    public function assertSent(string $mailableClass, ?Closure $callback = null): void
    {
        Assert::assertNotSame(
            [],
            $this->matching($mailableClass, $callback),
            "The expected [{$mailableClass}] mail was not sent" . ($callback !== null ? ' (no mail matched the callback).' : '.'),
        );
    }

    /**
     * Assert that no mail of the given class (matching the callback) was sent.
     *
     * @param class-string<Mailable>         $mailableClass
     * @param (Closure(Mailable): bool)|null $callback
     *
     * @return void
     */
    public function assertNotSent(string $mailableClass, ?Closure $callback = null): void
    {
        Assert::assertSame([], $this->matching($mailableClass, $callback), "The unexpected [{$mailableClass}] mail was sent.");
    }

    /**
     * Assert that exactly $count mails were sent in total.
     *
     * @param int $count
     *
     * @return void
     */
    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, 'Expected ' . $count . ' mail(s) to be sent, but ' . count($this->sent) . ' were.');
    }

    /**
     * Assert that no mail was sent.
     *
     * @return void
     */
    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, 'Mails were sent unexpectedly: ' . implode(', ', array_map(static fn (Mailable $m): string => $m::class, $this->sent)));
    }

    /**
     * @param class-string<Mailable>         $mailableClass
     * @param (Closure(Mailable): bool)|null $callback
     *
     * @return list<Mailable>
     */
    private function matching(string $mailableClass, ?Closure $callback): array
    {
        return array_values(array_filter(
            $this->sent($mailableClass),
            static fn (Mailable $m): bool => $callback === null || $callback($m) === true,
        ));
    }
}
