<?php

declare(strict_types=1);

namespace App\FlowEngine\Domain;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * 流程執行實例：由 ExecuteNextStep 訊息逐步驅動，直到完成或失敗（終態）。
 */
#[ORM\Entity]
#[ORM\Table(name: 'flow_instances', schema: 'flow_engine')]
#[ORM\Index(name: 'idx_flow_instances_definition_id', columns: ['definition_id'])]
final class FlowInstance
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    // 跨 aggregate 以 id 引用，不用 ORM 關聯
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $definitionId;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FlowInstanceStatus::class)]
    private FlowInstanceStatus $status;

    #[ORM\Column(type: Types::INTEGER)]
    private int $currentStepIndex;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $context;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    // 樂觀鎖：防止並發 flush 的 lost update（DB 層最後防線）。
    // 順序性的重複投遞由 ExecuteNextStep.stepIndex 的 step 級冪等擋下，不是靠這裡
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $definitionId, array $context)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->definitionId = $definitionId;
        $this->status = FlowInstanceStatus::Running;
        $this->currentStepIndex = 0;
        $this->context = $context;
        $this->error = null;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * @param array<string, mixed> $newContext
     */
    public function advance(array $newContext): void
    {
        if (!$this->isRunning()) {
            throw InvalidFlowTransition::advance($this->status);
        }

        ++$this->currentStepIndex;
        $this->context = $newContext;
        $this->touch();
    }

    /**
     * @param array<string, mixed> $newContext
     */
    public function complete(array $newContext): void
    {
        if (!$this->isRunning()) {
            throw InvalidFlowTransition::complete($this->status);
        }

        $this->status = FlowInstanceStatus::Completed;
        $this->context = $newContext;
        $this->touch();
    }

    public function fail(string $error): void
    {
        if (!$this->isRunning()) {
            throw InvalidFlowTransition::fail($this->status);
        }

        $this->status = FlowInstanceStatus::Failed;
        $this->error = $error;
        $this->touch();
    }

    public function isRunning(): bool
    {
        return FlowInstanceStatus::Running === $this->status;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getStatus(): FlowInstanceStatus
    {
        return $this->status;
    }

    public function getCurrentStepIndex(): int
    {
        return $this->currentStepIndex;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
