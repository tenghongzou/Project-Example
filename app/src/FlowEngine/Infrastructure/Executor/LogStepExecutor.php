<?php

declare(strict_types=1);

namespace App\FlowEngine\Infrastructure\Executor;

use App\FlowEngine\Application\StepExecutor;
use Psr\Log\LoggerInterface;

/**
 * type 'log'：記錄 params['message'] 與當前 context，不改變 context。
 */
final readonly class LogStepExecutor implements StepExecutor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function supports(string $type): bool
    {
        return 'log' === $type;
    }

    public function execute(array $params, array $context): array
    {
        $message = $params['message'] ?? null;

        // log message 固定、使用者資料一律放 context，避免 log injection/偽造
        $this->logger->info('Flow step log', [
            'message' => \is_string($message) ? $message : null,
            'context' => $context,
        ]);

        return $context;
    }
}
