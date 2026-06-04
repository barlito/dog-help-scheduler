<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationType>
 */
class NotificationTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationType::class);
    }

    /**
     * @return NotificationType[]
     */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = true')
            ->orderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
