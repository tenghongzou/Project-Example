<?php

declare(strict_types=1);

namespace App\Orchestration\Application\MessageHandler;

use App\EventManage\Application\Message\EventCancelled;
use App\Orchestration\Application\ReminderScheduler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 訂閱 EventCancelled：活動取消時撤銷尚未送出的提醒（補償路徑）。
 */
#[AsMessageHandler]
final readonly class CancelReminderOnEventCancelled
{
    public function __construct(
        private ReminderScheduler $scheduler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EventCancelled $message): void
    {
        $this->scheduler->cancelEventReminder($message->eventId);

        $this->logger->info('Reminder cancellation requested for cancelled event', [
            'event_id' => $message->eventId,
        ]);
    }
}
