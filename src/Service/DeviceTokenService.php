<?php

namespace App\Service;

use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenService
{
    private const string COOKIE_NAME = 'device_token';
    private const int COOKIE_LIFETIME = 31536000; // 1 год

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserSessionRepository $userSessionRepository,
    ) {
    }

    public function getDeviceToken(Request $request): string
    {
        $token = $request->cookies->get(self::COOKIE_NAME);

        if ($token === null) {
            $token = $request->headers->get('HTTP_X-Device-Token')
                ?? $request->headers->get('X-Device-Token');
        }

        if ($token === null) {
            $token = bin2hex(random_bytes(16));
        }

        return $token;
    }

    public function getUserSession(Request $request): UserSession
    {
        $token = $this->getDeviceToken($request);
        $session = $this->userSessionRepository->findOrCreateByToken($token);

        // Обновляем время последнего посещения
        $session->setLastVisit(new \DateTime());
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function addTokenCookie(Response $response, string $token): void
    {
        $cookie = new Cookie(
            self::COOKIE_NAME,
            $token,
            time() + self::COOKIE_LIFETIME,
            '/',
            null,
            false,
            true,
            false,
            Cookie::SAMESITE_LAX
        );

        $response->headers->setCookie($cookie);
    }

    public function updateUserSessionData(UserSession $session, array $userData): void
    {
        if (!empty($userData['fullName'])) {
            $session->setFullName($userData['fullName']);
        }

        if (!empty($userData['email'])) {
            $session->setEmail($userData['email']);
        }

        if (!empty($userData['phone'])) {
            $session->setPhone($userData['phone']);
        }

        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }
}
