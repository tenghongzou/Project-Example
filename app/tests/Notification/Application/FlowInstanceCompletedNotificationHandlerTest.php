<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use App\FlowEngine\Application\Message\FlowInstanceCompleted;
use App\Notification\Application\MessageHandler\FlowInstanceCompletedNotificationHandler;
use App\Notification\Application\Notifier;
use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class FlowInstanceCompletedNotificationHandlerTest extends TestCase
{
    public function testHandlerNotifiesTheCompletedFlowInstance(): void
    {
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(
                'log',
                'Notification: flow instance completed',
                'Flow instance of "Order Fulfilment" completed successfully.',
                [
                    'instance_id' => '0198c0de-0000-7000-8000-000000000010',
                    'definition_name' => 'Order Fulfilment',
                ],
            )
            ->willReturn(new Notification('log', 'Notification: flow instance completed', 'Flow instance of "Order Fulfilment" completed successfully.'));

        (new FlowInstanceCompletedNotificationHandler($notifier))(new FlowInstanceCompleted(
            instanceId: '0198c0de-0000-7000-8000-000000000010',
            definitionId: '0198c0de-0000-7000-8000-000000000011',
            definitionName: 'Order Fulfilment',
        ));
    }
}
