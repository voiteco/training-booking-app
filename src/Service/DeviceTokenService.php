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
    private const int COOKIE_LIFETIME = 31536000; // 1 year

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserSessionRepository $userSessionRepository,
    ) {
    }

    /**
     * Gets or generates a device token from the request
     * 
     * @param Request $request The HTTP request
     * @return string The device token
     */
    public function getDeviceToken(Request $request): string
    {
        $token = $request->cookies->get(self::COOKIE_NAME);

        if ($token === null) {
            // Try to get from headers
            $token = $request->headers->get('HTTP_X-Device-Token')
                ?? $request->headers->get('X-Device-Token');
        }

        if ($token === null || strlen($token) < 16) {
            // Generate a secure random token
            try {
                $token = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                // Fallback if random_bytes fails
                $token = md5(uniqid((string)mt_rand(), true));
            }
        }

        return $token;
    }

    /**
     * Gets or creates a user session for the given request
     * 
     * @param Request $request The HTTP request
     * @return UserSession The user session
     */
    public function getUserSession(Request $request): UserSession
    {
        try {
            $token = $this->getDeviceToken($request);
            $session = $this->userSessionRepository->findOrCreateByToken($token);

            // Update last visit time
            $session->setLastVisit(new \DateTime());
            $this->entityManager->persist($session);
            $this->entityManager->flush();

            return $session;
        } catch (\Exception $e) {
            // Create a new session if there's an error
            $token = $this->getDeviceToken($request);
            $session = new UserSession();
            $session->setDeviceToken($token);
            $session->setCreatedAt(new \DateTimeImmutable());
            $session->setLastVisit(new \DateTime());
            
            $this->entityManager->persist($session);
            $this->entityManager->flush();
            
            return $session;
        }
    }

    /**
     * Adds a secure device token cookie to the response
     * 
     * @param Response $response The HTTP response
     * @param string $token The device token
     */
    public function addTokenCookie(Response $response, string $token): void
    {
        $cookie = new Cookie(
            self::COOKIE_NAME,
            $token,
            time() + self::COOKIE_LIFETIME,
            '/',
            null,
            $this->isSecureRequest(), // Use HTTPS in production
            true, // HttpOnly
            false,
            Cookie::SAMESITE_LAX
        );

        $response->headers->setCookie($cookie);
    }

    /**
     * Updates user session data
     * 
     * @param UserSession $session The user session to update
     * @param array $userData The user data to update
     */
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

        $session->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }
    
    /**
     * Determines if the current request is secure (HTTPS)
     * 
     * @return bool True if the request is secure
     */
    private function isSecureRequest(): bool
    {
        // In production, this should return true to use secure cookies
        return $_SERVER['APP_ENV'] === 'prod';
    }
}
