<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SettingsRepository;
use Barlito\Utils\Traits\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Single-row application settings, editable from the backoffice.
 */
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
class Settings
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** ntfy topic (the "flux"). Empty falls back to the NTFY_TOPIC env var. */
    #[ORM\Column(length: 120)]
    private string $ntfyTopic = '';

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNtfyTopic(): string
    {
        return $this->ntfyTopic;
    }

    public function setNtfyTopic(string $ntfyTopic): self
    {
        $this->ntfyTopic = trim($ntfyTopic);

        return $this;
    }
}
