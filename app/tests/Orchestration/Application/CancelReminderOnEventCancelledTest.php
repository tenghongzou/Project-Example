<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Application;

use App\EventManage\Application\Message\EventCancelled;
use App\Orchestration\Application\MessageHandler\CancelReminderOnEventCancelled;
use App\Orchestration\Application\ReminderScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CancelReminderOnEventCancelledTest extends TestCase
{
    public function testCancelsTheReminderForTheCancelledEvent(): void
    {
        $scheduler = $this->createMock(ReminderScheduler::class);
        $scheduler->expects(self::once())
            ->method('cancelEventReminder')
            ->with('event-1');

        $handler = new CancelReminderOnEventCancelled($scheduler, new NullLogger());

        $handler(new EventCancelled(eventId: 'event-1', name: 'Team Meetup'));
    }
}
