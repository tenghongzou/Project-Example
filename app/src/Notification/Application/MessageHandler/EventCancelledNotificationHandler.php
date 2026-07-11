<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\EventManage\Application\Message\EventCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EventCancelledNotificationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EventCancelled $message): void
    {
        $this->logger->info('Notification: event cancelled', [
            'event_id' => $message->eventId,
            'name' => $message->name,
        ]);
    }
}
