<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationStatus;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(name: 'idx_notification_scheduled_at', columns: ['scheduled_at'])]
#[ORM\Index(name: 'idx_notification_status', columns: ['status'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: NotificationType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private NotificationType $type;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(length: 16, enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::PLANNED;

    /** Random per-notification secret used to authorise the ntfy quick-reply callback. */
    #[ORM\Column(length: 36, unique: true)]
    private string $responseToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(NotificationType $type, \DateTimeImmutable $scheduledAt)
    {
        $this->type = $type;
        $this->scheduledAt = $scheduledAt;
        $this->status = NotificationStatus::PLANNED;
        $this->responseToken = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    /** Human-readable type, used by the backoffice display. */
    public function getTypeLabel(): string
    {
        return $this->type->getLabel();
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    /** Human-readable status, used by the backoffice display. */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    public function getResponseToken(): string
    {
        return $this->responseToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markSent(): void
    {
        $this->status = NotificationStatus::SENT;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function markFailed(): void
    {
        $this->status = NotificationStatus::FAILED;
    }

    /** A notification can be cancelled only while it is still planned (not yet sent). */
    public function isCancellable(): bool
    {
        return NotificationStatus::PLANNED === $this->status;
    }

    /**
     * Cancels a still-planned notification so the worker skips it when its slot comes.
     * Returns false if it can no longer be cancelled (already sent/answered/failed).
     */
    public function cancel(): bool
    {
        if (!$this->isCancellable()) {
            return false;
        }

        $this->status = NotificationStatus::CANCELLED;

        return true;
    }

    /** Records the user's quick reply. Returns false if a reply was already recorded. */
    public function recordResponse(NotificationStatus $status): bool
    {
        if (!$status->isAnswered()) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid response status.', $status->value));
        }

        if ($this->status->isAnswered()) {
            return false;
        }

        $this->status = $status;
        $this->respondedAt = new \DateTimeImmutable();

        return true;
    }
}
