<?php

declare(strict_types=1);

namespace App\Orchestration\Infrastructure\Temporal;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;

final class WorkflowClientFactory
{
    public static function create(string $address): WorkflowClientInterface
    {
        return WorkflowClient::create(ServiceClient::create($address));
    }
}
