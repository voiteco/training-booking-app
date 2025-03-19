<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Find active bookings by device token
     */
    public function findActiveByDeviceToken(?string $deviceToken): array
    {
        if ($deviceToken === null) {
            return [];
        }

        return $this->createQueryBuilder('b')
            ->where('b.deviceToken = :deviceToken')
            ->andWhere('b.status = :status')
            ->setParameter('deviceToken', $deviceToken)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find booking history by device token
     */
    public function findHistoryByDeviceToken(string $deviceToken): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.deviceToken = :deviceToken')
            ->setParameter('deviceToken', $deviceToken)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count bookings for a training
     */
    public function countActiveBookingsForTraining(int $trainingId): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.training = :trainingId')
            ->andWhere('b.status = :status')
            ->setParameter('trainingId', $trainingId)
            ->setParameter('status', Booking::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
