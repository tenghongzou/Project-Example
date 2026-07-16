<?php

declare(strict_types=1);

namespace App\Tests\Notification\Presentation;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NotificationControllerTest extends WebTestCase
{
    public function testListContainsThePersistedNotification(): void
    {
        $client = self::createClient();
        $notification = $this->persistNotification();

        $client->request('GET', '/api/notifications');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = $this->decodeResponse($client);
        self::assertArrayHasKey('data', $payload);

        $ids = array_column($payload['data'], 'id');
        self::assertContains($notification->getId(), $ids);
    }

    public function testShowReturnsTheNotification(): void
    {
        $client = self::createClient();
        $notification = $this->persistNotification();

        $client->request('GET', '/api/notifications/'.$notification->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = $this->decodeResponse($client);
        self::assertSame($notification->getId(), $payload['id']);
        self::assertSame('log', $payload['channel']);
        self::assertSame('Notification: event created', $payload['subject']);
        self::assertSame('Event "Team Meetup" was created.', $payload['body']);
        self::assertSame(['event_id' => 'e-1', 'name' => 'Team Meetup'], $payload['context']);
        self::assertSame('sent', $payload['status']);
        self::assertNull($payload['error']);
        self::assertNotNull($payload['sent_at']);
    }

    public function testShowReturnsNotFoundForUnknownId(): void
    {
        $client = self::createClient();

        // 合法格式但不存在的 UUID（nil UUID 會被路由的 Requirement::UUID 擋掉，進不到 controller）
        $client->request('GET', '/api/notifications/a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // 業務錯誤統一 RFC 7807 problem+json 形狀
        /** @var array{type: string, title: string, status: int, detail: string} $problem */
        $problem = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_NOT_FOUND, $problem['status']);
        self::assertArrayHasKey('detail', $problem);
    }

    private function persistNotification(): Notification
    {
        $notification = new Notification(
            'log',
            'Notification: event created',
            'Event "Team Meetup" was created.',
            ['event_id' => 'e-1', 'name' => 'Team Meetup'],
        );
        $notification->markSent();

        $repository = self::getContainer()->get(NotificationRepository::class);
        \assert($repository instanceof NotificationRepository);
        $repository->save($notification);

        return $notification;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(KernelBrowser $client): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
