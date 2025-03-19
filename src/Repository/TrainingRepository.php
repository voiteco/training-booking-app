<?php

namespace App\Repository;

use App\Entity\Training;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Training>
 */
class TrainingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Training::class);
    }

    /**
     * @return Training[] Returns an array of upcoming Training objects
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.date >= :today')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('t.date', 'ASC')
            ->addOrderBy('t.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Training[] Returns an array of available Training objects (with seats)
     */
    public function findAvailable(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.date >= :today')
            ->andWhere('t.slotsAvailable > 0') // Make sure this exists and works
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('t.date', 'ASC')
            ->addOrderBy('t.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find trainings by Google Sheet IDs
     */
    public function findByGoogleSheetIds(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.googleSheetId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}