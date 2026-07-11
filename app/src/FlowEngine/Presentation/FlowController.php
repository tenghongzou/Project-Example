<?php

declare(strict_types=1);

namespace App\FlowEngine\Presentation;

use App\FlowEngine\Application\FlowService;
use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionNotFound;
use App\FlowEngine\Domain\FlowDefinitionRepository;
use App\FlowEngine\Domain\FlowInstance;
use App\Shared\Presentation\ProblemResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/flows')]
final class FlowController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly FlowService $flowService,
        private readonly FlowDefinitionRepository $definitionRepository,
    ) {
    }

    #[Route('', name: 'flow_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateFlowRequest $request): JsonResponse
    {
        try {
            $definition = $this->flowService->createDefinition(
                $request->name,
                array_map(
                    static fn (StepInput $step): array => ['type' => $step->type, 'params' => $step->params],
                    $request->steps,
                ),
            );
        } catch (\InvalidArgumentException $e) {
            // UnknownStepType 與 domain 的 steps 驗證：建立時 fail-fast 回 422
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'Unprocessable Entity', $e->getMessage());
        }

        return $this->json(
            $this->toArray($definition),
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('flow_show', ['id' => $definition->getId()])],
        );
    }

    #[Route('', name: 'flow_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json([
            'data' => array_map($this->toArray(...), $this->definitionRepository->list()),
        ]);
    }

    #[Route('/{id}', name: 'flow_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $definition = $this->definitionRepository->get($id);
        } catch (FlowDefinitionNotFound $e) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', $e->getMessage());
        }

        return $this->json($this->toArray($definition));
    }

    #[Route('/{id}/instances', name: 'flow_instance_start', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function startInstance(string $id, #[MapRequestPayload] StartFlowInstanceRequest $request): JsonResponse
    {
        try {
            $instance = $this->flowService->startInstance($id, $request->context);
        } catch (FlowDefinitionNotFound $e) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', $e->getMessage());
        }

        // 執行是非同步的（worker 逐步消費 ExecuteNextStep），故回 202 而非 201
        return $this->json(
            $this->instanceToArray($instance),
            Response::HTTP_ACCEPTED,
            ['Location' => $this->generateUrl('flow_instance_show', ['id' => $instance->getId()])],
        );
    }

    /**
     * 手動組回應陣列，避免 serializer 直接序列化 entity 而把內部欄位外洩成 API 契約。
     *
     * @return array{id: string, name: string, steps: list<array{type: string, params: array<string, mixed>}>, created_at: string}
     */
    private function toArray(FlowDefinition $definition): array
    {
        return [
            'id' => $definition->getId(),
            'name' => $definition->getName(),
            'steps' => $definition->getSteps(),
            'created_at' => $definition->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{id: string, definition_id: string, status: string, current_step_index: int, context: array<string, mixed>, error: ?string, created_at: string, updated_at: string}
     */
    private function instanceToArray(FlowInstance $instance): array
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
