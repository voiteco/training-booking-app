<?php

namespace App\Controller\Api;

use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use App\Service\DeviceTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user-data')]
class UserDataController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserSessionRepository $userSessionRepository,
        private DeviceTokenService $deviceTokenService,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('', name: 'api_user_data_save', methods: ['POST'])]
    public function saveUserData(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['full_name'], $data['email'], $data['phone']) || !$data) {
            return $this->json(['message' => 'Invalid data provided'], Response::HTTP_BAD_REQUEST);
        }

        $deviceToken = $this->deviceTokenService->getDeviceToken($request);

        // Ищем существующую сессию или создаем новую
        $userSession = $this->userSessionRepository->findOneBy(['deviceToken' => $deviceToken]);

        if ($userSession === null) {
            $userSession = new UserSession();
            $userSession->setDeviceToken($deviceToken);
            $userSession->setCreatedAt(new \DateTimeImmutable());
        }

        // Обновляем данные пользователя
        $userSession->setFullName($data['full_name']);
        $userSession->setEmail($data['email']);
        $userSession->setPhone($data['phone']);
        $userSession->setLastVisit(new \DateTimeImmutable());
        $userSession->setUpdatedAt(new \DateTimeImmutable());

        // Валидация
        $errors = $this->validator->validate($userSession);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Сохраняем данные
        $this->entityManager->persist($userSession);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'User data saved successfully',
            'device_token' => $deviceToken,
        ]);
    }

    #[Route('', name: 'api_user_data_get', methods: ['GET'])]
    public function getUserData(Request $request): JsonResponse
    {
        $deviceToken = $this->deviceTokenService->getDeviceToken($request);
        $userSession = $this->userSessionRepository->findOneBy(['deviceToken' => $deviceToken]);

        if ($userSession === null) {
            return $this->json([
                'message' => 'No user data found',
                'data' => null,
            ]);
        }

        return $this->json([
            'message' => 'User data retrieved successfully',
            'data' => [
                'full_name' => $userSession->getFullName(),
                'email' => $userSession->getEmail(),
                'phone' => $userSession->getPhone(),
            ],
        ]);
    }
}
