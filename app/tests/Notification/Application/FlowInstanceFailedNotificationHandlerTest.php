<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\FlowEngine\Application\Message\FlowInstanceFailed;
use App\Notification\Application\MessageHandler\FlowInstanceFailedNotificationHandler;
use App\Notification\Application\Notifier;
use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class FlowInstanceFailedNotificationHandlerTest extends TestCase
{
    public function testHandlerNotifiesTheFailedFlowInstanceWithWarningSeverity(): void
    {
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                'log',
                'Notification: flow instance failed',
                'Flow instance of "Order Fulfilment" failed: Step "reserve-stock" timed out',
                [
                    'instance_id' => '0198c0de-0000-7000-8000-000000000010',
                    'definition_name' => 'Order Fulfilment',
                    'error' => 'Step "reserve-stock" timed out',
                    'severity' => 'warning',
                ],
            )
            ->willReturn(new Notification('log', 'Notification: flow instance failed', 'Flow instance of "Order Fulfilment" failed: Step "reserve-stock" timed out'));

        (new FlowInstanceFailedNotificationHandler($notifier))(new FlowInstanceFailed(
            instanceId: '0198c0de-0000-7000-8000-000000000010',
            definitionId: '0198c0de-0000-7000-8000-000000000011',
            definitionName: 'Order Fulfilment',
            error: 'Step "reserve-stock" timed out',
        ));
    }
}
