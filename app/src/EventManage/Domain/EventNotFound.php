<?php

declare(strict_types=1);

namespace App\EventManage\Domain;

final class EventNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Event "%s" not found.', $id));
    }
}
