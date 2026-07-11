<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Infrastructure;

use App\FlowEngine\Application\StepExecutionFailed;
use App\FlowEngine\Infrastructure\Executor\FailStepExecutor;
use App\FlowEngine\Infrastructure\Executor\LogStepExecutor;
use App\FlowEngine\Infrastructure\Executor\SetContextStepExecutor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class StepExecutorsTest extends TestCase
{
    public function testLogStepExecutorSupportsOnlyLogType(): void
    {
        $executor = new LogStepExecutor(new NullLogger());

        self::assertTrue($executor->supports('log'));
        self::assertFalse($executor->supports('set'));
        self::assertFalse($executor->supports('fail'));
    }

    public function testLogStepExecutorLogsTheMessageAndReturnsContextUnchanged(): void
    {
        // log message 固定字串、使用者資料放 context（log injection 防護）
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Flow step log', ['message' => 'step reached', 'context' => ['foo' => 'bar']]);

        $executor = new LogStepExecutor($logger);

        $result = $executor->execute(['message' => 'step reached'], ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $result);
    }

    public function testSetContextStepExecutorSupportsOnlySetType(): void
    {
        $executor = new SetContextStepExecutor();

        self::assertTrue($executor->supports('set'));
        self::assertFalse($executor->supports('log'));
    }

    public function testSetContextStepExecutorWritesKeyValueIntoContext(): void
    {
        $executor = new SetContextStepExecutor();

        $result = $executor->execute(['key' => 'answer', 'value' => 42], ['existing' => true]);

        self::assertSame(['existing' => true, 'answer' => 42], $result);
    }

    public function testSetContextStepExecutorRejectsMissingKeyParam(): void
    {
        $executor = new SetContextStepExecutor();

        $this->expectException(StepExecutionFailed::class);

        $executor->execute(['value' => 42], []);
    }

    public function testFailStepExecutorSupportsOnlyFailType(): void
    {
        $executor = new FailStepExecutor();

        self::assertTrue($executor->supports('fail'));
        self::assertFalse($executor->supports('log'));
    }

    public function testFailStepExecutorThrowsRuntimeExceptionWithMessageFromParams(): void
    {
        $executor = new FailStepExecutor();

        $this->expectException(StepExecutionFailed::class);
        $this->expectExceptionMessage('custom failure reason');

        $executor->execute(['message' => 'custom failure reason'], []);
    }
}
