<?php

declare(strict_types=1);

namespace App\Tests\EventManage\Presentation;

use App\EventManage\Application\Message\EventCancelled;
use App\EventManage\Application\Message\EventCreated;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class EventControllerTest extends WebTestCase
{
    public function testCreateReturnsDraftEventAndQueuesEventCreated(): void
    {
        $client = self::createClient();

        $payload = $this->createEvent($client);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNotSame('', $payload['id']);
        self::assertSame('draft', $payload['status']);
        self::assertSame('Team Meetup', $payload['name']);
        self::assertSame('A casual meetup', $payload['description']);
        // +08:00 的輸入應被正規化成 UTC 的同一時間點
        self::assertSame('2026-08-01T02:00:00+00:00', $payload['scheduled_at']);

        // 測試環境的 async transport 是 in-memory（見 messenger.yaml 的 when@test）
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent);

        $message = $sent[0]->getMessage();
        self::assertInstanceOf(EventCreated::class, $message);
        self::assertSame($payload['id'], $message->eventId);
        self::assertSame('Team Meetup', $message->name);
    }

    public function testShowReturnsTheEvent(): void
    {
        $client = self::createClient();
        $created = $this->createEvent($client);

        $client->request('GET', '/api/events/'.$created['id']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = $this->decodeResponse($client);
        self::assertSame($created['id'], $payload['id']);
        self::assertSame('Team Meetup', $payload['name']);
        self::assertSame('A casual meetup', $payload['description']);
        self::assertSame('2026-08-01T02:00:00+00:00', $payload['scheduled_at']);
        self::assertSame('draft', $payload['status']);
    }

    public function testShowReturnsNotFoundForUnknownId(): void
    {
        $client = self::createClient();

        // 合法格式但不存在的 UUID（nil UUID 會被路由的 Requirement::UUID 擋掉，進不到 controller）
        $client->request('GET', '/api/events/a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // 業務錯誤統一 RFC 7807 problem+json 形狀
        /** @var array{type: string, title: string, status: int, detail: string} $problem */
        $problem = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_NOT_FOUND, $problem['status']);
        self::assertArrayHasKey('detail', $problem);
    }

    public function testPublishTransitionsToPublishedAndRepublishingConflicts(): void
    {
        $client = self::createClient();
        $created = $this->createEvent($client);

        $client->request('POST', '/api/events/'.$created['id'].'/publish');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = $this->decodeResponse($client);
        self::assertSame('published', $payload['status']);

        $client->request('POST', '/api/events/'.$created['id'].'/publish');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        /** @var array{type: string, title: string, status: int, detail: string} $problem */
        $problem = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_CONFLICT, $problem['status']);
    }

    public function testCancelTransitionsToCancelledAndQueuesEventCancelled(): void
    {
        $client = self::createClient();
        $created = $this->createEvent($client);

        $client->request('POST', '/api/events/'.$created['id'].'/cancel');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = $this->decodeResponse($client);
        self::assertSame('cancelled', $payload['status']);

        // cancel 這個 request 內只會派送 EventCancelled（kernel 每個 request 重啟，transport 不跨 request 累積）
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(EventCancelled::class, $message);
        self::assertSame($created['id'], $message->eventId);
    }

    public function testCreateWithBlankNameIsRejected(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/events',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => '',
                'scheduledAt' => '2026-08-01T10:00:00+08:00',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testListReturnsDataArray(): void
    {
        $client = self::createClient();
        $this->createEvent($client);

        $client->request('GET', '/api/events');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        /** @var array{data: list<array<string, mixed>>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload['data']);
        self::assertNotEmpty($payload['data']);
    }

    /**
     * @return array{id: string, name: string, description: ?string, scheduled_at: string, status: string, created_at: string}
     */
    private function createEvent(KernelBrowser $client): array
    {
        $client->request(
            'POST',
            '/api/events',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Team Meetup',
                'scheduledAt' => '2026-08-01T10:00:00+08:00',
                'description' => 'A casual meetup',
            ], JSON_THROW_ON_ERROR),
        );

        return $this->decodeResponse($client);
    }

    /**
     * @return array{id: string, name: string, description: ?string, scheduled_at: string, status: string, created_at: string}
     */
    private function decodeResponse(KernelBrowser $client): array
    {
        /** @var array{id: string, name: string, description: ?string, scheduled_at: string, status: string, created_at: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
