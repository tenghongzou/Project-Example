<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\EventManage\Application\Message\EventCancelled;
use App\Notification\Application\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EventCancelledNotificationHandler
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function __invoke(EventCancelled $message): void
    {
        $this->notifier->notify(
            'log',
            'Notification: event cancelled',
            \sprintf('Event "%s" was cancelled.', $message->name),
            [
                'event_id' => $message->eventId,
                'name' => $message->name,
            ],
        );
    }
}
