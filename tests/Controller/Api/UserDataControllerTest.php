<?php

namespace App\Tests\Controller\Api;

use App\Entity\UserSession;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class UserDataControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Очищаем тестовую базу данных
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserSession')->execute();
    }

    /**
     * @throws \JsonException
     */
    public function testSaveUserData(): void
    {
        $userData = [
            'full_name' => 'Мария Сидорова',
            'email' => 'maria@example.com',
            'phone' => '+79001112233'
        ];

        $this->client->request(
            'POST',
            '/api/user-data',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Device-Token' => 'test-user-device'
            ],
            json_encode($userData, JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);

        // Проверяем, что данные пользователя сохранены
        $userSession = $this->entityManager->getRepository(UserSession::class)
            ->findOneBy(['deviceToken' => 'test-user-device']);

        $this->assertNotNull($userSession);
        $this->assertEquals('Мария Сидорова', $userSession->getFullName());
        $this->assertEquals('maria@example.com', $userSession->getEmail());
        $this->assertEquals('+79001112233', $userSession->getPhone());
    }

    public function testUpdateUserData(): void
    {
        // Сначала создаем сессию пользователя
        $userSession = new UserSession();
        $userSession->setDeviceToken('existing-user-device');
        $userSession->setFullName('Старое Имя');
        $userSession->setEmail('old@example.com');
        $userSession->setPhone('+79009990000');
        $userSession->setLastVisit(new \DateTimeImmutable());
        $userSession->setCreatedAt(new \DateTimeImmutable());
        $userSession->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        // Теперь обновляем данные
        $updatedData = [
            'full_name' => 'Новое Имя',
            'email' => 'new@example.com',
            'phone' => '+79001113344'
        ];

        $this->client->request(
            'POST',
            '/api/user-data',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Device-Token' => 'existing-user-device'
            ],
            json_encode($updatedData)
        );

        self::assertResponseIsSuccessful();

        // Проверяем, что данные обновлены
        $this->entityManager->clear();
        $updatedUserSession = $this->entityManager->getRepository(UserSession::class)
            ->findOneBy(['deviceToken' => 'existing-user-device']);

        $this->assertEquals('Новое Имя', $updatedUserSession->getFullName());
        $this->assertEquals('new@example.com', $updatedUserSession->getEmail());
        $this->assertEquals('+79001113344', $updatedUserSession->getPhone());
    }

    public function testGetUserData(): void
    {
        // Создаем сессию пользователя
        $userSession = new UserSession();
        $userSession->setDeviceToken('get-user-data-device');
        $userSession->setFullName('Тест Тестов');
        $userSession->setEmail('test@example.com');
        $userSession->setPhone('+79001234567');
        $userSession->setLastVisit(new \DateTimeImmutable());
        $userSession->setCreatedAt(new \DateTimeImmutable());
        $userSession->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        // Запрашиваем данные пользователя
        $this->client->request(
            'GET',
            '/api/user-data',
            [],
            [],
            ['HTTP_X-Device-Token' => 'get-user-data-device']
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertEquals('Тест Тестов', $response['data']['full_name']);
        $this->assertEquals('test@example.com', $response['data']['email']);
        $this->assertEquals('+79001234567', $response['data']['phone']);
    }

    public function testGetUserDataForNonExistingUser(): void
    {
        // Запрашиваем данные несуществующего пользователя
        $this->client->request(
            'GET',
            '/api/user-data',
            [],
            [],
            ['HTTP_X-Device-Token' => 'non-existing-device']
        );

        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertEmpty($response['data']);
    }

    /**
     * @throws \JsonException
     */
    public function testInvalidUserData(): void
    {
        // Отправляем данные с неверным форматом email
        $invalidData = [
            'full_name' => 'Тест Тестов',
            'email' => 'invalid-email',
            'phone' => '+79001234567'
        ];

        $this->client->request(
            'POST',
            '/api/user-data',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Device-Token' => 'test-device'
            ],
            json_encode($invalidData, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);

        $response = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('errors', $response);
    }
}