<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Infrastructure;

use App\Orchestration\Infrastructure\Temporal\EventReminderWorkflowInterface;
use App\Orchestration\Infrastructure\Temporal\TemporalReminderScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\IdReusePolicy;

final class TemporalReminderSchedulerTest extends TestCase
{
    public function testScheduleStartsWorkflowWithDedupIdAndRejectDuplicatePolicy(): void
    {
        $stub = new \stdClass();

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects(self::once())
            ->method('newWorkflowStub')
            ->with(
                EventReminderWorkflowInterface::class,
                self::callback(
                    static fn (WorkflowOptions $options): bool => 'event-reminder-event-1' === $options->workflowId
                        && IdReusePolicy::RejectDuplicate->value === $options->workflowIdReusePolicy
                        && 'default' === $options->taskQueue,
                ),
            )
            ->willReturn($stub);
        $client->expects(self::once())
            ->method('start')
            ->with(
                $stub,
                'event-1',
                'Team Meetup',
                // delay 至少 1 秒（0ms timer 邊界值），且不超過距提醒時間的秒數
                self::callback(static fn (int $delay): bool => $delay >= 1 && $delay <= 3600),
            );

        $scheduler = new TemporalReminderScheduler($client, new NullLogger());

        $scheduler->scheduleEventReminder('event-1', 'Team Meetup', new \DateTimeImmutable('+3600 seconds'));
    }

    public function testPastRemindAtIsClampedToOneSecond(): void
    {
        $client = $this->createMock(WorkflowClientInterface::class);
        $client->method('newWorkflowStub')->willReturn(new \stdClass());
        $client->expects(self::once())
            ->method('start')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(1),
            );

        $scheduler = new TemporalReminderScheduler($client, new NullLogger());

        $scheduler->scheduleEventReminder('event-1', 'Team Meetup', new \DateTimeImmutable('-1 day'));
    }

    public function testCancelCancelsTheRunningWorkflow(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->expects(self::once())->method('cancel');

        $client = $this->createMock(WorkflowClientInterface::class);
        $client->expects(self::once())
            ->method('newUntypedRunningWorkflowStub')
            ->with('event-reminder-event-1', null, 'event.reminder')
            ->willReturn($stub);

        $scheduler = new TemporalReminderScheduler($client, new NullLogger());

        $scheduler->cancelEventReminder('event-1');
    }
}
