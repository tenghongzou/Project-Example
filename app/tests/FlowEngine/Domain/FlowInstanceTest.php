<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Domain;

use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceStatus;
use App\FlowEngine\Domain\InvalidFlowTransition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class FlowInstanceTest extends TestCase
{
    private const DEFINITION_ID = '0198c0de-0000-7000-8000-00000000000f';

    public function testNewInstanceIsRunningAtStepZeroWithGivenContext(): void
    {
        $instance = new FlowInstance(self::DEFINITION_ID, ['foo' => 'bar']);

        self::assertTrue(Uuid::isValid($instance->getId()));
        self::assertSame(self::DEFINITION_ID, $instance->getDefinitionId());
        self::assertSame(FlowInstanceStatus::Running, $instance->getStatus());
        self::assertTrue($instance->isRunning());
        self::assertSame(0, $instance->getCurrentStepIndex());
        self::assertSame(['foo' => 'bar'], $instance->getContext());
        self::assertNull($instance->getError());
    }

    public function testAdvanceIncrementsStepIndexAndReplacesContext(): void
    {
        $instance = new FlowInstance(self::DEFINITION_ID, ['foo' => 'bar']);

        $instance->advance(['foo' => 'bar', 'step' => 1]);

        self::assertSame(1, $instance->getCurrentStepIndex());
        self::assertSame(['foo' => 'bar', 'step' => 1], $instance->getContext());
        self::assertTrue($instance->isRunning());

        $instance->advance(['step' => 2]);

        self::assertSame(2, $instance->getCurrentStepIndex());
        self::assertSame(['step' => 2], $instance->getContext());
    }

    public function testCompleteIsATerminalStateWithFinalContext(): void
    {
        $instance = new FlowInstance(self::DEFINITION_ID, []);

        $instance->complete(['result' => 'done']);

        self::assertSame(FlowInstanceStatus::Completed, $instance->getStatus());
        self::assertFalse($instance->isRunning());
        self::assertSame(['result' => 'done'], $instance->getContext());
        self::assertNull($instance->getError());
    }

    public function testFailIsATerminalStateAndRecordsTheError(): void
    {
        $instance = new FlowInstance(self::DEFINITION_ID, []);

        $instance->fail('step exploded');

        self::assertSame(FlowInstanceStatus::Failed, $instance->getStatus());
        self::assertFalse($instance->isRunning());
        self::assertSame('step exploded', $instance->getError());
    }

    public function testCompletedInstanceRejectsAdvance(): void
    {
        $instance = $this->completedInstance();

        $this->expectException(InvalidFlowTransition::class);

        $instance->advance([]);
    }

    public function testCompletedInstanceRejectsComplete(): void
    {
        $instance = $this->completedInstance();

        $this->expectException(InvalidFlowTransition::class);

        $instance->complete([]);
    }

    public function testCompletedInstanceRejectsFail(): void
    {
        $instance = $this->completedInstance();

        $this->expectException(InvalidFlowTransition::class);

        $instance->fail('too late');
    }

    public function testFailedInstanceRejectsAdvance(): void
    {
        $instance = $this->failedInstance();

        $this->expectException(InvalidFlowTransition::class);

        $instance->advance([]);
    }

    public function testFailedInstanceRejectsComplete(): void
    {
        $instance = $this->failedInstance();

        $this->expectException(InvalidFlowTransition::class);

        $instance->complete([]);
    }

    public function testFailedInstanceRejectsFail(): void
    {
        $instance = $this->failedInstance();

        $this->expectException(InvalidFlowTransition::class);

        $instance->fail('again');
    }

    private function completedInstance(): FlowInstance
    {
        $instance = new FlowInstance(self::DEFINITION_ID, []);
        $instance->complete([]);

        return $instance;
    }

    private function failedInstance(): FlowInstance
    {
        $instance = new FlowInstance(self::DEFINITION_ID, []);
        $instance->fail('boom');

        return $instance;
    }
}
