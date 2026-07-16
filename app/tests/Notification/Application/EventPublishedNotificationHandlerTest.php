<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\EventManage\Application\Message\EventPublished;
use App\Notification\Application\MessageHandler\EventPublishedNotificationHandler;
use App\Notification\Application\Notifier;
use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class EventPublishedNotificationHandlerTest extends TestCase
{
    public function testHandlerNotifiesThePublishedEvent(): void
    {
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                'log',
                'Notification: event published',
                'Event "Team Meetup" was published.',
                [
                    'event_id' => '0198c0de-0000-7000-8000-000000000001',
                    'name' => 'Team Meetup',
                ],
            )
            ->willReturn(new Notification('log', 'Notification: event published', 'Event "Team Meetup" was published.'));

        (new EventPublishedNotificationHandler($notifier))(new EventPublished(
            eventId: '0198c0de-0000-7000-8000-000000000001',
            name: 'Team Meetup',
            scheduledAt: '2026-08-01T02:00:00+00:00',
        ));
    }
}
