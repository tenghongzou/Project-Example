<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Application;

use App\FlowEngine\Application\Message\ExecuteNextStep;
use App\FlowEngine\Application\Message\FlowInstanceCompleted;
use App\FlowEngine\Application\Message\FlowInstanceFailed;
use App\FlowEngine\Application\MessageHandler\ExecuteNextStepHandler;
use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionRepository;
use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceRepository;
use App\FlowEngine\Domain\FlowInstanceStatus;
use App\FlowEngine\Infrastructure\Executor\FailStepExecutor;
use App\FlowEngine\Infrastructure\Executor\LogStepExecutor;
use App\FlowEngine\Infrastructure\Executor\SetContextStepExecutor;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExecuteNextStepHandlerTest extends TestCase
{
    /** @var list<object> */
    private array $dispatchedMessages = [];

    protected function setUp(): void
    {
        $this->dispatchedMessages = [];
    }

    public function testIntermediateStepAdvancesTheInstanceAndDispatchesExecuteNextStep(): void
    {
        $definition = new FlowDefinition('Two Steps', [
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
            ['type' => 'log', 'params' => ['message' => 'done']],
        ]);
        $instance = new FlowInstance($definition->getId(), []);

        $handler = $this->createHandler($definition, $instance);

        $handler(new ExecuteNextStep($instance->getId(), 0));

        self::assertSame(FlowInstanceStatus::Running, $instance->getStatus());
        self::assertSame(1, $instance->getCurrentStepIndex());
        self::assertSame(['foo' => 'bar'], $instance->getContext());

        self::assertCount(1, $this->dispatchedMessages);
        $message = $this->dispatchedMessages[0];
        self::assertInstanceOf(ExecuteNextStep::class, $message);
        self::assertSame($instance->getId(), $message->instanceId);
        self::assertSame(1, $message->stepIndex);
    }

    public function testLastStepCompletesTheInstanceAndDispatchesFlowInstanceCompleted(): void
    {
        $definition = new FlowDefinition('One Step', [
            ['type' => 'set', 'params' => ['key' => 'result', 'value' => 42]],
        ]);
        $instance = new FlowInstance($definition->getId(), []);

        $handler = $this->createHandler($definition, $instance);

        $handler(new ExecuteNextStep($instance->getId(), 0));

        self::assertSame(FlowInstanceStatus::Completed, $instance->getStatus());
        self::assertSame(['result' => 42], $instance->getContext());
        self::assertNull($instance->getError());

        self::assertCount(1, $this->dispatchedMessages);
        $message = $this->dispatchedMessages[0];
        self::assertInstanceOf(FlowInstanceCompleted::class, $message);
        self::assertSame($instance->getId(), $message->instanceId);
        self::assertSame($definition->getId(), $message->definitionId);
        self::assertSame('One Step', $message->definitionName);
    }

    public function testExecutorFailureFailsTheInstanceAndDispatchesFlowInstanceFailedWithoutRethrowing(): void
    {
        $definition = new FlowDefinition('Failing Flow', [
            ['type' => 'fail', 'params' => ['message' => 'kaboom']],
        ]);
        $instance = new FlowInstance($definition->getId(), []);

        $handler = $this->createHandler($definition, $instance);

        // 不 rethrow：呼叫本身不得丟例外，否則會進 messenger retry
        $handler(new ExecuteNextStep($instance->getId(), 0));

        self::assertSame(FlowInstanceStatus::Failed, $instance->getStatus());
        self::assertSame('kaboom', $instance->getError());

        self::assertCount(1, $this->dispatchedMessages);
        $message = $this->dispatchedMessages[0];
        self::assertInstanceOf(FlowInstanceFailed::class, $message);
        self::assertSame($instance->getId(), $message->instanceId);
        self::assertSame($definition->getId(), $message->definitionId);
        self::assertSame('Failing Flow', $message->definitionName);
        self::assertSame('kaboom', $message->error);
    }

    public function testUnknownStepTypeFailsTheInstanceAndDispatchesFlowInstanceFailed(): void
    {
        $definition = new FlowDefinition('Unknown Step Flow', [
            ['type' => 'teleport', 'params' => []],
        ]);
        $instance = new FlowInstance($definition->getId(), []);

        $handler = $this->createHandler($definition, $instance);

        $handler(new ExecuteNextStep($instance->getId(), 0));

        self::assertSame(FlowInstanceStatus::Failed, $instance->getStatus());
        self::assertSame('No step executor supports type "teleport".', $instance->getError());

        self::assertCount(1, $this->dispatchedMessages);
        $message = $this->dispatchedMessages[0];
        self::assertInstanceOf(FlowInstanceFailed::class, $message);
        self::assertSame('No step executor supports type "teleport".', $message->error);
    }

    public function testTerminalInstanceIsSkippedWithoutExecutionOrDispatch(): void
    {
        $definition = new FlowDefinition('Idempotency Flow', [
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
        ]);
        $instance = new FlowInstance($definition->getId(), []);
        $instance->complete([]);

        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::once())
            ->method('get')
            ->with($instance->getId())
            ->willReturn($instance);
        $instanceRepository->expects(self::never())
            ->method('save');

        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::never())
            ->method('get');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $handler = new ExecuteNextStepHandler(
            $definitionRepository,
            $instanceRepository,
            $this->createExecutors(),
            $messageBus,
            new NullLogger(),
        );

        $handler(new ExecuteNextStep($instance->getId(), 0));

        // 冪等防護：終態不變、context 不被重新執行的步驟覆寫
        self::assertSame(FlowInstanceStatus::Completed, $instance->getStatus());
        self::assertSame([], $instance->getContext());
    }

    public function testStaleStepIndexMessageIsSkippedWhileRunning(): void
    {
        $definition = new FlowDefinition('Stale Message Flow', [
            ['type' => 'set', 'params' => ['key' => 'a', 'value' => 1]],
            ['type' => 'set', 'params' => ['key' => 'b', 'value' => 2]],
            ['type' => 'log', 'params' => []],
        ]);
        $instance = new FlowInstance($definition->getId(), []);
        $instance->advance(['a' => 1]); // index 已推進到 1

        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::once())
            ->method('get')
            ->willReturn($instance);
        $instanceRepository->expects(self::never())
            ->method('save');

        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::never())
            ->method('get');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $handler = new ExecuteNextStepHandler(
            $definitionRepository,
            $instanceRepository,
            $this->createExecutors(),
            $messageBus,
            new NullLogger(),
        );

        // 重複投遞的舊訊息（step 0）：step 級冪等必須擋下，否則訊息鏈分裂、後續步驟雙重執行
        $handler(new ExecuteNextStep($instance->getId(), 0));

        self::assertSame(FlowInstanceStatus::Running, $instance->getStatus());
        self::assertSame(1, $instance->getCurrentStepIndex());
        self::assertSame(['a' => 1], $instance->getContext());
    }

    public function testOptimisticLockConflictOnSaveIsSwallowedWithoutDispatch(): void
    {
        $definition = new FlowDefinition('Concurrent Flow', [
            ['type' => 'set', 'params' => ['key' => 'a', 'value' => 1]],
        ]);
        $instance = new FlowInstance($definition->getId(), []);

        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::once())
            ->method('get')
            ->willReturn($definition);

        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::once())
            ->method('get')
            ->willReturn($instance);
        $instanceRepository->expects(self::once())
            ->method('save')
            ->willThrowException(new OptimisticLockException('conflict', $instance));

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $handler = new ExecuteNextStepHandler(
            $definitionRepository,
            $instanceRepository,
            $this->createExecutors(),
            $messageBus,
            new NullLogger(),
        );

        // 另一個 worker 已推進：本次結果作廢即可，不得把實例標成 Failed、不得發假事件、不得 rethrow
        $handler(new ExecuteNextStep($instance->getId(), 0));
    }

    private function createHandler(FlowDefinition $definition, FlowInstance $instance): ExecuteNextStepHandler
    {
        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::once())
            ->method('get')
            ->with($definition->getId())
            ->willReturn($definition);

        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::once())
            ->method('get')
            ->with($instance->getId())
            ->willReturn($instance);
        $instanceRepository->expects(self::once())
            ->method('save')
            ->with($instance);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message): Envelope {
                $this->dispatchedMessages[] = $message;

                return new Envelope($message);
            });

        return new ExecuteNextStepHandler(
            $definitionRepository,
            $instanceRepository,
            $this->createExecutors(),
            $messageBus,
            new NullLogger(),
        );
    }

    /**
     * @return list<\App\FlowEngine\Application\StepExecutor>
     */
    private function createExecutors(): array
    {
        return [
            new LogStepExecutor(new NullLogger()),
            new SetContextStepExecutor(),
            new FailStepExecutor(),
        ];
    }
}
