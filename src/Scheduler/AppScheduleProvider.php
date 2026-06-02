<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\PlanDayMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Schedule consumed by the worker via `messenger:consume scheduler_app`.
 *
 * Plans the day's fake-walk notifications every morning at 00:05 (Europe/Paris).
 */
#[AsSchedule('app')]
final class AppScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron('5 0 * * *', new PlanDayMessage(), new \DateTimeZone($this->timezone)),
            )
            // Persist run state so a missed trigger (downtime) is caught up on restart.
            ->stateful($this->cache)
        ;
    }
}
