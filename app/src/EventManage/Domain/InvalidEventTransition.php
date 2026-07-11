<?php

declare(strict_types=1);

namespace App\EventManage\Domain;

final class InvalidEventTransition extends \DomainException
{
    public static function publish(EventStatus $current): self
    {
        return new self(\sprintf('Only a draft event can be published, current status is "%s".', $current->value));
    }

    public static function cancel(): self
    {
        return new self('Event is already cancelled.');
    }
}
