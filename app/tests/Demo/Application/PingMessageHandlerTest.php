<?php

declare(strict_types=1);

namespace App\Tests\Demo\Application;

use App\Demo\Application\Message\PingMessage;
use App\Demo\Application\MessageHandler\PingMessageHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PingMessageHandlerTest extends TestCase
{
    public function testHandlerLogsTheNote(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('PingMessage consumed', ['note' => 'hello']);

        (new PingMessageHandler($logger))(new PingMessage('hello'));
    }
}
