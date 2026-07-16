<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notifications', schema: 'notification')]
final class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $channel;

    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $context;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: NotificationStatus::class)]
    private NotificationStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    // 樂觀鎖：與其他模組一致，防止並發 read-modify-write 覆寫終態
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $channel, string $subject, string $body, array $context = [])
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->channel = $channel;
        $this->subject = $subject;
        $this->body = $body;
        $this->context = $context;
        $this->status = NotificationStatus::Pending;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function markSent(): void
    {
        if (NotificationStatus::Pending !== $this->status) {
            throw InvalidNotificationTransition::markSent($this->status);
        }

        $this->status = NotificationStatus::Sent;
        $this->sentAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function markFailed(string $error): void
    {
        if (NotificationStatus::Pending !== $this->status) {
            throw InvalidNotificationTransition::markFailed($this->status);
        }

        $this->status = NotificationStatus::Failed;
        $this->error = $error;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
