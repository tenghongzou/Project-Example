<?php

declare(strict_types=1);

namespace App\Notification\Presentation;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationNotFound;
use App\Notification\Domain\NotificationRepository;
use App\Shared\Presentation\ProblemResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/notifications')]
final class NotificationController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    #[Route('', name: 'notification_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json([
            'data' => array_map($this->toArray(...), $this->notificationRepository->list()),
        ]);
    }

    #[Route('/{id}', name: 'notification_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $notification = $this->notificationRepository->get($id);
        } catch (NotificationNotFound $e) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', $e->getMessage());
        }

        return $this->json($this->toArray($notification));
    }

    /**
     * 手動組回應陣列，避免 serializer 直接序列化 entity 而把內部欄位外洩成 API 契約。
     *
     * @return array{id: string, channel: string, subject: string, body: string, context: array<string, mixed>, status: string, error: ?string, created_at: string, sent_at: ?string}
     */
    private function toArray(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'channel' => $notification->getChannel(),
            'subject' => $notification->getSubject(),
            'body' => $notification->getBody(),
            'context' => $notification->getContext(),
            'status' => $notification->getStatus()->value,
            'error' => $notification->getError(),
            'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'sent_at' => $notification->getSentAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
