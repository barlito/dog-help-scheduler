<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Message\PlanDayMessage;
use App\Message\SendNotificationMessage;
use App\MessageHandler\PlanDayMessageHandler;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class PlanDayMessageHandlerTest extends KernelTestCase
{
    private function reset(EntityManagerInterface $em): void
    {
        // Clean slate (delete notifications first because of the FK to notification_type).
        $em->createQuery('DELETE FROM ' . Notification::class . ' n')->execute();
        $em->createQuery('DELETE FROM ' . NotificationType::class . ' t')->execute();
    }

    private function makeType(bool $enabled, int $perDay = 4): NotificationType
    {
        return (new NotificationType())
            ->setKey('plan_' . uniqid())
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

    public function testPlansAndDispatchesForEnabledTypes(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->reset($em);

        $em->persist($this->makeType(enabled: true, perDay: 4));
        $em->flush();

        $container->get(PlanDayMessageHandler::class)(new PlanDayMessage());

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $planned = $container->get(NotificationRepository::class)->findForDay($today);
        $this->assertCount(4, $planned);

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        $this->assertCount(4, $transport->getSent());
        foreach ($transport->getSent() as $envelope) {
            $this->assertInstanceOf(SendNotificationMessage::class, $envelope->getMessage());
        }
    }

    public function testDisabledTypesAreSkipped(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->reset($em);

        $em->persist($this->makeType(enabled: false, perDay: 4));
        $em->flush();

        $container->get(PlanDayMessageHandler::class)(new PlanDayMessage());

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->assertCount(0, $container->get(NotificationRepository::class)->findForDay($today));
    }

    public function testIsIdempotentForTheSameDay(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->reset($em);

        $em->persist($this->makeType(enabled: true, perDay: 4));
        $em->flush();

        $handler = $container->get(PlanDayMessageHandler::class);
        $handler(new PlanDayMessage());
        $handler(new PlanDayMessage()); // second run must be a no-op

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->assertCount(4, $container->get(NotificationRepository::class)->findForDay($today), 'Planning twice must not duplicate.');
    }
}
