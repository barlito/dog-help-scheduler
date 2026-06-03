<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Immutable description of an ntfy notification payload (transport-agnostic).
 */
final readonly class NtfyMessage
{
    /**
     * @param string[]                         $tags
     * @param array<int, array<string, mixed>> $actions
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $tags = [],
        public array $actions = [],
        public ?string $icon = null,
    ) {
    }
}
