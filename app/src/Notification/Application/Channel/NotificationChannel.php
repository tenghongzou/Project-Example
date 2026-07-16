<?php

declare(strict_types=1);

namespace App\Notification\Application\Channel;

use App\Notification\Application\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * 可擴充的通知管道：實作即自動註冊（tagged service），
 * 由 ChannelNotifier 以 supports() 挑選。新增 email/SMS/push 管道
 * 只需實作本介面，不必改動任何既有程式。
 */
#[AutoconfigureTag('app.notification_channel')]
interface NotificationChannel
{
    public function supports(string $channel): bool;

    /**
     * @throws NotificationDeliveryFailed 投遞失敗（由 ChannelNotifier 記錄為 failed）
     */
    public function send(Notification $notification): void;
}
