<?php

declare(strict_types=1);

namespace App\FlowEngine\Application;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * 可擴充的步驟執行器：實作即自動註冊（tagged service），
 * 由 ExecuteNextStepHandler 以 supports() 挑選。
 */
#[AutoconfigureTag('app.flow_step_executor')]
interface StepExecutor
{
    public function supports(string $type): bool;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed> 執行後的新 context
     */
    public function execute(array $params, array $context): array;
}
