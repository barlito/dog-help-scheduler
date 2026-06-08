<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NotificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NotificationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(NotificationRepository::class);

        // Counts are global: start from an empty table so the assertions are
        // exact even though the test database persists between runs.
        $this->em->createQuery('DELETE FROM ' . Notification::class)->execute();
    }

    public function testCountByStatusCanBeRestrictedToASchedulingPeriod(): void
    {
        $type = (new NotificationType())
            ->setKey('repo_' . uniqid())
            ->setLabel('Test')
            ->setTitle('Test')
            ->setMessage('Test message')
        ;
        $this->em->persist($type);

        $inRange = new Notification($type, new \DateTimeImmutable('2026-06-03 10:00'));
        $inRange->markSent();
        $inRange->recordResponse(NotificationStatus::VALIDATED);
        $beforeRange = new Notification($type, new \DateTimeImmutable('2026-05-20 10:00'));
        $afterRange = new Notification($type, new \DateTimeImmutable('2026-06-10 10:00'));
        foreach ([$inRange, $beforeRange, $afterRange] as $notification) {
            $this->em->persist($notification);
        }
        $this->em->flush();

        $week = $this->repository->countByStatus(
            new \DateTimeImmutable('2026-06-01 00:00'),
            new \DateTimeImmutable('2026-06-08 00:00'),
        );
        $this->assertSame(1, $week[NotificationStatus::VALIDATED->value]);
        $this->assertSame(0, $week[NotificationStatus::PLANNED->value], 'Out-of-range notifications must be excluded.');

        $allTime = $this->repository->countByStatus();
        $this->assertSame(1, $allTime[NotificationStatus::VALIDATED->value]);
        $this->assertSame(2, $allTime[NotificationStatus::PLANNED->value]);
    }
}
