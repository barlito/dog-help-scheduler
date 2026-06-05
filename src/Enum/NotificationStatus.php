<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationStatus: string
{
    /** Time slot reserved for today, not yet sent. */
    case PLANNED = 'planned';
    /** Pushed to ntfy, awaiting the user's reply. */
    case SENT = 'sent';
    /** User confirmed the fake walk was done. */
    case VALIDATED = 'validated';
    /** User chose to postpone it. */
    case POSTPONED = 'postponed';
    /** User explicitly marked it as not done. */
    case NOT_DONE = 'not_done';
    /** Sending to ntfy failed. */
    case FAILED = 'failed';
    /** Cancelled from the backoffice before it was sent (e.g. day away from home). */
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planifiée',
            self::SENT => 'Envoyée',
            self::VALIDATED => 'Validée',
            self::POSTPONED => 'Reportée',
            self::NOT_DONE => 'Non effectuée',
            self::FAILED => 'Échec',
            self::CANCELLED => 'Annulée',
        };
    }

    /** EasyAdmin badge colour. */
    public function color(): string
    {
        return match ($this) {
            self::PLANNED => 'secondary',
            self::SENT => 'info',
            self::VALIDATED => 'success',
            self::POSTPONED => 'warning',
            self::NOT_DONE => 'danger',
            self::FAILED => 'dark',
            self::CANCELLED => 'light',
        };
    }

    /** True once the user has answered (any of the three quick replies). */
    public function isAnswered(): bool
    {
        return \in_array($this, [self::VALIDATED, self::POSTPONED, self::NOT_DONE], true);
    }
}
