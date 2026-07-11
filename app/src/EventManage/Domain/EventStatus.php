<?php

declare(strict_types=1);

namespace App\EventManage\Domain;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
}
