<?php

declare(strict_types=1);

namespace App\EventManage\Domain;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'events', schema: 'event_manage')]
final class Event
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: EventStatus::class)]
    private EventStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // 樂觀鎖：並發的 read-modify-write 會在 flush 時丟 OptimisticLockException，
    // 防止 cancelled 被並發請求覆寫回 published 之類的非法終態
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function __construct(string $name, \DateTimeImmutable $scheduledAt, ?string $description = null)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->name = $name;
        // DB 欄位是 TIMESTAMP WITHOUT TIME ZONE，先正規化成 UTC 才不會偏移實際時間點
        $this->scheduledAt = $scheduledAt->setTimezone(new \DateTimeZone('UTC'));
        $this->description = $description;
        $this->status = EventStatus::Draft;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function publish(): void
    {
        if (EventStatus::Draft !== $this->status) {
            throw InvalidEventTransition::publish($this->status);
        }

        $this->status = EventStatus::Published;
    }

    public function cancel(): void
    {
        if (EventStatus::Cancelled === $this->status) {
            throw InvalidEventTransition::cancel();
        }

        $this->status = EventStatus::Cancelled;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
