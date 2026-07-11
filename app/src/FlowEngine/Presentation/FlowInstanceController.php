<?php

declare(strict_types=1);

namespace App\FlowEngine\Presentation;

use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceNotFound;
use App\FlowEngine\Domain\FlowInstanceRepository;
use App\Shared\Presentation\ProblemResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/flow-instances')]
final class FlowInstanceController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly FlowInstanceRepository $instanceRepository,
    ) {
    }

    #[Route('/{id}', name: 'flow_instance_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $instance = $this->instanceRepository->get($id);
        } catch (FlowInstanceNotFound $e) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', $e->getMessage());
        }

        return $this->json($this->toArray($instance));
    }

    /**
     * @return array{id: string, definition_id: string, status: string, current_step_index: int, context: array<string, mixed>, error: ?string, created_at: string, updated_at: string}
     */
    private function toArray(FlowInstance $instance): array
    {
        return [
            'id' => $instance->getId(),
            'definition_id' => $instance->getDefinitionId(),
            'status' => $instance->getStatus()->value,
            'current_step_index' => $instance->getCurrentStepIndex(),
            'context' => $instance->getContext(),
            'error' => $instance->getError(),
            'created_at' => $instance->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $instance->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
