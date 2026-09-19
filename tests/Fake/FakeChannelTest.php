<?php

declare(strict_types=1);

namespace Tests\Fake;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Testing\Fake\FakeChannel;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

final class FakeChannelUser implements NotifiableInterface
{
    public function routeNotificationFor(string $channel): string
    {
        return 'user-1';
    }
}

final class FakeChannelInvoicePaid implements NotificationInterface
{
    public function __construct(public readonly int $invoiceId = 1)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }
}

final class FakeChannelOtherNotification implements NotificationInterface
{
    public function via(): array
    {
        return ['mail'];
    }
}

#[CoversClass(FakeChannel::class)]
final class FakeChannelTest extends TestCase
{
    public function test_it_records_what_would_have_been_delivered(): void
    {
        $channel = new FakeChannel();
        $user = new FakeChannelUser();
        $notification = new FakeChannelInvoicePaid(7);

        $channel->send($user, $notification);

        self::assertSame([['notifiable' => $user, 'notification' => $notification]], $channel->sent());
        $channel->assertSentCount(1);
    }

    public function test_assert_sent_to_matches_notifiable_class_and_callback(): void
    {
        $channel = new FakeChannel();
        $user = new FakeChannelUser();
        $channel->send($user, new FakeChannelInvoicePaid(7));

        $channel->assertSentTo($user, FakeChannelInvoicePaid::class);
        $channel->assertSentTo($user, FakeChannelInvoicePaid::class, static fn (NotificationInterface $n): bool => $n instanceof FakeChannelInvoicePaid && $n->invoiceId === 7);
        $this->addToAssertionCount(1);
    }

    public function test_assert_sent_to_fails_for_another_notifiable(): void
    {
        $channel = new FakeChannel();
        $channel->send(new FakeChannelUser(), new FakeChannelInvoicePaid());

        $this->expectException(AssertionFailedError::class);

        $channel->assertSentTo(new FakeChannelUser(), FakeChannelInvoicePaid::class);
    }

    public function test_assert_sent_to_fails_for_another_notification_class_or_callback(): void
    {
        $channel = new FakeChannel();
        $user = new FakeChannelUser();
        $channel->send($user, new FakeChannelInvoicePaid());

        try {
            $channel->assertSentTo($user, FakeChannelOtherNotification::class);
            self::fail('a different notification class must not match');
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(AssertionFailedError::class);
        $channel->assertSentTo($user, FakeChannelInvoicePaid::class, static fn (NotificationInterface $n): bool => false);
    }

    public function test_assert_nothing_sent(): void
    {
        $channel = new FakeChannel();
        $channel->assertNothingSent();
        $channel->assertSentCount(0);

        $channel->send(new FakeChannelUser(), new FakeChannelInvoicePaid());

        $this->expectException(AssertionFailedError::class);
        $channel->assertNothingSent();
    }

    public function test_assert_sent_count_fails_on_mismatch(): void
    {
        $channel = new FakeChannel();

        $this->expectException(AssertionFailedError::class);

        $channel->assertSentCount(1);
    }
}
