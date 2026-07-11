<?php

declare(strict_types=1);

namespace App\FlowEngine\Presentation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StepInput
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $type,
        public array $params = [],
    ) {
    }
}
