<?php

namespace App\Controller\Api;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\TrainingRepository;
use App\Service\DeviceTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Bookings')]
#[Route('/api/bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TrainingRepository $trainingRepository,
        private BookingRepository $bookingRepository,
        private DeviceTokenService $deviceTokenService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[OA\Post(
        path: '/api/bookings',
        summary: 'Create a new booking for a training'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['training_id', 'full_name', 'email', 'phone'],
            properties: [
                new OA\Property(property: 'training_id', type: 'integer', example: 1),
                new OA\Property(property: 'full_name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Booking created successfully',
        content: new OA\JsonContent(ref: new Model(type: Booking::class, groups: ['booking:read']))
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid input or no available slots',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'No available slots'),
                new OA\Property(property: 'errors', type: 'object'),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Training not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Training not found'),
            ]
        )
    )]
    #[Route('', name: 'api_bookings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Validate the agreement separately since it's not part of the entity
        // if (!isset($data['agreement']) || $data['agreement'] !== true) {
        //    return $this->json(['errors' => ['agreement' => 'You must agree to the terms']], Response::HTTP_BAD_REQUEST);
        // }

        $training = $this->trainingRepository->find($data['training_id'] ?? 0);

        if (!$training) {
            return $this->json(['error' => 'Training not found'], Response::HTTP_NOT_FOUND);
        }

        if ($training->getSlotsAvailable() <= 0) {
            return $this->json(['error' => 'No available slots'], Response::HTTP_BAD_REQUEST);
        }

        $deviceToken = $this->deviceTokenService->getDeviceToken($request);
        $session = $this->deviceTokenService->getUserSession($request);

        // Check for existing booking
        $existingBooking = $this->bookingRepository->findOneBy([
            'training' => $training->getId(),
            'deviceToken' => $deviceToken,
            'status' => Booking::STATUS_ACTIVE,
        ]);

        if ($existingBooking) {
            return $this->json(['error' => 'You already have a booking for this training'], Response::HTTP_BAD_REQUEST);
        }

        // Create booking
        $booking = new Booking();
        $booking->setTraining($training);
        $booking->setFullName($data['full_name'] ?? '');
        $booking->setEmail($data['email'] ?? '');
        $booking->setPhone($data['phone'] ?? '');
        $booking->setDeviceToken($deviceToken);

        // Validate the entity
        $violations = $this->validator->validate($booking);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $errors[$propertyPath] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // Save user data for future form fills
        $this->deviceTokenService->updateUserSessionData($session, [
            'fullName' => $booking->getFullName(),
            'email' => $booking->getEmail(),
            'phone' => $booking->getPhone(),
        ]);

        // Update available slots
        $training->setSlotsAvailable($training->getSlotsAvailable() - 1);

        $this->entityManager->persist($booking);
        $this->entityManager->persist($training);
        $this->entityManager->flush();

        $responseData = json_decode($this->serializer->serialize($booking, 'json', [
            'groups' => ['booking:read'],
        ]), true, 512, JSON_THROW_ON_ERROR);

        $response = new JsonResponse($responseData, Response::HTTP_CREATED);
        $this->deviceTokenService->addTokenCookie($response, $deviceToken);

        return $response;
    }

    #[OA\Delete(
        path: '/api/bookings/{id}',
        summary: 'Cancel an existing booking'
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'Booking ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Booking cancelled successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied - not your booking',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Access denied'),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Booking not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Booking not found'),
            ]
        )
    )]
    #[Route('/{id}', name: 'api_bookings_cancel', methods: ['DELETE'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->json(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
        }

        $deviceToken = $this->deviceTokenService->getDeviceToken($request);

        // Проверяем, принадлежит ли бронирование текущему пользователю
        if ($booking->getDeviceToken() !== $deviceToken) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $training = $booking->getTraining();

        $booking->setStatus(Booking::STATUS_CANCELLED);

        // Увеличиваем количество доступных мест
        $training->setSlotsAvailable($training->getSlotsAvailable() + 1);

        $this->entityManager->persist($booking);
        $this->entityManager->persist($training);
        $this->entityManager->flush();

        $response = new JsonResponse(['success' => true]);
        $this->deviceTokenService->addTokenCookie($response, $deviceToken);

        return $response;
    }

    /**
     * @throws \JsonException
     */
    #[OA\Get(
        path: '/api/bookings/history',
        summary: 'Get user\'s booking history'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of user\'s bookings',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                    new OA\Property(property: 'fullName', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1234567890'),
                    new OA\Property(
                        property: 'training',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
                            new OA\Property(property: 'dateFormatted', type: 'string', example: '01.01.2023'),
                            new OA\Property(property: 'timeFormatted', type: 'string', example: '18:00'),
                        ],
                        type: 'object'
                    ),
                ]
            )
        )
    )]
    #[Route('/history', name: 'api_bookings_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $deviceToken = $this->deviceTokenService->getDeviceToken($request);
        $bookings = $this->bookingRepository->findHistoryByDeviceToken($deviceToken);

        $result = [];
        foreach ($bookings as $booking) {
            $bookingData = json_decode($this->serializer->serialize($booking, 'json', [
                'groups' => ['booking:read'],
            ]), true, 512, JSON_THROW_ON_ERROR);

            // Добавляем информацию о тренировке
            $training = $booking->getTraining();
            $bookingData['training'] = [
                'id' => $training->getId(),
                'title' => $training->getTitle(),
                'dateFormatted' => $training->getDate()->format('d.m.Y'),
                'timeFormatted' => $training->getTime()->format('H:i'),
            ];

            $result[] = $bookingData;
        }

        $response = new JsonResponse($result);
        $this->deviceTokenService->addTokenCookie($response, $deviceToken);

        return $response;
    }
}
