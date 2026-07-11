<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * 業務錯誤統一用 RFC 7807 problem+json，與 MapRequestPayload 驗證失敗（422）的形狀一致.
 *
 * 供繼承 AbstractController 的控制器使用（依賴其 json() 方法）。
 */
trait ProblemResponseTrait
{
    private function problem(int $status, string $title, string $detail): JsonResponse
    {
        return $this->json(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
