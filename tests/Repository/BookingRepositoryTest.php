<?php

namespace App\Tests\Repository;

use App\Entity\Booking;
use App\Entity\Training;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookingRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private ?BookingRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(Booking::class);
    }

    public function testFindByDeviceToken(): void
    {
        // Create a test training
        $training = new Training();
        $training->setTitle('Test Training');
        $training->setDate(new \DateTime('tomorrow'));
        $training->setTime(new \DateTime('15:00:00'));
        $training->setSlots(10);
        $training->setSlotsAvailable(5);
        $training->setPrice(20.00);
        $training->setGoogleSheetId('sheet-test');

        $this->entityManager->persist($training);

        // Create test bookings with different device tokens
        $booking1 = new Booking();
        $booking1->setFullName('User 1');
        $booking1->setEmail('user1@example.com');
        $booking1->setPhone('+11111111111');
        $booking1->setConfirmationToken('token1');
        $booking1->setStatus('active');
        $booking1->setDeviceToken('device-token-1');
        $booking1->setTraining($training);

        $booking2 = new Booking();
        $booking2->setFullName('User 2');
        $booking2->setEmail('user2@example.com');
        $booking2->setPhone('+22222222222');
        $booking2->setConfirmationToken('token2');
        $booking2->setStatus('active');
        $booking2->setDeviceToken('device-token-2');
        $booking2->setTraining($training);

        $booking3 = new Booking();
        $booking3->setFullName('User 1 again');
        $booking3->setEmail('user1@example.com');
        $booking3->setPhone('+11111111111');
        $booking3->setConfirmationToken('token3');
        $booking3->setStatus('cancelled');
        $booking3->setDeviceToken('device-token-1');
        $booking3->setTraining($training);

        // Save bookings to the database
        $this->entityManager->persist($booking1);
        $this->entityManager->persist($booking2);
        $this->entityManager->persist($booking3);
        $this->entityManager->flush();

        // Test findHistoryByDeviceToken method
        $device1Bookings = $this->repository->findHistoryByDeviceToken('device-token-1');

        // Assertions
        $this->assertCount(2, $device1Bookings);

        // Test findActiveByDeviceToken method
        $activeDevice1Bookings = $this->repository->findActiveByDeviceToken('device-token-1');

        // Assertions
        $this->assertCount(1, $activeDevice1Bookings);
        $this->assertEquals('User 1', $activeDevice1Bookings[0]->getFullName());

        // Test countActiveBookingsForTraining method
        $activeTrainingBookings = $this->repository->countActiveBookingsForTraining($training->getId());

        // Assertions
        $this->assertEquals(2, $activeTrainingBookings);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Delete test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\Booking b')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Training t')->execute();

        // Close entity manager
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
