<?php

declare(strict_types=1);

namespace App\Notification\Domain;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
