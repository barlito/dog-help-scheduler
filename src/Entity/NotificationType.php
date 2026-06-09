<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationTypeRepository;
use Barlito\Utils\Traits\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A configurable kind of notification (e.g. "fake walk", "give water"), carrying both
 * its message content and its own daily planning window. Managed from the backoffice.
 */
#[ORM\Entity(repositoryClass: NotificationTypeRepository::class)]
class NotificationType implements \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Stable machine key (e.g. "fake_walk"). */
    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9_]+$/', message: 'Lettres minuscules, chiffres et "_" uniquement.')]
    private string $key = '';

    /** Human-readable name shown in the backoffice. */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $label = '';

    /** ntfy notification title. */
    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private string $title = '';

    /** ntfy notification message body. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
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
    #[Assert\Regex('/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Format attendu HH:MM.')]
    private string $windowStart = '08:00';

    /** Daily window end, "HH:MM". */
    #[ORM\Column(length: 5)]
    #[Assert\Regex('/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Format attendu HH:MM.')]
    private string $windowEnd = '20:00';

    /** Number of notifications planned per day. */
    #[ORM\Column]
    #[Assert\Positive]
    private int $perDay = 4;

    /** Minimum minutes between two notifications of this type. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $minGapMinutes = 60;

    /** When the user taps "Reporter", re-send after this many minutes. */
    #[ORM\Column]
    #[Assert\Positive]
    private int $postponeMinutes = 10;

    /** Random extra delay on top of the postpone: a draw between 1 and this many minutes (0 disables it). */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $postponeJitterMaxMinutes = 5;

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

    public function getPostponeMinutes(): int
    {
        return $this->postponeMinutes;
    }

    public function setPostponeMinutes(int $postponeMinutes): self
    {
        $this->postponeMinutes = $postponeMinutes;

        return $this;
    }

    public function getPostponeJitterMaxMinutes(): int
    {
        return $this->postponeJitterMaxMinutes;
    }

    public function setPostponeJitterMaxMinutes(int $postponeJitterMaxMinutes): self
    {
        $this->postponeJitterMaxMinutes = $postponeJitterMaxMinutes;

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

    /**
     * Reject windows the scheduler could not honour, so the error shows in the form
     * instead of crashing the planner later.
     */
    #[Assert\Callback]
    public function validatePlanning(ExecutionContextInterface $context): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $this->windowStart) || !preg_match('/^\d{2}:\d{2}$/', $this->windowEnd)) {
            return; // invalid time format already reported by the field constraints
        }

        $start = (int) substr($this->windowStart, 0, 2) * 60 + (int) substr($this->windowStart, 3, 2);
        $end = (int) substr($this->windowEnd, 0, 2) * 60 + (int) substr($this->windowEnd, 3, 2);

        if ($end <= $start) {
            $context->buildViolation('La fin de fenêtre doit être après le début.')
                ->atPath('windowEnd')->addViolation()
            ;

            return;
        }

        if (($end - $start) < ($this->perDay - 1) * $this->minGapMinutes) {
            $context->buildViolation('Impossible de caser {{ count }} notifications espacées de {{ gap }} min dans cette fenêtre.')
                ->setParameter('{{ count }}', (string) $this->perDay)
                ->setParameter('{{ gap }}', (string) $this->minGapMinutes)
                ->atPath('perDay')->addViolation()
            ;
        }
    }
}
