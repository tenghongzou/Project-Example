<?php

declare(strict_types=1);

namespace App\Notification\Application;

final class NotificationDeliveryFailed extends \RuntimeException
{
    public static function withReason(string $reason): self
    {
        return new self($reason);
    }
}
