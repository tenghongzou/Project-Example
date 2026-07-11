<?php

declare(strict_types=1);

namespace App\FlowEngine\Application\Message;

/**
 * 模組間 pub/sub 契約：欄位只加不改（見 ARCHITECTURE.md）。
 */
final readonly class FlowInstanceCompleted
{
    public function __construct(
        public string $instanceId,
        public string $definitionId,
        public string $definitionName,
    ) {
    }
}
