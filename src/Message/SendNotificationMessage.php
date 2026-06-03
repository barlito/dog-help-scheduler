<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched with a DelayStamp so the worker pushes the ntfy notification at the
 * randomly-picked time.
 */
final class SendNotificationMessage
{
    /** @param string $notificationId the notification UUID (RFC 4122) */
    public function __construct(public readonly string $notificationId)
    {
    }
}
