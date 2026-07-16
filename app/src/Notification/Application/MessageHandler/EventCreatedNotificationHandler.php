<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\EventManage\Application\Message\EventCreated;
use App\Notification\Application\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EventCreatedNotificationHandler
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function __invoke(EventCreated $message): void
    {
        $this->notifier->notify(
            'log',
            'Notification: event created',
            \sprintf('Event "%s" was created.', $message->name),
            [
                'event_id' => $message->eventId,
                'name' => $message->name,
            ],
        );
    }
}
