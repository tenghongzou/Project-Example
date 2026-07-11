<?php

declare(strict_types=1);

namespace App\Orchestration\Application\MessageHandler;

use App\EventManage\Application\Message\EventPublished;
use App\Orchestration\Application\ReminderScheduler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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
        // 契約加欄位前的舊訊息（重放時）沒有 scheduledAt：略過而非炸掉
        if ('' === $message->scheduledAt) {
            $this->logger->warning('EventPublished without scheduledAt, skipping reminder', [
                'event_id' => $message->eventId,
            ]);

            return;
        }

        $remindAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $message->scheduledAt);
        if (false === $remindAt) {
            // 格式錯誤是永久性錯誤：直接進 failed transport，不浪費 retry
            throw new UnrecoverableMessageHandlingException(\sprintf('Invalid scheduledAt "%s" on EventPublished for event "%s".', $message->scheduledAt, $message->eventId));
        }

        $this->scheduler->scheduleEventReminder($message->eventId, $message->name, $remindAt);

        $this->logger->info('Reminder scheduled for published event', [
            'event_id' => $message->eventId,
            'remind_at' => $remindAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
