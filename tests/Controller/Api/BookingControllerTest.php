<?php

namespace App\Tests\Controller\Api;

use App\Entity\Training;
use App\Entity\Booking;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class BookingControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $trainingId;
    private $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Очищаем тестовую базу данных и добавляем тестовые данные
        $this->setupTestData();
    }

    private function setupTestData(): void
    {
        // Очищаем существующие бронирования
        $this->entityManager->createQuery('DELETE FROM App\Entity\Booking')->execute();
        // Очищаем существующие тренировки
        $this->entityManager->createQuery('DELETE FROM App\Entity\Training')->execute();

        // Создаем тестовую тренировку
        $training = new Training();
        $training->setGoogleSheetId('test-1');
        $training->setDate(new \DateTimeImmutable('2025-03-25'));
        $training->setTime(new \DateTimeImmutable('10:00'));
        $training->setTitle('Тестовая тренировка');
        $training->setSlots(10);
        $training->setSlotsAvailable(10);
        $training->setPrice(1000);
        $training->setCreatedAt(new \DateTimeImmutable());
        $training->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($training);
        $this->entityManager->flush();

        $this->trainingId = $training->getId();

        $this->deviceToken = 'test-user-device';
    }

    /**
     * @throws \JsonException
     */
    public function testCreateBooking(): void
    {
        $bookingData = [
            'training_id' => $this->trainingId,
            'full_name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'phone' => '+79001234567'
        ];

        $this->client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Device-Token' => $this->deviceToken,
            ],
            json_encode($bookingData, JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('Иван Иванов', $response['fullName']);
        $this->assertEquals('active', $response['status']);

        // Проверяем, что количество доступных мест уменьшилось
        $training = $this->entityManager->getRepository(Training::class)->find($this->trainingId);
        $this->assertEquals(9, $training->getSlotsAvailable());
    }

    /**
     * @throws RandomException
     */
    public function testCancelBooking(): void
    {
        // Сначала создаем бронирование
        $training = $this->entityManager->getRepository(Training::class)->find($this->trainingId);

        $booking = new Booking();
        $booking->setTraining($training);
        $booking->setFullName('Петр Петров');
        $booking->setEmail('petr@example.com');
        $booking->setPhone('+79009876543');
        $booking->setStatus('active');
        $booking->setConfirmationToken(bin2hex(random_bytes(16)));
        $booking->setDeviceToken($this->deviceToken);
        $booking->setCreatedAt(new \DateTimeImmutable());
        $booking->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($booking);

        $training->setSlotsAvailable($training->getSlotsAvailable() - 1);
        $this->entityManager->flush();

        // Теперь отменяем бронирование
        $this->client->request(
            'DELETE',
            '/api/bookings/' . $booking->getId(),
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        self::assertResponseIsSuccessful();

        // Проверяем, что бронирование отменено
        $updatedBooking = $this->entityManager->getRepository(Booking::class)->find($booking->getId());
        $this->assertEquals('cancelled', $updatedBooking->getStatus());

        // Проверяем, что количество доступных мест увеличилось
        $updatedTraining = $this->entityManager->getRepository(Training::class)->find($this->trainingId);
        $this->assertEquals(10, $updatedTraining->getSlotsAvailable());
    }

    /**
     * @throws RandomException
     * @throws \JsonException
     */
    public function testGetUserBookings(): void
    {
        // Создаем два бронирования с одним device token
        $training = $this->entityManager->getRepository(Training::class)->find($this->trainingId);

        for ($i = 0; $i < 2; $i++) {
            $booking = new Booking();
            $booking->setTraining($training);
            $booking->setFullName('Тест Тестов ' . $i);
            $booking->setEmail('test' . $i . '@example.com');
            $booking->setPhone('+7900123456' . $i);
            $booking->setStatus('active');
            $booking->setConfirmationToken(bin2hex(random_bytes(16)));
            $booking->setDeviceToken($this->deviceToken);
            $booking->setCreatedAt(new \DateTimeImmutable());
            $booking->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($booking);

            $training->setSlotsAvailable($training->getSlotsAvailable() - 1);
            $this->entityManager->persist($training);
        }

        $this->entityManager->flush();

        // Запрашиваем бронирования пользователя
        $this->client->request(
            'GET',
            '/api/bookings/history',
            [],
            [],
            ['HTTP_X-Device-Token' => $this->deviceToken]
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    /**
     * @throws \JsonException
     */
    public function testBookingWithoutAvailableSlots(): void
    {
        // Заполняем все доступные места
        $training = $this->entityManager->getRepository(Training::class)->find($this->trainingId);
        $training->setSlotsAvailable(0);
        $this->entityManager->flush();

        // Пытаемся создать бронирование
        $bookingData = [
            'training_id' => $this->trainingId,
            'full_name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'phone' => '+79001234567'
        ];

        $this->client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($bookingData, JSON_THROW_ON_ERROR)
        );

        // Ожидаем ошибку, т.к. мест нет
        self::assertResponseStatusCodeSame(400);

        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $response);
    }
}