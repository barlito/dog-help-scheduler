<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use App\Repository\NotificationTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Plans a day's notifications: for each type, picks random times within its window and
 * dispatches a delayed SendNotificationMessage for each slot. Shared by the daily
 * Scheduler handler, the app:plan-day command and the backoffice "plan now" button.
 *
 * Planning is idempotent per type and day (see existsForDayAndType), so re-running is safe.
 */
final class DayPlanner
{
    public function __construct(
        private readonly RandomScheduleGenerator $generator,
        private readonly NotificationRepository $notifications,
        private readonly NotificationTypeRepository $types,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    /**
     * Plans every enabled type for the given moment's day (defaults to now).
     *
     * @return int number of notifications planned
     */
    public function planEnabled(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        $planned = [];
        foreach ($this->types->findEnabled() as $type) {
            foreach ($this->buildForType($type, $now) as $notification) {
                $planned[] = $notification;
            }
        }

        return $this->flushAndDispatch($planned, $now);
    }

    /**
     * Plans a single type for the given moment's day (defaults to now), regardless of
     * whether it is enabled. No-op if that type already has notifications for the day.
     *
     * @return int number of notifications planned (0 if already planned for the day)
     */
    public function planType(NotificationType $type, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        return $this->flushAndDispatch($this->buildForType($type, $now), $now);
    }

    /**
     * @return Notification[]
     */
    private function buildForType(NotificationType $type, \DateTimeImmutable $now): array
    {
        // Idempotency: never plan the same type twice for the same day.
        if ($this->notifications->existsForDayAndType($now, $type)) {
            return [];
        }

        $built = [];
        $times = $this->generator->generate(
            $now,
            $type->getWindowStart(),
            $type->getWindowEnd(),
            $type->getPerDay(),
            $type->getMinGapMinutes(),
        );
        foreach ($times as $time) {
            $notification = new Notification($type, $time);
            $this->em->persist($notification);
            $built[] = $notification;
        }

        return $built;
    }

    /**
     * @param Notification[] $planned
     *
     * @return int number of notifications planned
     */
    private function flushAndDispatch(array $planned, \DateTimeImmutable $now): int
    {
        $this->em->flush();

        foreach ($planned as $notification) {
            // Slots already past (e.g. a mid-day manual run) get a zero delay and fire now.
            $delayMs = max(0, ($notification->getScheduledAt()->getTimestamp() - $now->getTimestamp()) * 1000);
            $this->bus->dispatch(
                new SendNotificationMessage((string) $notification->getId()),
                [new DelayStamp($delayMs)],
            );
        }

        return \count($planned);
    }
}
