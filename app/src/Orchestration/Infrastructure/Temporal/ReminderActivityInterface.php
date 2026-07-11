<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'reminder.')]
interface ReminderActivityInterface
{
    #[ActivityMethod(name: 'send')]
    public function sendReminder(string $eventId, string $eventName): void;
}
