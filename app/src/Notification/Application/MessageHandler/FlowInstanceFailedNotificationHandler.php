<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\FlowEngine\Application\Message\FlowInstanceFailed;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FlowInstanceFailedNotificationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlowInstanceFailed $message): void
    {
        $this->logger->warning('Notification: flow instance failed', [
            'instance_id' => $message->instanceId,
            'definition_name' => $message->definitionName,
            'error' => $message->error,
        ]);
    }
}
