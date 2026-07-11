<?php

declare(strict_types=1);

namespace App\FlowEngine\Presentation;

final readonly class StartFlowInstanceRequest
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $context = [],
    ) {
    }
}
