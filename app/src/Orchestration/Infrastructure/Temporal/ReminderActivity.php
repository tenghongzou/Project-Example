<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use Psr\Log\LoggerInterface;

/**
 * 提醒 activity：目前以 log 代表通知動作（之後可換成 email/推播）。
 */
final readonly class ReminderActivity implements ReminderActivityInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function sendReminder(string $eventId, string $eventName): void
    {
        $this->logger->info('Reminder: event is starting', [
            'event_id' => $eventId,
            'name' => $eventName,
        ]);
    }
}
