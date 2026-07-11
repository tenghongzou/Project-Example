<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Infrastructure;

use App\Orchestration\Infrastructure\Temporal\ReminderActivity;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReminderActivityTest extends TestCase
{
    public function testSendReminderLogsTheEvent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Reminder: event is starting', [
                'event_id' => 'event-1',
                'name' => 'Team Meetup',
            ]);

        (new ReminderActivity($logger))->sendReminder('event-1', 'Team Meetup');
    }
}
