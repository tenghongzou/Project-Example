<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Channel;

use App\Notification\Application\Channel\NotificationChannel;
use App\Notification\Domain\Notification;
use Psr\Log\LoggerInterface;

/**
 * 預設管道：寫入應用程式 log。
 * context 的 'severity' 鍵（warning|error）決定 log 等級，其餘一律 info。
 */
final readonly class LogNotificationChannel implements NotificationChannel
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function supports(string $channel): bool
    {
        return 'log' === $channel;
    }

    public function send(Notification $notification): void
    {
        $severity = $notification->getContext()['severity'] ?? null;
        $level = match ($severity) {
            'warning', 'error' => $severity,
            default => 'info',
        };

        $this->logger->log($level, $notification->getSubject(), $notification->getContext());
    }
}
