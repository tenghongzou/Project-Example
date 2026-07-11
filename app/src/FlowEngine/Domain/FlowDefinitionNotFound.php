<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

final class FlowDefinitionNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Flow definition "%s" not found.', $id));
    }
}
