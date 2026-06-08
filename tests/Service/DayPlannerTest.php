<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use App\Service\DayPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DayPlannerTest extends KernelTestCase
{
    private function reset(EntityManagerInterface $em): void
    {
        // Notifications first because of the FK to notification_type.
        $em->createQuery('DELETE FROM ' . Notification::class . ' n')->execute();
        $em->createQuery('DELETE FROM ' . NotificationType::class . ' t')->execute();
    }

    private function makeType(bool $enabled, int $perDay = 4): NotificationType
    {
        return (new NotificationType())
            ->setKey('planner_' . uniqid())
            ->setLabel('Test')
            ->setTitle('Test')
            ->setMessage('Test message')
            ->setWindowStart('08:00')
            ->setWindowEnd('20:00')
            ->setPerDay($perDay)
            ->setMinGapMinutes(60)
            ->setEnabled($enabled)
        ;
    }

    public function testPlanTypePlansAndDispatchesEvenWhenDisabled(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->reset($em);

        // Disabled on purpose: the manual "plan now" button must work regardless.
        $type = $this->makeType(enabled: false, perDay: 4);
        $em->persist($type);
        $em->flush();

        $count = $container->get(DayPlanner::class)->planType($type);

        $this->assertSame(4, $count);

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->assertCount(4, $container->get(NotificationRepository::class)->findForDay($today));

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        $this->assertCount(4, $transport->getSent());
        foreach ($transport->getSent() as $envelope) {
            $this->assertInstanceOf(SendNotificationMessage::class, $envelope->getMessage());
        }
    }

    public function testPlanTypeIsIdempotentForTheSameDay(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->reset($em);

        $type = $this->makeType(enabled: true, perDay: 4);
        $em->persist($type);
        $em->flush();

        $planner = $container->get(DayPlanner::class);
        $this->assertSame(4, $planner->planType($type));
        $this->assertSame(0, $planner->planType($type), 'A second run for the same day must be a no-op.');

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->assertCount(4, $container->get(NotificationRepository::class)->findForDay($today));
    }

    public function testPlanEnabledSkipsDisabledTypes(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->reset($em);

        $em->persist($this->makeType(enabled: true, perDay: 3));
        $em->persist($this->makeType(enabled: false, perDay: 5));
        $em->flush();

        $count = $container->get(DayPlanner::class)->planEnabled();

        $this->assertSame(3, $count, 'Only the enabled type should be planned.');
    }
}
