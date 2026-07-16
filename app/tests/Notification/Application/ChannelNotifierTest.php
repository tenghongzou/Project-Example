<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\Notification\Application\Channel\NotificationChannel;
use App\Notification\Application\ChannelNotifier;
use App\Notification\Application\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepository;
use App\Notification\Domain\NotificationStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ChannelNotifierTest extends TestCase
{
    public function testNotifyDeliversViaTheSupportingChannelAndMarksSent(): void
    {
        $repository = $this->createMock(NotificationRepository::class);
        // pending 先落地一次，投遞後再存最終狀態
        $repository->expects(self::exactly(2))->method('save');

        $channel = $this->createMock(NotificationChannel::class);
        $channel->expects(self::atLeastOnce())->method('supports')->with('log')->willReturn(true);
        $channel->expects(self::once())
            ->method('send')
            ->with(self::callback(
                static fn (Notification $notification): bool => 'Subject' === $notification->getSubject(),
            ));

        $notifier = new ChannelNotifier($repository, [$channel], new NullLogger());

        $notification = $notifier->notify('log', 'Subject', 'Body', ['key' => 'value']);

        self::assertSame(NotificationStatus::Sent, $notification->getStatus());
        self::assertNotNull($notification->getSentAt());
        self::assertSame(['key' => 'value'], $notification->getContext());
    }

    public function testNotifyMarksFailedWhenNoChannelSupportsTheRequestedOne(): void
    {
        $repository = $this->createMock(NotificationRepository::class);
        $repository->expects(self::exactly(2))->method('save');

        $channel = $this->createMock(NotificationChannel::class);
        $channel->method('supports')->willReturn(false);
        $channel->expects(self::never())->method('send');

        $notifier = new ChannelNotifier($repository, [$channel], new NullLogger());

        $notification = $notifier->notify('sms', 'Subject', 'Body');

        self::assertSame(NotificationStatus::Failed, $notification->getStatus());
        self::assertSame('No notification channel supports "sms".', $notification->getError());
        self::assertNull($notification->getSentAt());
    }

    public function testNotifyMarksFailedWhenTheChannelThrows(): void
    {
        $repository = $this->createMock(NotificationRepository::class);
        $repository->expects(self::exactly(2))->method('save');

        $channel = $this->createMock(NotificationChannel::class);
        $channel->expects(self::atLeastOnce())->method('supports')->with('log')->willReturn(true);
        $channel->method('send')->willThrowException(NotificationDeliveryFailed::withReason('SMTP down'));

        $notifier = new ChannelNotifier($repository, [$channel], new NullLogger());

        $notification = $notifier->notify('log', 'Subject', 'Body');

        self::assertSame(NotificationStatus::Failed, $notification->getStatus());
        self::assertSame('SMTP down', $notification->getError());
    }
}
