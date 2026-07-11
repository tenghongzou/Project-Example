<?php

declare(strict_types=1);

namespace App\FlowEngine\Infrastructure;

use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceNotFound;
use App\FlowEngine\Domain\FlowInstanceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFlowInstanceRepository implements FlowInstanceRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(FlowInstance $instance): void
    {
        $this->entityManager->persist($instance);
        $this->entityManager->flush();
    }

    public function get(string $id): FlowInstance
    {
        $instance = $this->entityManager->find(FlowInstance::class, $id);

        if (null === $instance) {
            throw FlowInstanceNotFound::withId($id);
        }

        return $instance;
    }
}
