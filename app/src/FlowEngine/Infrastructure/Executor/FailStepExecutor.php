<?php

declare(strict_types=1);

namespace App\FlowEngine\Infrastructure\Executor;

use App\FlowEngine\Application\StepExecutionFailed;
use App\FlowEngine\Application\StepExecutor;

/**
 * type 'fail'：必定丟例外，供測試失敗路徑使用。
 */
final readonly class FailStepExecutor implements StepExecutor
{
    public function supports(string $type): bool
    {
        return 'fail' === $type;
    }

    public function execute(array $params, array $context): array
    {
        $message = $params['message'] ?? null;

        throw new StepExecutionFailed(\is_string($message) ? $message : 'Step failed');
    }
}
