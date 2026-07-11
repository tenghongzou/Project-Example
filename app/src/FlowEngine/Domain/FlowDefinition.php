<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * 不可變的流程定義：有序步驟清單，建立後不再修改（無 setter）。
 */
#[ORM\Entity]
#[ORM\Table(name: 'flow_definitions', schema: 'flow_engine')]
final class FlowDefinition
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $name;

    /** @var list<array{type: string, params: array<string, mixed>}> */
    #[ORM\Column(type: Types::JSON)]
    private array $steps;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<int, array<string, mixed>> $steps
     */
    public function __construct(string $name, array $steps)
    {
        if ([] === $steps) {
            throw new \InvalidArgumentException('A flow definition must contain at least one step.');
        }

        // domain 自我防衛：不信任呼叫端的形狀（HTTP DTO 之外還有其他進入點）
        $validated = [];
        foreach ($steps as $step) {
            $type = $step['type'] ?? null;
            if (!\is_string($type) || '' === $type) {
                throw new \InvalidArgumentException('Each step must have a non-empty string "type".');
            }

            $params = $step['params'] ?? [];
            if (!\is_array($params)) {
                throw new \InvalidArgumentException('Step "params" must be an object.');
            }

            /* @var array<string, mixed> $params */
            $validated[] = ['type' => $type, 'params' => $params];
        }

        $this->id = Uuid::v7()->toRfc4122();
        $this->name = $name;
        $this->steps = $validated;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<array{type: string, params: array<string, mixed>}>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return array{type: string, params: array<string, mixed>}|null 超出範圍回傳 null
     */
    public function getStep(int $index): ?array
    {
        return $this->steps[$index] ?? null;
    }

    public function getStepCount(): int
    {
        return \count($this->steps);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
