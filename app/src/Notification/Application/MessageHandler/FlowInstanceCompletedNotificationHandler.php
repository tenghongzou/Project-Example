<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\FlowEngine\Application\Message\FlowInstanceCompleted;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FlowInstanceCompletedNotificationHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlowInstanceCompleted $message): void
    {
        $this->logger->info('Notification: flow instance completed', [
            'instance_id' => $message->instanceId,
            'definition_name' => $message->definitionName,
        ]);
    }
}
