<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Infrastructure;

use App\Orchestration\Infrastructure\Temporal\EventReminderWorkflow;
use App\Orchestration\Infrastructure\Temporal\EventReminderWorkflowInterface;
use App\Orchestration\Infrastructure\Temporal\ReminderActivityInterface;
use PHPUnit\Framework\TestCase;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Pins the Temporal workflow/activity contract via reflection.
 *
 * The workflow runtime (test-server binary + ext-grpc) is unavailable in this
 * environment, so the workflow body cannot be executed. What CAN and MUST be
 * verified is the registration metadata: TemporalReminderScheduler cancels by
 * the literal workflow type 'event.reminder', so renaming the workflow method
 * or the activity would silently break scheduling in production.
 */
final class EventReminderWorkflowContractTest extends TestCase
{
    public function testWorkflowInterfaceCarriesTheWorkflowInterfaceAttribute(): void
    {
        $attributes = new \ReflectionClass(EventReminderWorkflowInterface::class)
            ->getAttributes(WorkflowInterface::class);

        self::assertCount(1, $attributes, 'EventReminderWorkflowInterface must be marked #[WorkflowInterface]');
    }

    public function testWorkflowMethodNameIsEventReminder(): void
    {
        $attributes = new \ReflectionMethod(EventReminderWorkflowInterface::class, 'run')
            ->getAttributes(WorkflowMethod::class);

        self::assertCount(1, $attributes, 'run() must be marked #[WorkflowMethod]');
        self::assertSame(
            'event.reminder',
            $attributes[0]->newInstance()->name,
            'TemporalReminderScheduler cancels workflows by the type name "event.reminder"; renaming it breaks cancellation',
        );
    }

    public function testWorkflowImplementsItsDeclaredInterface(): void
    {
        self::assertInstanceOf(EventReminderWorkflowInterface::class, new EventReminderWorkflow());
    }

    public function testRunIsAGeneratorWithNoSideEffectsBeforeIteration(): void
    {
        // Workflow 方法必須是 generator：呼叫本身不得執行任何 workflow API
        // （在 workflow context 之外執行 Workflow::timer 會擲出 OutOfContextException）。
        $result = new EventReminderWorkflow()->run('event-1', 'Team Meetup', 60);

        self::assertInstanceOf(\Generator::class, $result);
    }

    public function testRunSignatureMatchesTheArgumentsPassedByTheScheduler(): void
    {
        // TemporalReminderScheduler 以位置參數 start($stub, $eventId, $eventName, $delaySeconds)
        // 啟動工作流，簽章一變 payload 解碼就會爛掉。
        $parameters = new \ReflectionMethod(EventReminderWorkflowInterface::class, 'run')->getParameters();

        self::assertCount(3, $parameters);
        self::assertSame(['eventId', 'eventName', 'delaySeconds'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $parameters,
        ));
        self::assertSame(['string', 'string', 'int'], array_map(
            static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $parameters,
        ));
    }

    public function testActivityInterfacePrefixIsReminderDot(): void
    {
        $attributes = new \ReflectionClass(ReminderActivityInterface::class)
            ->getAttributes(ActivityInterface::class);

        self::assertCount(1, $attributes, 'ReminderActivityInterface must be marked #[ActivityInterface]');
        self::assertSame('reminder.', $attributes[0]->newInstance()->prefix);
    }

    public function testActivityMethodNameIsSend(): void
    {
        $attributes = new \ReflectionMethod(ReminderActivityInterface::class, 'sendReminder')
            ->getAttributes(ActivityMethod::class);

        self::assertCount(1, $attributes, 'sendReminder() must be marked #[ActivityMethod]');
        self::assertSame(
            'send',
            $attributes[0]->newInstance()->name,
            'Workers register the activity as "reminder.send"; renaming it orphans queued reminders',
        );
    }
}
