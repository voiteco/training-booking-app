<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Find or create a session by device token
     */
    public function findOrCreateByToken(string $deviceToken): UserSession
    {
        $session = $this->findOneBy(['deviceToken' => $deviceToken]);

        if (!$session) {
            $session = new UserSession();
            $session->setDeviceToken($deviceToken);
        } else {
            $session->setLastVisit(new \DateTime());
        }

        return $session;
    }

    /**
     * Clean up old sessions (older than 90 days)
     */
    public function cleanUpOldSessions(): int
    {
        $date = new \DateTime('-90 days');

        return $this->createQueryBuilder('u')
            ->delete()
            ->andWhere('u.lastVisit < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}