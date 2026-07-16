<?php

declare(strict_types=1);

namespace App\Notification\Domain;

final class NotificationNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Notification "%s" not found.', $id));
    }
}
