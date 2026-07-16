<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthReportsPostgresRedisAndPhp(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        // 需要活的 Postgres 與 Redis（cache.app 走 Redis adapter）
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        /** @var array{status: string, postgres: string, redis_cached_at: string, php: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $payload['status']);
        self::assertStringContainsString('PostgreSQL', $payload['postgres']);
        // redis_cached_at 是 DATE_ATOM 格式的快取時間戳
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, $payload['redis_cached_at']));
        self::assertSame(PHP_VERSION, $payload['php']);
    }
}
