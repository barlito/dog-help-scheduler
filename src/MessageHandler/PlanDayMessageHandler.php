<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\PlanDayMessage;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use App\Repository\NotificationTypeRepository;
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
        private readonly NotificationTypeRepository $types,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function __invoke(PlanDayMessage $message): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        /** @var Notification[] $planned */
        $planned = [];
        foreach ($this->types->findEnabled() as $type) {
            // Idempotency: never plan the same type twice for the same day.
            if ($this->notifications->existsForDayAndType($now, $type)) {
                continue;
            }

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
                $planned[] = $notification;
            }
        }
        $this->em->flush();

        foreach ($planned as $notification) {
            $delayMs = max(0, ($notification->getScheduledAt()->getTimestamp() - $now->getTimestamp()) * 1000);
            $this->bus->dispatch(
                new SendNotificationMessage((string) $notification->getId()),
                [new DelayStamp($delayMs)],
            );
        }

        $this->logger->info('PlanDay scheduled {count} notifications for {day}.', [
            'count' => \count($planned),
            'day' => $now->format('Y-m-d'),
        ]);
    }
}
