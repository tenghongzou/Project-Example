<?php

declare(strict_types=1);

namespace App\Orchestration\Application;

/**
 * 排程「活動提醒」的 port：Application 層不依賴任何工作流引擎，
 * 實作（Temporal adapter）在 Infrastructure。
 */
interface ReminderScheduler
{
    public function scheduleEventReminder(string $eventId, string $eventName, \DateTimeImmutable $remindAt): void;
}
