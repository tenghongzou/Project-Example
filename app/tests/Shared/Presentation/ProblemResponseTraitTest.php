<?php

declare(strict_types=1);

namespace App\Tests\Shared\Presentation;

use App\Shared\Presentation\ProblemResponseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProblemResponseTraitTest extends TestCase
{
    public function testProblemBuildsAnRfc7807JsonResponse(): void
    {
        $response = $this->createController()->buildProblem(
            Response::HTTP_NOT_FOUND,
            'Not Found',
            'Event not found.',
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int, detail: string} $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('about:blank', $payload['type']);
        self::assertSame('Not Found', $payload['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $payload['status']);
        self::assertSame('Event not found.', $payload['detail']);
    }

    public function testProblemStatusInBodyMatchesHttpStatusForConflict(): void
    {
        $response = $this->createController()->buildProblem(
            Response::HTTP_CONFLICT,
            'Conflict',
            'Event is already published.',
        );

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        /** @var array{type: string, title: string, status: int, detail: string} $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($response->getStatusCode(), $payload['status']);
        self::assertSame('Conflict', $payload['title']);
        self::assertSame('Event is already published.', $payload['detail']);
    }

    /**
     * trait 依賴 AbstractController::json()，用匿名 controller 搭配空容器來驅動真實行為.
     */
    private function createController(): object
    {
        $controller = new class extends AbstractController {
            use ProblemResponseTrait;

            public function buildProblem(int $status, string $title, string $detail): JsonResponse
            {
                return $this->problem($status, $title, $detail);
            }
        };

        $controller->setContainer(new Container());

        return $controller;
    }
}
