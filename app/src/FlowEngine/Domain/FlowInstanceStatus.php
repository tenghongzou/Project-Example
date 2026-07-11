<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

enum FlowInstanceStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
