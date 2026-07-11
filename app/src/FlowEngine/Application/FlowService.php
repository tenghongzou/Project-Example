<?php

declare(strict_types=1);

namespace App\FlowEngine\Application;

use App\FlowEngine\Application\Message\ExecuteNextStep;
use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionNotFound;
use App\FlowEngine\Domain\FlowDefinitionRepository;
use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class FlowService
{
    /**
     * @param iterable<StepExecutor> $executors
     */
    public function __construct(
        private FlowDefinitionRepository $definitionRepository,
        private FlowInstanceRepository $instanceRepository,
        #[AutowireIterator('app.flow_step_executor')]
        private iterable $executors,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param list<array{type: string, params: array<string, mixed>}> $steps
     *
     * @throws UnknownStepType 建立時 fail-fast：type 沒有對應 executor 不該等到非同步執行才發現
     */
    public function createDefinition(string $name, array $steps): FlowDefinition
    {
        foreach ($steps as $step) {
            if (!$this->isSupportedType($step['type'])) {
                throw UnknownStepType::of($step['type']);
            }
        }

        $definition = new FlowDefinition($name, $steps);
        $this->definitionRepository->save($definition);

        return $definition;
    }

    /**
     * 建立 Running 實例並派送第一步的執行命令，實際執行由 worker 非同步進行。
     *
     * @param array<string, mixed> $context
     *
     * @throws FlowDefinitionNotFound
     */
    public function startInstance(string $definitionId, array $context): FlowInstance
    {
        $definition = $this->definitionRepository->get($definitionId);

        $instance = new FlowInstance($definition->getId(), $context);
        $this->instanceRepository->save($instance);

        $this->messageBus->dispatch(new ExecuteNextStep($instance->getId(), $instance->getCurrentStepIndex()));

        return $instance;
    }

    private function isSupportedType(string $type): bool
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($type)) {
                return true;
            }
        }

        return false;
    }
}
