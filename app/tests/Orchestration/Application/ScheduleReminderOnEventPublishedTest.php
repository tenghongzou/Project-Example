<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Application;

use App\EventManage\Application\Message\EventPublished;
use App\Orchestration\Application\MessageHandler\ScheduleReminderOnEventPublished;
use App\Orchestration\Application\ReminderScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ScheduleReminderOnEventPublishedTest extends TestCase
{
    public function testSchedulesAReminderAtTheEventScheduledTime(): void
    {
        $scheduler = $this->createMock(ReminderScheduler::class);
        $scheduler->expects(self::once())
            ->method('scheduleEventReminder')
            ->with(
                'event-1',
                'Team Meetup',
                self::callback(
                    static fn (\DateTimeImmutable $remindAt): bool => '2026-08-01T02:00:00+00:00' === $remindAt->format(\DateTimeInterface::ATOM),
                ),
            );

        $handler = new ScheduleReminderOnEventPublished($scheduler, new NullLogger());

        $handler(new EventPublished(
            eventId: 'event-1',
            name: 'Team Meetup',
            scheduledAt: '2026-08-01T02:00:00+00:00',
        ));
    }
}
