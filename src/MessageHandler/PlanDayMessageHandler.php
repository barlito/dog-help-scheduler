<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\PlanDayMessage;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use App\Service\RandomScheduleGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class PlanDayMessageHandler
{
    public function __construct(
        private readonly RandomScheduleGenerator $generator,
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
        #[Autowire('%env(NOTIF_WINDOW_START)%')]
        private readonly string $windowStart,
        #[Autowire('%env(NOTIF_WINDOW_END)%')]
        private readonly string $windowEnd,
        #[Autowire('%env(int:NOTIF_PER_DAY)%')]
        private readonly int $perDay,
        #[Autowire('%env(int:NOTIF_MIN_GAP_MINUTES)%')]
        private readonly int $minGapMinutes,
    ) {
    }

    public function __invoke(PlanDayMessage $message): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        // Idempotency: never plan the same day twice (e.g. a catch-up run after downtime).
        if ($this->notifications->existsForDay($now)) {
            $this->logger->info('PlanDay skipped: notifications already exist for {day}.', ['day' => $now->format('Y-m-d')]);

            return;
        }

        $times = $this->generator->generate($now, $this->windowStart, $this->windowEnd, $this->perDay, $this->minGapMinutes);

        $created = [];
        foreach ($times as $time) {
            $notification = new Notification($time);
            $this->em->persist($notification);
            $created[] = $notification;
        }
        $this->em->flush();

        foreach ($created as $notification) {
            $delayMs = max(0, ($notification->getScheduledAt()->getTimestamp() - $now->getTimestamp()) * 1000);
            $this->bus->dispatch(
                new SendNotificationMessage($notification->getId()),
                [new DelayStamp($delayMs)],
            );
        }

        $this->logger->info('PlanDay scheduled {count} notifications for {day}.', [
            'count' => \count($created),
            'day' => $now->format('Y-m-d'),
        ]);
    }
}
