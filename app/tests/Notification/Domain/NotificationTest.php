<?php

declare(strict_types=1);

namespace App\Tests\Notification\Domain;

use App\Notification\Domain\InvalidNotificationTransition;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class NotificationTest extends TestCase
{
    public function testNewNotificationIsPendingWithAValidUuid(): void
    {
        $notification = new Notification('log', 'Subject', 'Body', ['key' => 'value']);

        self::assertTrue(Uuid::isValid($notification->getId()));
        self::assertSame('log', $notification->getChannel());
        self::assertSame('Subject', $notification->getSubject());
        self::assertSame('Body', $notification->getBody());
        self::assertSame(['key' => 'value'], $notification->getContext());
        self::assertSame(NotificationStatus::Pending, $notification->getStatus());
        self::assertNull($notification->getError());
        self::assertNull($notification->getSentAt());
        self::assertSame(1, $notification->getVersion());
        self::assertSame('UTC', $notification->getCreatedAt()->getTimezone()->getName());
    }

    public function testMarkSentTransitionsToSentAndStampsSentAt(): void
    {
        $notification = new Notification('log', 'Subject', 'Body');

        $notification->markSent();

        self::assertSame(NotificationStatus::Sent, $notification->getStatus());
        self::assertNotNull($notification->getSentAt());
        self::assertSame('UTC', $notification->getSentAt()->getTimezone()->getName());
        self::assertNull($notification->getError());
    }

    public function testMarkFailedTransitionsToFailedAndRecordsTheError(): void
    {
        $notification = new Notification('log', 'Subject', 'Body');

        $notification->markFailed('SMTP down');

        self::assertSame(NotificationStatus::Failed, $notification->getStatus());
        self::assertSame('SMTP down', $notification->getError());
        self::assertNull($notification->getSentAt());
    }

    public function testMarkSentTwiceIsRejected(): void
    {
        $notification = new Notification('log', 'Subject', 'Body');
        $notification->markSent();

        $this->expectException(InvalidNotificationTransition::class);

        $notification->markSent();
    }

    public function testMarkFailedAfterSentIsRejected(): void
    {
        $notification = new Notification('log', 'Subject', 'Body');
        $notification->markSent();

        $this->expectException(InvalidNotificationTransition::class);

        $notification->markFailed('too late');
    }

    public function testMarkSentAfterFailedIsRejected(): void
    {
        $notification = new Notification('log', 'Subject', 'Body');
        $notification->markFailed('SMTP down');

        $this->expectException(InvalidNotificationTransition::class);

        $notification->markSent();
    }
}
