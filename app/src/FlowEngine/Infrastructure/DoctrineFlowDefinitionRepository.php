<?php

declare(strict_types=1);

namespace App\FlowEngine\Infrastructure;

use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionNotFound;
use App\FlowEngine\Domain\FlowDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFlowDefinitionRepository implements FlowDefinitionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(FlowDefinition $definition): void
    {
        $this->entityManager->persist($definition);
        $this->entityManager->flush();
    }

    public function get(string $id): FlowDefinition
    {
        $definition = $this->entityManager->find(FlowDefinition::class, $id);

        if (null === $definition) {
            throw FlowDefinitionNotFound::withId($id);
        }

        return $definition;
    }

    public function list(): array
    {
        /** @var list<FlowDefinition> $definitions */
        $definitions = $this->entityManager
            ->getRepository(FlowDefinition::class)
            ->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $definitions;
    }
}
