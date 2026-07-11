<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use App\Orchestration\Application\ReminderScheduler;
use Psr\Log\LoggerInterface;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowNotFoundException;

final readonly class TemporalReminderScheduler implements ReminderScheduler
{
    private const string TASK_QUEUE = 'default';

    public function __construct(
        private WorkflowClientInterface $client,
        private LoggerInterface $logger,
    ) {
    }

    public function scheduleEventReminder(string $eventId, string $eventName, \DateTimeImmutable $remindAt): void
    {
        // 至少 1 秒：0ms timer 是邊界值（提醒時間已過時仍走正常 timer 路徑）
        $delaySeconds = max(1, $remindAt->getTimestamp() - time());

        $workflow = $this->client->newWorkflowStub(
            EventReminderWorkflowInterface::class,
            WorkflowOptions::new()
                // workflow id 以 eventId 去重；RejectDuplicate 讓「已完成」的同 id 也拒絕重啟，
                // 否則短提醒完成後的重複投遞仍會排出第二個提醒
                ->withWorkflowId(self::workflowId($eventId))
                ->withWorkflowIdReusePolicy(IdReusePolicy::RejectDuplicate)
                ->withTaskQueue(self::TASK_QUEUE),
        );

        try {
            $this->client->start($workflow, $eventId, $eventName, $delaySeconds);
        } catch (WorkflowExecutionAlreadyStartedException) {
            $this->logger->info('Reminder workflow already scheduled, skipping', [
                'event_id' => $eventId,
            ]);
        }
    }

    public function cancelEventReminder(string $eventId): void
    {
        try {
            $this->client
                ->newUntypedRunningWorkflowStub(self::workflowId($eventId), null, 'event.reminder')
                ->cancel();
        } catch (WorkflowNotFoundException) {
            // 沒排過提醒、或提醒已送出——無事可做
            $this->logger->info('No running reminder workflow to cancel', [
                'event_id' => $eventId,
            ]);
        }
    }

    private static function workflowId(string $eventId): string
    {
        return 'event-reminder-'.$eventId;
    }
}
