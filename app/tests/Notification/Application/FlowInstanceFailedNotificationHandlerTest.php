<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\FlowEngine\Application\Message\FlowInstanceFailed;
use App\Notification\Application\MessageHandler\FlowInstanceFailedNotificationHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FlowInstanceFailedNotificationHandlerTest extends TestCase
{
    public function testHandlerLogsTheFailedFlowInstanceAtWarningLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Notification: flow instance failed', [
                'instance_id' => '0198c0de-0000-7000-8000-000000000010',
                'definition_name' => 'Order Fulfilment',
                'error' => 'Step "reserve-stock" timed out',
            ]);

        (new FlowInstanceFailedNotificationHandler($logger))(new FlowInstanceFailed(
            instanceId: '0198c0de-0000-7000-8000-000000000010',
            definitionId: '0198c0de-0000-7000-8000-000000000011',
            definitionName: 'Order Fulfilment',
            error: 'Step "reserve-stock" timed out',
        ));
    }
}
