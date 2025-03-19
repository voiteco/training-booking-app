<?php

namespace App\Tests\Repository;

use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserSessionRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private ?UserSessionRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(UserSession::class);
    }

    public function testFindByDeviceToken(): void
    {
        // Create test user sessions
        $session1 = new UserSession();
        $session1->setDeviceToken('device-token-a');
        $session1->setFullName('Alice Smith');
        $session1->setEmail('alice@example.com');
        $session1->setPhone('+11111111111');
        $session1->setLastVisit(new \DateTime());

        $session2 = new UserSession();
        $session2->setDeviceToken('device-token-b');
        $session2->setFullName('Bob Jones');
        $session2->setEmail('bob@example.com');
        $session2->setPhone('+22222222222');
        $session2->setLastVisit(new \DateTime());

        // Save sessions to the database
        $this->entityManager->persist($session1);
        $this->entityManager->persist($session2);
        $this->entityManager->flush();

        // Test findByDeviceToken method
        $foundSession = $this->repository->findOneByDeviceToken('device-token-a');

        // Assertions
        $this->assertNotNull($foundSession);
        $this->assertEquals('Alice Smith', $foundSession->getFullName());
        $this->assertEquals('alice@example.com', $foundSession->getEmail());

        // Test not existing device token
        $nonExistingSession = $this->repository->findOneByDeviceToken('non-existing-token');

        // Assertions
        $this->assertNull($nonExistingSession);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Delete test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserSession u')->execute();

        // Close entity manager
        $this->entityManager->close();
        $this->entityManager = null;
    }
}