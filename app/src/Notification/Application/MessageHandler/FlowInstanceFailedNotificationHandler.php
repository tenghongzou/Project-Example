<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\FlowEngine\Application\Message\FlowInstanceFailed;
use App\Notification\Application\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FlowInstanceFailedNotificationHandler
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function __invoke(FlowInstanceFailed $message): void
    {
        $this->notifier->notify(
            'log',
            'Notification: flow instance failed',
            \sprintf('Flow instance of "%s" failed: %s', $message->definitionName, $message->error),
            [
                'instance_id' => $message->instanceId,
                'definition_name' => $message->definitionName,
                'error' => $message->error,
                'severity' => 'warning',
            ],
        );
    }
}
