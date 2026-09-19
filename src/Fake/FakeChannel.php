<?php

declare(strict_types=1);

namespace EzPhp\Testing\Fake;

use Closure;
use EzPhp\Notification\ChannelInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationInterface;
use PHPUnit\Framework\Assert;

/**
 * Class FakeChannel
 *
 * Recording notification channel: register it under the channel name the notification
 * uses (e.g. `'mail'`) and assert what would have been delivered.
 *
 *     $channel = new FakeChannel();
 *     $notifier = new Notifier(['mail' => $channel]);
 *     $notifier->send($user, new InvoicePaid($invoice));
 *     $channel->assertSentTo($user, InvoicePaid::class);
 *
 * `ez-php/notification` is a soft dependency (`suggest`): only autoloaded when used.
 *
 * @package EzPhp\Testing\Fake
 */
final class FakeChannel implements ChannelInterface
{
    /**
     * @var list<array{notifiable: NotifiableInterface, notification: NotificationInterface}>
     */
    private array $sent = [];

    /**
     * @param NotifiableInterface   $notifiable
     * @param NotificationInterface $notification
     *
     * @return void
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->sent[] = ['notifiable' => $notifiable, 'notification' => $notification];
    }

    /**
     * Everything delivered so far.
     *
     * @return list<array{notifiable: NotifiableInterface, notification: NotificationInterface}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * Assert that a notification of the given class was sent to the notifiable (same instance),
     * optionally also matching a callback.
     *
     * @param NotifiableInterface                     $notifiable
     * @param class-string<NotificationInterface>     $notificationClass
     * @param (Closure(NotificationInterface): bool)|null $callback
     *
     * @return void
     */
    public function assertSentTo(NotifiableInterface $notifiable, string $notificationClass, ?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->sent,
            static fn (array $entry): bool => $entry['notifiable'] === $notifiable
                && $entry['notification'] instanceof $notificationClass
                && ($callback === null || $callback($entry['notification']) === true),
        );

        Assert::assertNotSame([], $matching, "The expected [{$notificationClass}] notification was not sent to that notifiable.");
    }

    /**
     * Assert that exactly $count notifications were delivered in total.
     *
     * @param int $count
     *
     * @return void
     */
    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, 'Expected ' . $count . ' notification(s) to be sent, but ' . count($this->sent) . ' were.');
    }

    /**
     * Assert that nothing was delivered.
     *
     * @return void
     */
    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, 'Notifications were sent unexpectedly.');
    }
}
