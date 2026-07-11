<?php

declare(strict_types=1);

namespace App\FlowEngine\Infrastructure\Executor;

use App\FlowEngine\Application\StepExecutionFailed;
use App\FlowEngine\Application\StepExecutor;

/**
 * type 'set'：把 params['key'] => params['value'] 寫進 context。
 */
final readonly class SetContextStepExecutor implements StepExecutor
{
    public function supports(string $type): bool
    {
        return 'set' === $type;
    }

    public function execute(array $params, array $context): array
    {
        $key = $params['key'] ?? null;

        if (!\is_string($key) || '' === $key) {
            throw new StepExecutionFailed('The "set" step requires a non-empty string "key" param.');
        }

        $context[$key] = $params['value'] ?? null;

        return $context;
    }
}
