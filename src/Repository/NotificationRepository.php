<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** True when notifications of this type are already planned for the given day. */
    public function existsForDayAndType(\DateTimeImmutable $day, NotificationType $type): bool
    {
        $start = $day->setTime(0, 0);
        $end = $start->modify('+1 day');

        $count = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.scheduledAt >= :start')
            ->andWhere('n.scheduledAt < :end')
            ->andWhere('n.type = :type')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    /**
     * Number of notifications grouped by status, optionally restricted to a
     * scheduling period ([from, to[). No bounds = all time.
     *
     * @return array<string, int> keyed by NotificationStatus value
     */
    public function countByStatus(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select('n.status AS status, COUNT(n.id) AS total')
            ->groupBy('n.status')
        ;
        if (null !== $from) {
            $qb->andWhere('n.scheduledAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('n.scheduledAt < :to')->setParameter('to', $to);
        }

        $rows = $qb->getQuery()->getResult();

        $counts = [];
        foreach (NotificationStatus::cases() as $case) {
            $counts[$case->value] = 0;
        }
        foreach ($rows as $row) {
            $key = $row['status'] instanceof NotificationStatus ? $row['status']->value : (string) $row['status'];
            $counts[$key] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Latest re-fire time among postponed notifications still waiting to pop again.
     * Used to stagger a burst of postpones instead of letting them all repop at once.
     */
    public function findLatestPendingPostponedUntil(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $value = $this->createQueryBuilder('n')
            ->select('MAX(n.postponedUntil)')
            ->andWhere('n.status = :status')
            ->andWhere('n.postponedUntil > :now')
            ->setParameter('status', NotificationStatus::POSTPONED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return null === $value ? null : new \DateTimeImmutable($value);
    }

    /**
     * @return Notification[]
     */
    public function findForDay(\DateTimeImmutable $day): array
    {
        $start = $day->setTime(0, 0);
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('n')
            ->andWhere('n.scheduledAt >= :start')
            ->andWhere('n.scheduledAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('n.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
