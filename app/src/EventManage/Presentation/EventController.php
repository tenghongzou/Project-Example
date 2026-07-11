<?php

declare(strict_types=1);

namespace App\EventManage\Presentation;

use App\EventManage\Application\EventService;
use App\EventManage\Domain\Event;
use App\EventManage\Domain\EventNotFound;
use App\EventManage\Domain\EventRepository;
use App\EventManage\Domain\InvalidEventTransition;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/events')]
final class EventController extends AbstractController
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route('', name: 'event_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateEventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->name, $request->scheduledAt, $request->description);

        return $this->json(
            $this->toArray($event),
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('event_show', ['id' => $event->getId()])],
        );
    }

    #[Route('', name: 'event_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json([
            'data' => array_map($this->toArray(...), $this->eventRepository->list()),
        ]);
    }

    #[Route('/{id}', name: 'event_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $event = $this->eventRepository->get($id);
        } catch (EventNotFound $e) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', $e->getMessage());
        }

        return $this->json($this->toArray($event));
    }

    #[Route('/{id}/publish', name: 'event_publish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        return $this->transition(fn (): Event => $this->eventService->publish($id));
    }

    #[Route('/{id}/cancel', name: 'event_cancel', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        return $this->transition(fn (): Event => $this->eventService->cancel($id));
    }

    /**
     * @param callable(): Event $operation
     */
    private function transition(callable $operation): JsonResponse
    {
        try {
            $event = $operation();
        } catch (EventNotFound $e) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', $e->getMessage());
        } catch (InvalidEventTransition $e) {
            return $this->problem(Response::HTTP_CONFLICT, 'Conflict', $e->getMessage());
        } catch (OptimisticLockException) {
            return $this->problem(Response::HTTP_CONFLICT, 'Conflict', 'The event was modified concurrently, please retry.');
        }

        return $this->json($this->toArray($event));
    }

    /**
     * 業務錯誤統一用 RFC 7807 problem+json，與 MapRequestPayload 驗證失敗（422）的形狀一致.
     */
    private function problem(int $status, string $title, string $detail): JsonResponse
    {
        return $this->json(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    /**
     * 手動組回應陣列，避免 serializer 直接序列化 entity 而把內部欄位外洩成 API 契約。
     *
     * @return array{id: string, name: string, description: ?string, scheduled_at: string, status: string, created_at: string}
     */
    private function toArray(Event $event): array
    {
        return [
            'id' => $event->getId(),
            'name' => $event->getName(),
            'description' => $event->getDescription(),
            'scheduled_at' => $event->getScheduledAt()->format(\DateTimeInterface::ATOM),
            'status' => $event->getStatus()->value,
            'created_at' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
