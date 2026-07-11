<?php

declare(strict_types=1);

namespace App\Orchestration\Application\MessageHandler;

use App\EventManage\Application\Message\EventPublished;
use App\Orchestration\Application\ReminderScheduler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 訂閱 EventPublished：活動發佈後排程一個到點提醒（durable timer 由排程引擎負責）。
 */
#[AsMessageHandler]
final readonly class ScheduleReminderOnEventPublished
{
    public function __construct(
        private ReminderScheduler $scheduler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EventPublished $message): void
    {
        $remindAt = new \DateTimeImmutable($message->scheduledAt);

        $this->scheduler->scheduleEventReminder($message->eventId, $message->name, $remindAt);

        $this->logger->info('Reminder scheduled for published event', [
            'event_id' => $message->eventId,
            'remind_at' => $remindAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
