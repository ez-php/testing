<?php

declare(strict_types=1);

namespace Tests\Fake;

use EzPhp\Mail\Mailable;
use EzPhp\Testing\Fake\FakeMailer;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

final class FakeMailerWelcomeMail extends Mailable
{
}

final class FakeMailerOtherMail extends Mailable
{
}

#[CoversClass(FakeMailer::class)]
final class FakeMailerTest extends TestCase
{
    public function test_it_records_sent_mail_without_delivering(): void
    {
        $mailer = new FakeMailer();
        $mail = (new FakeMailerWelcomeMail())->to('a@example.test')->subject('Hi');

        $mailer->send($mail);

        self::assertSame([$mail], $mailer->sent());
        self::assertSame([$mail], $mailer->sent(FakeMailerWelcomeMail::class));
        self::assertSame([], $mailer->sent(FakeMailerOtherMail::class));
    }

    public function test_assert_sent_with_callback(): void
    {
        $mailer = new FakeMailer();
        $mailer->send((new FakeMailerWelcomeMail())->to('a@example.test'));

        $mailer->assertSent(FakeMailerWelcomeMail::class);
        $mailer->assertSent(FakeMailerWelcomeMail::class, static fn (Mailable $m): bool => $m->getToAddress() === 'a@example.test');
        $mailer->assertNotSent(FakeMailerWelcomeMail::class, static fn (Mailable $m): bool => $m->getToAddress() === 'b@example.test');
        $this->addToAssertionCount(1);
    }

    public function test_assert_sent_fails_when_the_callback_never_matches(): void
    {
        $mailer = new FakeMailer();
        $mailer->send((new FakeMailerWelcomeMail())->to('a@example.test'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('no mail matched the callback');

        $mailer->assertSent(FakeMailerWelcomeMail::class, static fn (Mailable $m): bool => false);
    }

    public function test_assert_not_sent_fails_when_it_was(): void
    {
        $mailer = new FakeMailer();
        $mailer->send(new FakeMailerWelcomeMail());

        $this->expectException(AssertionFailedError::class);

        $mailer->assertNotSent(FakeMailerWelcomeMail::class);
    }

    public function test_assert_sent_count_and_nothing_sent(): void
    {
        $mailer = new FakeMailer();
        $mailer->assertNothingSent();
        $mailer->assertSentCount(0);

        $mailer->send(new FakeMailerWelcomeMail());
        $mailer->assertSentCount(1);

        $this->expectException(AssertionFailedError::class);
        $mailer->assertNothingSent();
    }

    public function test_assert_sent_count_fails_on_mismatch(): void
    {
        $mailer = new FakeMailer();
        $mailer->send(new FakeMailerWelcomeMail());

        $this->expectException(AssertionFailedError::class);

        $mailer->assertSentCount(2);
    }
}
