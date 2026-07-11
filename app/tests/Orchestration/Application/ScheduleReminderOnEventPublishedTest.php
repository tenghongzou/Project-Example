<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Application;

use App\EventManage\Application\Message\EventPublished;
use App\Orchestration\Application\MessageHandler\ScheduleReminderOnEventPublished;
use App\Orchestration\Application\ReminderScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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

    public function testLegacyMessageWithoutScheduledAtIsSkipped(): void
    {
        $scheduler = $this->createMock(ReminderScheduler::class);
        $scheduler->expects(self::never())
            ->method('scheduleEventReminder');

        $handler = new ScheduleReminderOnEventPublished($scheduler, new NullLogger());

        // 契約加欄位前的舊訊息（重放場景）：略過而非炸掉
        $handler(new EventPublished(eventId: 'event-1', name: 'Team Meetup'));
    }

    public function testMalformedScheduledAtIsUnrecoverable(): void
    {
        $scheduler = $this->createMock(ReminderScheduler::class);
        $scheduler->expects(self::never())
            ->method('scheduleEventReminder');

        $handler = new ScheduleReminderOnEventPublished($scheduler, new NullLogger());

        // 永久性錯誤：直接進 failed transport，不浪費 retry
        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new EventPublished(
            eventId: 'event-1',
            name: 'Team Meetup',
            scheduledAt: 'not-a-date',
        ));
    }
}
