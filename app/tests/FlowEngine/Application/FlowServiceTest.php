<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Application;

use App\FlowEngine\Application\FlowService;
use App\FlowEngine\Application\Message\ExecuteNextStep;
use App\FlowEngine\Application\StepExecutor;
use App\FlowEngine\Application\UnknownStepType;
use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionNotFound;
use App\FlowEngine\Domain\FlowDefinitionRepository;
use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceRepository;
use App\FlowEngine\Domain\FlowInstanceStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class FlowServiceTest extends TestCase
{
    public function testCreateDefinitionSavesAndReturnsTheDefinition(): void
    {
        $savedDefinition = null;
        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(FlowDefinition::class))
            ->willReturnCallback(static function (FlowDefinition $definition) use (&$savedDefinition): void {
                $savedDefinition = $definition;
            });

        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::never())
            ->method('save');

        // 建立定義不該有任何非同步副作用
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $steps = [
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
            ['type' => 'log', 'params' => ['message' => 'done']],
        ];
        $definition = $this->createService($definitionRepository, $instanceRepository, $messageBus)
            ->createDefinition('Order Flow', $steps);

        self::assertSame($definition, $savedDefinition);
        self::assertNotSame('', $definition->getId());
        self::assertSame('Order Flow', $definition->getName());
        self::assertSame($steps, $definition->getSteps());
    }

    public function testCreateDefinitionRejectsUnknownStepTypeWithoutSaving(): void
    {
        // fail-fast：沒有 executor 支援的 type 連存都不該存
        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::never())
            ->method('save');

        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::never())
            ->method('save');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $service = $this->createService($definitionRepository, $instanceRepository, $messageBus);

        $this->expectException(UnknownStepType::class);
        $this->expectExceptionMessage('Unknown step type "teleport"');

        $service->createDefinition('Order Flow', [
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
            ['type' => 'teleport', 'params' => []],
        ]);
    }

    public function testStartInstanceSavesTheInstanceAndDispatchesExecuteNextStepForStepZero(): void
    {
        $definition = new FlowDefinition('Order Flow', [
            ['type' => 'log', 'params' => ['message' => 'done']],
        ]);

        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::once())
            ->method('get')
            ->with($definition->getId())
            ->willReturn($definition);

        $savedInstance = null;
        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(FlowInstance::class))
            ->willReturnCallback(static function (FlowInstance $instance) use (&$savedInstance): void {
                $savedInstance = $instance;
            });

        $dispatchedMessage = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $message;

                return new Envelope($message);
            });

        $instance = $this->createService($definitionRepository, $instanceRepository, $messageBus)
            ->startInstance($definition->getId(), ['order_id' => 'A-001']);

        self::assertSame($instance, $savedInstance);
        self::assertSame($definition->getId(), $instance->getDefinitionId());
        self::assertSame(FlowInstanceStatus::Running, $instance->getStatus());
        self::assertSame(0, $instance->getCurrentStepIndex());
        self::assertSame(['order_id' => 'A-001'], $instance->getContext());

        // 第一步的執行命令必須指向剛建立的實例、從 step 0 開始
        self::assertInstanceOf(ExecuteNextStep::class, $dispatchedMessage);
        self::assertSame($instance->getId(), $dispatchedMessage->instanceId);
        self::assertSame(0, $dispatchedMessage->stepIndex);
    }

    public function testStartInstanceThrowsWhenDefinitionIsMissing(): void
    {
        $definitionId = 'a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d';

        $definitionRepository = $this->createMock(FlowDefinitionRepository::class);
        $definitionRepository->expects(self::once())
            ->method('get')
            ->with($definitionId)
            ->willThrowException(FlowDefinitionNotFound::withId($definitionId));

        // 定義不存在時不得留下孤兒實例、也不得派送執行命令
        $instanceRepository = $this->createMock(FlowInstanceRepository::class);
        $instanceRepository->expects(self::never())
            ->method('save');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $service = $this->createService($definitionRepository, $instanceRepository, $messageBus);

        $this->expectException(FlowDefinitionNotFound::class);

        $service->startInstance($definitionId, []);
    }

    private function createService(
        FlowDefinitionRepository $definitionRepository,
        FlowInstanceRepository $instanceRepository,
        MessageBusInterface $messageBus,
    ): FlowService {
        return new FlowService($definitionRepository, $instanceRepository, [$this->createExecutor()], $messageBus);
    }

    /**
     * 模擬 tagged iterator：只支援 set 與 log 兩種 type。
     */
    private function createExecutor(): StepExecutor
    {
        $executor = self::createStub(StepExecutor::class);
        $executor->method('supports')
            ->willReturnCallback(static fn (string $type): bool => \in_array($type, ['set', 'log'], true));

        return $executor;
    }
}
