<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\Notification;

interface Notifier
{
    /**
     * 建立並投遞一則通知；無論投遞成敗都會持久化（sent / failed），
     * 投遞失敗不重丟例外，以通知紀錄為投遞結果的唯一事實來源。
     *
     * @param array<string, mixed> $context 附加資料；'severity' 鍵（info|warning|error）供管道決定強度
     */
    public function notify(string $channel, string $subject, string $body, array $context = []): Notification;
}
