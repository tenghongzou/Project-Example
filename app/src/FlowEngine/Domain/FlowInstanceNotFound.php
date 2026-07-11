<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

final class FlowInstanceNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Flow instance "%s" not found.', $id));
    }
}
