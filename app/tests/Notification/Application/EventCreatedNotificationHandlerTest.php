<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\EventManage\Application\Message\EventCreated;
use App\Notification\Application\MessageHandler\EventCreatedNotificationHandler;
use App\Notification\Application\Notifier;
use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class EventCreatedNotificationHandlerTest extends TestCase
{
    public function testHandlerNotifiesTheCreatedEvent(): void
    {
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                'log',
                'Notification: event created',
                'Event "Team Meetup" was created.',
                [
                    'event_id' => '0198c0de-0000-7000-8000-000000000001',
                    'name' => 'Team Meetup',
                ],
            )
            ->willReturn(new Notification('log', 'Notification: event created', 'Event "Team Meetup" was created.'));

        (new EventCreatedNotificationHandler($notifier))(new EventCreated(
            eventId: '0198c0de-0000-7000-8000-000000000001',
            name: 'Team Meetup',
            scheduledAt: '2026-08-01T02:00:00+00:00',
        ));
    }
}
