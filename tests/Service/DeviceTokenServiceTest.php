<?php

namespace App\Tests\Service;

use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use App\Service\DeviceTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;

class DeviceTokenServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserSessionRepository $repository;
    private DeviceTokenService $deviceTokenService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->createMock(UserSessionRepository::class);
        $this->deviceTokenService = new DeviceTokenService($this->entityManager, $this->repository);
    }

    public function testGetDeviceTokenFromCookie(): void
    {
        // Create a request with a cookie
        $request = new Request();
        $request->cookies = new ParameterBag(['device_token' => 'test-device-token']);

        $token = $this->deviceTokenService->getDeviceToken($request);
        $this->assertEquals('test-device-token', $token);
    }

    public function testGetDeviceTokenGeneratesNew(): void
    {
        // Create a request without a cookie
        $request = new Request();

        $token = $this->deviceTokenService->getDeviceToken($request);
        $this->assertNotEmpty($token);
        $this->assertEquals(32, strlen($token));
    }

    public function testAddTokenCookie(): void
    {
        $response = new Response();
        $token = 'test-device-token';

        $this->deviceTokenService->addTokenCookie($response, $token);

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('device_token', $cookies[0]->getName());
        $this->assertEquals($token, $cookies[0]->getValue());
    }

    public function testGetUserSession(): void
    {
        $token = 'test-device-token';
        $request = new Request();
        $request->cookies = new ParameterBag(['device_token' => $token]);

        $session = new UserSession();
        $session->setDeviceToken($token);

        $this->repository->expects($this->once())
            ->method('findOrCreateByToken')
            ->with($token)
            ->willReturn($session);

        $result = $this->deviceTokenService->getUserSession($request);
        $this->assertSame($session, $result);
        $this->assertNotNull($result->getLastVisit());
    }

    public function testUpdateUserSessionData(): void
    {
        $token = 'test-device-token';

        $session = new UserSession();
        $session->setDeviceToken($token);
        $userData = [
            'fullName' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+12345678901'
        ];

        $this->deviceTokenService->updateUserSessionData($session, $userData);

        $this->assertEquals('Test User', $session->getFullName());
        $this->assertEquals('test@example.com', $session->getEmail());
        $this->assertEquals('+12345678901', $session->getPhone());
    }
}