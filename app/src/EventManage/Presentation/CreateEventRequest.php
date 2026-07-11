<?php

declare(strict_types=1);

namespace App\EventManage\Presentation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateEventRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        #[Assert\NotBlank]
        public \DateTimeImmutable $scheduledAt,
        public ?string $description = null,
    ) {
    }
}
