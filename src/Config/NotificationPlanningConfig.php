<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Daily fake-walk planning settings, bound from environment variables.
 *
 * Kept as a small value object so the planning logic takes one dependency instead
 * of five scalars — and so a future editable Settings entity can replace it cleanly.
 */
final class NotificationPlanningConfig
{
    public function __construct(
        #[Autowire('%env(APP_TIMEZONE)%')]
        public readonly string $timezone,
        #[Autowire('%env(NOTIF_WINDOW_START)%')]
        public readonly string $windowStart,
        #[Autowire('%env(NOTIF_WINDOW_END)%')]
        public readonly string $windowEnd,
        #[Autowire('%env(int:NOTIF_PER_DAY)%')]
        public readonly int $perDay,
        #[Autowire('%env(int:NOTIF_MIN_GAP_MINUTES)%')]
        public readonly int $minGapMinutes,
    ) {
    }
}
