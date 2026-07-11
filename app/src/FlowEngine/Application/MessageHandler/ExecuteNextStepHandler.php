<?php

declare(strict_types=1);

namespace App\FlowEngine\Application\MessageHandler;

use App\FlowEngine\Application\Message\ExecuteNextStep;
use App\FlowEngine\Application\Message\FlowInstanceCompleted;
use App\FlowEngine\Application\Message\FlowInstanceFailed;
use App\FlowEngine\Application\StepExecutionFailed;
use App\FlowEngine\Application\StepExecutor;
use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionRepository;
use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceRepository;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ExecuteNextStepHandler
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
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExecuteNextStep $message): void
    {
        $instance = $this->instanceRepository->get($message->instanceId);

        // 冪等防護一：終態實例直接略過
        if (!$instance->isRunning()) {
            $this->logger->warning('Flow instance is not running, skipping step execution', [
                'instance_id' => $instance->getId(),
                'status' => $instance->getStatus()->value,
            ]);

            return;
        }

        // 冪等防護二（step 級）：重複投遞的舊訊息（index 已推進）略過，
        // 否則會分裂出第二條訊息鏈，剩餘步驟全部重複執行
        if ($message->stepIndex !== $instance->getCurrentStepIndex()) {
            $this->logger->warning('Stale ExecuteNextStep message skipped', [
                'instance_id' => $instance->getId(),
                'message_step_index' => $message->stepIndex,
                'current_step_index' => $instance->getCurrentStepIndex(),
            ]);

            return;
        }

        $definition = $this->definitionRepository->get($instance->getDefinitionId());
        $index = $instance->getCurrentStepIndex();

        // 業務失敗只來自步驟解析與執行；save/dispatch 的基礎設施錯誤不在此列
        try {
            $step = $definition->getStep($index);
            if (null === $step) {
                throw StepExecutionFailed::outOfRange($index, $definition->getId());
            }

            $executor = $this->findExecutor($step['type']);
            $newContext = $executor->execute($step['params'], $instance->getContext());
        } catch (\Throwable $e) {
            $this->failInstance($instance, $definition, $index, $e);

            return;
        }

        $isLastStep = $index >= $definition->getStepCount() - 1;
        if ($isLastStep) {
            $instance->complete($newContext);
        } else {
            $instance->advance($newContext);
        }

        if (!$this->save($instance)) {
            return;
        }

        // dispatch 失敗屬基礎設施錯誤：上拋進 messenger retry。
        // save 成功後 dispatch 失敗會讓實例卡在 Running（無 outbox 的已知取捨，見 ARCHITECTURE P2）
        if ($isLastStep) {
            $this->messageBus->dispatch(new FlowInstanceCompleted(
                instanceId: $instance->getId(),
                definitionId: $definition->getId(),
                definitionName: $definition->getName(),
            ));
        } else {
            $this->messageBus->dispatch(new ExecuteNextStep($instance->getId(), $instance->getCurrentStepIndex()));
        }
    }

    private function failInstance(FlowInstance $instance, FlowDefinition $definition, int $index, \Throwable $e): void
    {
        // 只有明確的業務失敗訊息可外洩到 API 與整合事件；其他例外細節只進 log
        $error = $e instanceof StepExecutionFailed ? $e->getMessage() : 'Step execution failed unexpectedly.';

        $this->logger->error('Flow step execution failed', [
            'instance_id' => $instance->getId(),
            'definition_id' => $definition->getId(),
            'step_index' => $index,
            'exception' => $e,
        ]);

        $instance->fail($error);

        if (!$this->save($instance)) {
            return;
        }

        $this->messageBus->dispatch(new FlowInstanceFailed(
            instanceId: $instance->getId(),
            definitionId: $definition->getId(),
            definitionName: $definition->getName(),
            error: $error,
        ));
    }

    /**
     * @return bool 是否成功持久化；撞樂觀鎖代表另一個 worker 已推進此實例，本次結果作廢
     */
    private function save(FlowInstance $instance): bool
    {
        try {
            $this->instanceRepository->save($instance);
        } catch (OptimisticLockException) {
            $this->logger->warning('Optimistic lock conflict, another worker advanced the instance', [
                'instance_id' => $instance->getId(),
            ]);

            return false;
        }

        return true;
    }

    private function findExecutor(string $type): StepExecutor
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($type)) {
                return $executor;
            }
        }

        throw StepExecutionFailed::unknownType($type);
    }
}
