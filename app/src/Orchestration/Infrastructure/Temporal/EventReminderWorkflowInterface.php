<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface EventReminderWorkflowInterface
{
    /**
     * @return \Generator<mixed, mixed, mixed, void>
     */
    #[WorkflowMethod(name: 'event.reminder')]
    public function run(string $eventId, string $eventName, int $delaySeconds);
}
