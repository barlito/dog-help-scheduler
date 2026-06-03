<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A configurable kind of notification (e.g. "fake walk", "give water"), carrying both
 * its message content and its own daily planning window. Managed from the backoffice.
 */
#[ORM\Entity(repositoryClass: NotificationTypeRepository::class)]
class NotificationType implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Stable machine key (e.g. "fake_walk"). */
    #[ORM\Column(length: 64, unique: true)]
    private string $key = '';

    /** Human-readable name shown in the backoffice. */
    #[ORM\Column(length: 120)]
    private string $label = '';

    /** ntfy notification title. */
    #[ORM\Column(length: 200)]
    private string $title = '';

    /** ntfy notification message body. */
    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    /**
     * ntfy tags (emojis/keywords).
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    /** Daily window start, "HH:MM". */
    #[ORM\Column(length: 5)]
    private string $windowStart = '08:00';

    /** Daily window end, "HH:MM". */
    #[ORM\Column(length: 5)]
    private string $windowEnd = '20:00';

    /** Number of notifications planned per day. */
    #[ORM\Column]
    private int $perDay = 4;

    /** Minimum minutes between two notifications of this type. */
    #[ORM\Column]
    private int $minGapMinutes = 60;

    /** When false, the scheduler skips this type entirely. */
    #[ORM\Column]
    private bool $enabled = true;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $tags
     */
    public function setTags(array $tags): self
    {
        $this->tags = array_values($tags);

        return $this;
    }

    public function getWindowStart(): string
    {
        return $this->windowStart;
    }

    public function setWindowStart(string $windowStart): self
    {
        $this->windowStart = $windowStart;

        return $this;
    }

    public function getWindowEnd(): string
    {
        return $this->windowEnd;
    }

    public function setWindowEnd(string $windowEnd): self
    {
        $this->windowEnd = $windowEnd;

        return $this;
    }

    public function getPerDay(): int
    {
        return $this->perDay;
    }

    public function setPerDay(int $perDay): self
    {
        $this->perDay = $perDay;

        return $this;
    }

    public function getMinGapMinutes(): int
    {
        return $this->minGapMinutes;
    }

    public function setMinGapMinutes(int $minGapMinutes): self
    {
        $this->minGapMinutes = $minGapMinutes;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function __toString(): string
    {
        return '' !== $this->label ? $this->label : $this->key;
    }
}
