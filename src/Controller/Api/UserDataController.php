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
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'User Data')]
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
    #[OA\Post(
        path: '/api/user-data',
        summary: 'Save user profile data'
    )]
    #[OA\Parameter(
        name: 'X-Device-Token',
        description: 'Device token for user identification',
        in: 'header',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['full_name', 'email', 'phone'],
            properties: [
                new OA\Property(property: 'full_name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+1234567890')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'User data saved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'User data saved successfully'),
                new OA\Property(property: 'device_token', type: 'string', example: 'abc123def456')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid input or validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                new OA\Property(
                    property: 'errors',
                    type: 'array',
                    items: new OA\Items(type: 'string', example: 'Email is not valid')
                )
            ]
        )
    )]
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

    #[OA\Get(
        path: '/api/user-data',
        summary: 'Get user profile data'
    )]
    #[OA\Parameter(
        name: 'X-Device-Token',
        description: 'Device token for user identification',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'User data retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'User data retrieved successfully'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'full_name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+1234567890')
                    ],
                    type: 'object',
                    nullable: true
                )
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'No user data found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'No user data found'),
                new OA\Property(property: 'data', type: 'object', example: null)
            ]
        )
    )]
    #[Route('', name: 'api_user_data_get', methods: ['GET'])]
    public function getUserData(Request $request): JsonResponse
    {
        $deviceToken = $this->deviceTokenService->getDeviceToken($request);
        $userSession = $this->userSessionRepository->findOneBy(['deviceToken' => $deviceToken]);

        if ($userSession === null) {
            return $this->json([
                'message' => 'No user data found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
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
