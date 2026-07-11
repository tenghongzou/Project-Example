<?php

declare(strict_types=1);

namespace App\EventManage\Application\Message;

/**
 * 模組間 pub/sub 契約：欄位只加不改（見 ARCHITECTURE.md）。
 */
final readonly class EventCreated
{
    public function __construct(
        public string $eventId,
        public string $name,
        public string $scheduledAt,
    ) {
    }
}
