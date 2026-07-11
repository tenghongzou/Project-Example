<?php

declare(strict_types=1);

namespace App\FlowEngine\Presentation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateFlowRequest
{
    /**
     * @param list<StepInput> $steps serializer 依此 phpdoc 反序列化巢狀 DTO
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        #[Assert\Valid]
        #[Assert\Count(min: 1, max: 50)]
        public array $steps,
    ) {
    }
}
