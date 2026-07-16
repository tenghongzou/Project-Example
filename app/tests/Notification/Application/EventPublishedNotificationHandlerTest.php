<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\EventManage\Application\Message\EventPublished;
use App\Notification\Application\MessageHandler\EventPublishedNotificationHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EventPublishedNotificationHandlerTest extends TestCase
{
    public function testHandlerLogsThePublishedEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Notification: event published', [
                'event_id' => '0198c0de-0000-7000-8000-000000000001',
                'name' => 'Team Meetup',
            ]);

        (new EventPublishedNotificationHandler($logger))(new EventPublished(
            eventId: '0198c0de-0000-7000-8000-000000000001',
            name: 'Team Meetup',
            scheduledAt: '2026-08-01T02:00:00+00:00',
        ));
    }
}
