<?php

declare(strict_types=1);

namespace App\Tests\Demo\Presentation;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class PingControllerTest extends WebTestCase
{
    public function testPingQueuesAMessage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/ping');

        self::assertResponseIsSuccessful();

        /** @var array{queued: bool, note: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['queued']);

        // 測試環境的 async transport 是 in-memory（見 messenger.yaml 的 when@test）
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertCount(1, $transport->getSent());
    }
}
