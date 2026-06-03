<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched once a day by the Scheduler. Its handler picks the day's random
 * notification times and schedules each one for delivery.
 */
final class PlanDayMessage
{
}
