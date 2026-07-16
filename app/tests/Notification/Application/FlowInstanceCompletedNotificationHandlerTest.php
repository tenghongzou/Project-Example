<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\FlowEngine\Application\Message\FlowInstanceCompleted;
use App\Notification\Application\MessageHandler\FlowInstanceCompletedNotificationHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FlowInstanceCompletedNotificationHandlerTest extends TestCase
{
    public function testHandlerLogsTheCompletedFlowInstance(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Notification: flow instance completed', [
                'instance_id' => '0198c0de-0000-7000-8000-000000000010',
                'definition_name' => 'Order Fulfilment',
            ]);

        (new FlowInstanceCompletedNotificationHandler($logger))(new FlowInstanceCompleted(
            instanceId: '0198c0de-0000-7000-8000-000000000010',
            definitionId: '0198c0de-0000-7000-8000-000000000011',
            definitionName: 'Order Fulfilment',
        ));
    }
}
