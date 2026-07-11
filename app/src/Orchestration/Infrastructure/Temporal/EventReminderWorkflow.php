<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;

/**
 * 活動提醒工作流：durable timer 睡到提醒時間再執行 activity。
 * timer 由 Temporal 持久化——worker 重啟、部署、甚至睡一個月都不會遺失。
 */
final class EventReminderWorkflow implements EventReminderWorkflowInterface
{
    public function run(string $eventId, string $eventName, int $delaySeconds)
    {
        yield Workflow::timer(max(1, $delaySeconds));

        $activity = Workflow::newActivityStub(
            ReminderActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::seconds(30))
                // Temporal 預設無限重試；提醒送不出去重試 5 次就該進 log/告警，不該永久卡住
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(5)),
        );

        // stub 在 workflow context 實際回傳 Promise 供 yield（SDK 的 proxy 魔法，介面型別是給 activity 實作看的）
        // @phpstan-ignore method.void
        yield $activity->sendReminder($eventId, $eventName);
    }
}
