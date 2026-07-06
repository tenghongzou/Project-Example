<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health')]
    public function __invoke(Connection $connection, CacheInterface $cache): JsonResponse
    {
        $pgVersion = (string) $connection->fetchOne('SELECT version()');

        $cachedAt = $cache->get('health.cached_at', function (ItemInterface $item): string {
            $item->expiresAfter(30);

            return date(DATE_ATOM);
        });

        return $this->json([
            'status' => 'ok',
            'postgres' => $pgVersion,
            'redis_cached_at' => $cachedAt,
            'php' => PHP_VERSION,
        ]);
    }
}
