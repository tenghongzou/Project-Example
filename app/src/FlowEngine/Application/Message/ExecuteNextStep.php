<?php

declare(strict_types=1);

namespace App\FlowEngine\Application\Message;

/**
 * 模組內部命令：驅動實例執行下一個步驟（每步一則訊息）。
 * stepIndex 是 step 級冪等 key：at-least-once 投遞下，重複的舊訊息
 * 會因 index 已推進而被 handler 略過，避免訊息鏈分裂造成副作用重複執行。
 */
final readonly class ExecuteNextStep
{
    public function __construct(
        public string $instanceId,
        public int $stepIndex,
    ) {
    }
}
