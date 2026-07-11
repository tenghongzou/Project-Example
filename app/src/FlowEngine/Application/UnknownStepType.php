<?php

declare(strict_types=1);

namespace App\FlowEngine\Application;

/**
 * 建立 FlowDefinition 時的 fail-fast 驗證：步驟 type 沒有對應的 executor。
 */
final class UnknownStepType extends \InvalidArgumentException
{
    public static function of(string $type): self
    {
        return new self(\sprintf('Unknown step type "%s": no executor supports it.', $type));
    }
}
