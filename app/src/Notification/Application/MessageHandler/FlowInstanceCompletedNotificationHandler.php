<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\FlowEngine\Application\Message\FlowInstanceCompleted;
use App\Notification\Application\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FlowInstanceCompletedNotificationHandler
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function __invoke(FlowInstanceCompleted $message): void
    {
        $this->notifier->notify(
            'log',
            'Notification: flow instance completed',
            \sprintf('Flow instance of "%s" completed successfully.', $message->definitionName),
            [
                'instance_id' => $message->instanceId,
                'definition_name' => $message->definitionName,
            ],
        );
    }
}
