<?php

declare(strict_types=1);

namespace App\Tests\Orchestration\Infrastructure;

use App\Orchestration\Infrastructure\Temporal\WorkflowClientFactory;
use PHPUnit\Framework\TestCase;
use Temporal\Client\WorkflowClientInterface;

final class WorkflowClientFactoryTest extends TestCase
{
    public function testCreateReturnsAWorkflowClient(): void
    {
        // Temporal SDK 在建構 ServiceClient 時硬性檢查 ext-grpc（BaseClient::__construct），
        // 本機沒裝就跳過；CI 的 PHP 有裝 grpc，會在那裡實際執行。
        // gRPC 連線本身是 lazy 的，建 client 不需要 Temporal server 在線
        if (!\extension_loaded('grpc')) {
            self::markTestSkipped('ext-grpc is required to construct the Temporal service client.');
        }

        $client = WorkflowClientFactory::create('localhost:7233');

        self::assertInstanceOf(WorkflowClientInterface::class, $client);
    }
}
