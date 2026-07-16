<?php

declare(strict_types=1);

namespace App\Notification\Domain;

final class InvalidNotificationTransition extends \DomainException
{
    public static function markSent(NotificationStatus $current): self
    {
        return new self(\sprintf('Only a pending notification can be marked sent, current status is "%s".', $current->value));
    }

    public static function markFailed(NotificationStatus $current): self
    {
        return new self(\sprintf('Only a pending notification can be marked failed, current status is "%s".', $current->value));
    }
}
