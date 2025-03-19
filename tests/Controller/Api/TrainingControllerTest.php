<?php

namespace App\Tests\Controller\Api;

use App\Entity\Training;
use App\Entity\Booking;
use App\Entity\UserSession;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class TrainingControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $deviceToken = 'test-device-token';

    /**
     * @throws \DateMalformedStringException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Clear test database and add test data
        $this->setupTestData();
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function setupTestData(): void
    {
        // Clear existing data
        $this->entityManager->createQuery('DELETE FROM App\Entity\Booking')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Training')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserSession')->execute();

        // Create test trainings
        $trainings = [
            $this->createTraining('1', '2025-03-25', '10:00', 'Йога для начинающих', 20, 0),
            $this->createTraining('2', '2025-03-25', '12:00', 'Пилатес', 15, 15, 1200), // No slots available
            $this->createTraining('3', '2025-03-26', '11:00', 'Кроссфит', 10, 1500)
        ];

        foreach ($trainings as $training) {
            $this->entityManager->persist($training);
        }

        // Get Пилатес
        $training = $trainings[1];

        // Create user session with device token
        $userSession = new UserSession();
        $userSession->setDeviceToken($this->deviceToken);
        $this->entityManager->persist($userSession);

        // Create a booking for the test user
        $booking = new Booking();
        $booking->setTraining($training);
        $booking->setFullName('Test User');
        $booking->setEmail('test@example.com');
        $booking->setPhone('+79001234567');
        $booking->setStatus('active');
        $booking->setDeviceToken($this->deviceToken);
        $booking->setConfirmationToken('test-token');
        $booking->setCreatedAt(new \DateTimeImmutable());
        $booking->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($booking);

        $training->setSlotsAvailable($training->getSlotsAvailable() - 1);
        $this->entityManager->persist($training);

        $this->entityManager->flush();
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function createTraining(string $googleSheetId, string $date, string $time, string $title, int $slots, ?int $slotsAvailable = 0, float $price = 1000): Training
    {
        $training = new Training();
        $training->setGoogleSheetId($googleSheetId);
        $training->setDate(new \DateTimeImmutable($date));
        $training->setTime(new \DateTimeImmutable($time));
        $training->setTitle($title);
        $training->setSlots($slots);
        $training->setSlotsAvailable($slotsAvailable);
        $training->setPrice($price);
        $training->setCreatedAt(new \DateTimeImmutable());
        $training->setUpdatedAt(new \DateTimeImmutable());

        return $training;
    }

    /**
     * @throws \JsonException
     */
    public function testGetTrainingsList(): void
    {
        $this->client->request(
            'GET',
            '/api/trainings',
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertCount(3, $response);
        $this->assertEquals('Йога для начинающих', $response[0]['title']);

        // Check that userBooked flag is set correctly
        $this->assertTrue($response[1]['userBooked']);
        $this->assertArrayHasKey('userBookingId', $response[1]);
        $this->assertFalse($response[0]['userBooked']);
    }

    public function testGetAvailableTrainings(): void
    {
        $this->client->request(
            'GET',
            '/api/trainings/available',
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);

        // Should only return trainings with available slots
        $this->assertCount(2, $response);
        $titles = array_map(function($item) { return $item['title']; }, $response);
        $this->assertContains('Пилатес', $titles);
        $this->assertContains('Кроссфит', $titles);
        $this->assertNotContains('Йога для начинающих', $titles); // This should be filtered out
    }

    public function testGetUserTrainings(): void
    {
        $this->client->request(
            'GET',
            '/api/trainings/user',
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertCount(1, $response);
        $this->assertEquals('Пилатес', $response[0]['title']);
        $this->assertTrue($response[0]['userBooked']);
        $this->assertArrayHasKey('userBookingId', $response[0]);
    }

    public function testGetTrainingDetails(): void
    {
        // Get ID of first training
        $training = $this->entityManager->getRepository(Training::class)->findOneBy(['title' => 'Пилатес']);
        $id = $training->getId();

        $this->client->request(
            'GET',
            '/api/trainings/' . $id,
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertEquals('Пилатес', $response['title']);
        $this->assertEquals(15, $response['slots']);

        // Fix: should check for 20 not 1000
        $this->assertEquals(14, $response['slotsAvailable']);
        $this->assertTrue($response['userBooked']);
        $this->assertArrayHasKey('userBookingId', $response);
    }

    /**
     * @throws \JsonException
     */
    public function testGetTrainingDetailsNotBooked(): void
    {
        // Get ID of training that user hasn't booked
        $training = $this->entityManager->getRepository(Training::class)->findOneBy(['title' => 'Кроссфит']);
        $id = $training->getId();

        $this->client->request(
            'GET',
            '/api/trainings/' . $id,
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($response['userBooked']);
    }

    public function testNonExistentTraining(): void
    {
        $this->client->request('GET', '/api/trainings/999999');
        self::assertResponseStatusCodeSame(404);
    }
}