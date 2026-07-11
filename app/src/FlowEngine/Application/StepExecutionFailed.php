<?php

declare(strict_types=1);

namespace App\FlowEngine\Application;

/**
 * 步驟執行的「業務失敗」：訊息可安全外洩到 API 與整合事件。
 * 其他例外一律視為基礎設施錯誤，只進 log 不外洩。
 */
final class StepExecutionFailed extends \RuntimeException
{
    public static function unknownType(string $type): self
    {
        return new self(\sprintf('No step executor supports type "%s".', $type));
    }

    public static function outOfRange(int $index, string $definitionId): self
    {
        return new self(\sprintf('Step index %d is out of range for definition "%s".', $index, $definitionId));
    }
}
