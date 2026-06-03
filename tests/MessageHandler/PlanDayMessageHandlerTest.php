<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Notification;
use App\Message\PlanDayMessage;
use App\Message\SendNotificationMessage;
use App\MessageHandler\PlanDayMessageHandler;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PlanDayMessageHandlerTest extends KernelTestCase
{
    public function testPlansAndDispatchesTheDailyNotifications(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        // Clean slate so existsForDay() doesn't short-circuit the planning.
        $em->createQuery('DELETE FROM '.Notification::class.' n')->execute();

        $handler = $container->get(PlanDayMessageHandler::class);
        $handler(new PlanDayMessage());

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $planned = $container->get(NotificationRepository::class)->findForDay($today);

        // Matches NOTIF_PER_DAY in .env.test (inherited from .env = 4).
        self::assertCount(4, $planned);

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        self::assertCount(4, $transport->getSent());

        foreach ($transport->getSent() as $envelope) {
            self::assertInstanceOf(SendNotificationMessage::class, $envelope->getMessage());
        }
    }

    public function testIsIdempotentForTheSameDay(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM '.Notification::class.' n')->execute();

        $handler = $container->get(PlanDayMessageHandler::class);
        $handler(new PlanDayMessage());
        $handler(new PlanDayMessage()); // second run must be a no-op

        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $planned = $container->get(NotificationRepository::class)->findForDay($today);

        self::assertCount(4, $planned, 'Planning twice must not create duplicates.');
    }
}
