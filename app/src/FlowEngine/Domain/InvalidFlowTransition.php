<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

final class InvalidFlowTransition extends \DomainException
{
    public static function advance(FlowInstanceStatus $current): self
    {
        return new self(\sprintf('Only a running flow instance can advance, current status is "%s".', $current->value));
    }

    public static function complete(FlowInstanceStatus $current): self
    {
        return new self(\sprintf('Only a running flow instance can be completed, current status is "%s".', $current->value));
    }

    public static function fail(FlowInstanceStatus $current): self
    {
        return new self(\sprintf('Only a running flow instance can be failed, current status is "%s".', $current->value));
    }
}
