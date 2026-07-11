<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use App\Orchestration\Application\ReminderScheduler;
use Psr\Log\LoggerInterface;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;

final readonly class TemporalReminderScheduler implements ReminderScheduler
{
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
                // workflow id 以 eventId 去重：EventPublished 重複投遞時不會排出第二個提醒
                ->withWorkflowId('event-reminder-'.$eventId)
                ->withTaskQueue('default'),
        );

        try {
            $this->client->start($workflow, $eventId, $eventName, $delaySeconds);
        } catch (WorkflowExecutionAlreadyStartedException) {
            $this->logger->info('Reminder workflow already scheduled, skipping', [
                'event_id' => $eventId,
            ]);
        }
    }
}
