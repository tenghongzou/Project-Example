<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\EventManage\Application\Message\EventCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EventCreatedNotificationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EventCreated $message): void
    {
        $this->logger->info('Notification: event created', [
            'event_id' => $message->eventId,
            'name' => $message->name,
        ]);
    }
}
