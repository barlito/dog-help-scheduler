<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    /** A reminder to perform a fake departure to desensitise the dog. */
    case FAKE_WALK = 'fake_walk';

    public function label(): string
    {
        return match ($this) {
            self::FAKE_WALK => 'Fausse sortie',
        };
    }
}
