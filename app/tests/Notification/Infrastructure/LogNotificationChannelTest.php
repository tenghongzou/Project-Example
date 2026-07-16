<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure;

use App\Notification\Domain\Notification;
use App\Notification\Infrastructure\Channel\LogNotificationChannel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LogNotificationChannelTest extends TestCase
{
    public function testSupportsOnlyTheLogChannel(): void
    {
        $channel = new LogNotificationChannel($this->createStub(LoggerInterface::class));

        self::assertTrue($channel->supports('log'));
        self::assertFalse($channel->supports('email'));
    }

    public function testSendLogsAtInfoLevelByDefault(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with('info', 'Notification: event created', ['event_id' => 'e-1']);

        (new LogNotificationChannel($logger))->send(
            new Notification('log', 'Notification: event created', 'Body', ['event_id' => 'e-1']),
        );
    }

    public function testSendHonoursTheSeverityContextKey(): void
    {
        $context = ['instance_id' => 'i-1', 'severity' => 'warning'];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with('warning', 'Notification: flow instance failed', $context);

        (new LogNotificationChannel($logger))->send(
            new Notification('log', 'Notification: flow instance failed', 'Body', $context),
        );
    }

    public function testSendFallsBackToInfoForAnUnknownSeverity(): void
    {
        $context = ['severity' => 'catastrophic'];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with('info', 'Subject', $context);

        (new LogNotificationChannel($logger))->send(
            new Notification('log', 'Subject', 'Body', $context),
        );
    }
}
