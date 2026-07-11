<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

interface FlowDefinitionRepository
{
    public function save(FlowDefinition $definition): void;

    /**
     * @throws FlowDefinitionNotFound
     */
    public function get(string $id): FlowDefinition;

    /**
     * @return list<FlowDefinition> 依 createdAt 由新到舊排序
     */
    public function list(): array;
}
