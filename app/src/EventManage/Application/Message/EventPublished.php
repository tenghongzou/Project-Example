<?php

declare(strict_types=1);

namespace App\EventManage\Application\Message;

/**
 * 模組間 pub/sub 契約：欄位只加不改（見 ARCHITECTURE.md）。
 */
final readonly class EventPublished
{
    public function __construct(
        public string $eventId,
        public string $name,
        /** 活動開始時間（ATOM、UTC）；訂閱者可據此排程提醒。
         *  預設空字串：讓「加欄位前」已入列的舊訊息（含 failed transport 重放）仍可反序列化 */
        public string $scheduledAt = '',
    ) {
    }
}
