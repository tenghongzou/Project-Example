<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\EventManage\Application\Message\EventCancelled;
use App\Notification\Application\MessageHandler\EventCancelledNotificationHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EventCancelledNotificationHandlerTest extends TestCase
{
    public function testHandlerLogsTheCancelledEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Notification: event cancelled', [
                'event_id' => '0198c0de-0000-7000-8000-000000000001',
                'name' => 'Team Meetup',
            ]);

        (new EventCancelledNotificationHandler($logger))(new EventCancelled(
            eventId: '0198c0de-0000-7000-8000-000000000001',
            name: 'Team Meetup',
        ));
    }
}
