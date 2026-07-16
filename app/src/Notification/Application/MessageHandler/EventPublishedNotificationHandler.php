<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\EventManage\Application\Message\EventPublished;
use App\Notification\Application\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EventPublishedNotificationHandler
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function __invoke(EventPublished $message): void
    {
        $this->notifier->notify(
            'log',
            'Notification: event published',
            \sprintf('Event "%s" was published.', $message->name),
            [
                'event_id' => $message->eventId,
                'name' => $message->name,
            ],
        );
    }
}
