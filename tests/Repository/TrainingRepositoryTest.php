<?php

namespace App\Tests\Repository;

use App\Entity\Training;
use App\Repository\TrainingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TrainingRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private ?TrainingRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(Training::class);
    }

    public function testFindAvailable(): void
    {
        // Create test trainings
        $yesterday = new \DateTime('yesterday');
        $tomorrow = new \DateTime('tomorrow');
        $nextWeek = new \DateTime('+7 days');

        $training1 = new Training();
        $training1->setTitle('Past Training');
        $training1->setDate($yesterday);
        $training1->setTime(new \DateTime('10:00:00'));
        $training1->setSlots(10);
        $training1->setSlotsAvailable(5);
        $training1->setPrice(15.00);
        $training1->setGoogleSheetId('sheet1');

        $training2 = new Training();
        $training2->setTitle('Future Training 1');
        $training2->setDate($tomorrow);
        $training2->setTime(new \DateTime('11:00:00'));
        $training2->setSlots(10);
        $training2->setSlotsAvailable(0); // No available slots
        $training2->setPrice(20.00);
        $training2->setGoogleSheetId('sheet2');

        $training3 = new Training();
        $training3->setTitle('Future Training 2');
        $training3->setDate($nextWeek);
        $training3->setTime(new \DateTime('12:00:00'));
        $training3->setSlots(10);
        $training3->setSlotsAvailable(5); // Available slots
        $training3->setPrice(25.00);
        $training3->setGoogleSheetId('sheet3');

        // Save trainings to the database
        $this->entityManager->persist($training1);
        $this->entityManager->persist($training2);
        $this->entityManager->persist($training3);
        $this->entityManager->flush();

        // Test findAvailable method
        $availableTrainings = $this->repository->findAvailable();

        // Assertions
        $this->assertCount(1, $availableTrainings);
        $this->assertEquals('Future Training 2', $availableTrainings[0]->getTitle());

        // Test findUpcoming method
        $upcomingTrainings = $this->repository->findUpcoming();

        // Assertions
        $this->assertCount(2, $upcomingTrainings);
        $this->assertEquals('Future Training 1', $upcomingTrainings[0]->getTitle());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Delete test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\Training t')->execute();

        // Close entity manager
        $this->entityManager->close();
        $this->entityManager = null;
    }
}