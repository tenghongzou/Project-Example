<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\EventManage\Application\Message\EventCancelled;
use App\Notification\Application\MessageHandler\EventCancelledNotificationHandler;
use App\Notification\Application\Notifier;
use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class EventCancelledNotificationHandlerTest extends TestCase
{
    public function testHandlerNotifiesTheCancelledEvent(): void
    {
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                'log',
                'Notification: event cancelled',
                'Event "Team Meetup" was cancelled.',
                [
                    'event_id' => '0198c0de-0000-7000-8000-000000000001',
                    'name' => 'Team Meetup',
                ],
            )
            ->willReturn(new Notification('log', 'Notification: event cancelled', 'Event "Team Meetup" was cancelled.'));

        (new EventCancelledNotificationHandler($notifier))(new EventCancelled(
            eventId: '0198c0de-0000-7000-8000-000000000001',
            name: 'Team Meetup',
        ));
    }
}
