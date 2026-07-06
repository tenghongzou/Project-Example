<?php

declare(strict_types=1);

namespace App\Demo\Application\Message;

final readonly class PingMessage
{
    public function __construct(
        public string $note,
    ) {
    }
}
