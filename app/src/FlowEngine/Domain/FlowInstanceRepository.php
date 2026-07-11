<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

interface FlowInstanceRepository
{
    public function save(FlowInstance $instance): void;

    /**
     * @throws FlowInstanceNotFound
     */
    public function get(string $id): FlowInstance;
}
