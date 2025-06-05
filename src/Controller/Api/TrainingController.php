<?php

namespace App\Controller\Api;

use App\Entity\Training;
use App\Repository\BookingRepository;
use App\Repository\TrainingRepository;
use App\Service\DeviceTokenService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Tag(name: 'Trainings')]
#[Route('/api/trainings')]
class TrainingController extends AbstractController
{
    public function __construct(
        private TrainingRepository $trainingRepository,
        private BookingRepository $bookingRepository,
        private DeviceTokenService $deviceTokenService,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * Gets all upcoming trainings
     * 
     * @throws \JsonException
     */
    #[OA\Get(
        path: '/api/trainings',
        summary: 'Get all upcoming trainings'
    )]
    #[OA\Parameter(
        name: 'X-Device-Token',
        description: 'Device token for user identification',
        in: 'header',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of upcoming trainings',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
                    new OA\Property(property: 'description', type: 'string', example: 'Beginner-friendly yoga session'),
                    new OA\Property(property: 'dateFormatted', type: 'string', example: '01.01.2023'),
                    new OA\Property(property: 'timeFormatted', type: 'string', example: '18:00'),
                    new OA\Property(property: 'totalSlots', type: 'integer', example: 20),
                    new OA\Property(property: 'bookedSlots', type: 'integer', example: 15),
                    new OA\Property(property: 'userBooked', type: 'boolean', example: true),
                    new OA\Property(property: 'userBookingId', type: 'integer', example: 5, nullable: true),
                ]
            )
        )
    )]
    #[Route('', name: 'api_trainings_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $trainings = $this->trainingRepository->findUpcoming();

            $deviceToken = $this->deviceTokenService->getDeviceToken($request);
            $userBookings = $this->bookingRepository->findActiveByDeviceToken($deviceToken);

            // Map bookings by training ID for easy lookup
            $bookingsByTrainingId = [];
            foreach ($userBookings as $booking) {
                $training = $booking->getTraining();
                if ($training) {
                    $bookingsByTrainingId[$training->getId()] = $booking;
                }
            }

            $result = array_map(function ($training) use ($bookingsByTrainingId) {
                try {
                    $data = $this->serializeTraining($training);
                    $trainingId = $training->getId();

                    $data['userBooked'] = isset($bookingsByTrainingId[$trainingId]);
                    if ($data['userBooked']) {
                        $data['userBookingId'] = $bookingsByTrainingId[$trainingId]->getId();
                    }

                    return $data;
                } catch (\Exception $e) {
                    // Skip this training if there's an error
                    return null;
                }
            }, $trainings);

            // Filter out any null entries from errors
            $result = array_filter($result);

            $response = new JsonResponse($result);
            $this->deviceTokenService->addTokenCookie($response, $deviceToken);

            return $response;
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while fetching trainings'], 
                \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Gets trainings with available slots
     * 
     * @throws \JsonException
     */
    #[OA\Get(
        path: '/api/trainings/available',
        summary: 'Get trainings with available slots'
    )]
    #[OA\Parameter(
        name: 'X-Device-Token',
        description: 'Device token for user identification',
        in: 'header',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of trainings with available slots',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
                    new OA\Property(property: 'description', type: 'string', example: 'Beginner-friendly yoga session'),
                    new OA\Property(property: 'dateFormatted', type: 'string', example: '01.01.2023'),
                    new OA\Property(property: 'timeFormatted', type: 'string', example: '18:00'),
                    new OA\Property(property: 'totalSlots', type: 'integer', example: 20),
                    new OA\Property(property: 'bookedSlots', type: 'integer', example: 15),
                ]
            )
        )
    )]
    #[Route('/available', name: 'api_trainings_available', methods: ['GET'])]
    public function available(Request $request): JsonResponse
    {
        try {
            $trainings = $this->trainingRepository->findAvailable();
            $deviceToken = $this->deviceTokenService->getDeviceToken($request);

            $result = [];
            foreach ($trainings as $training) {
                try {
                    $result[] = $this->serializeTraining($training);
                } catch (\Exception $e) {
                    // Skip this training if there's an error
                    continue;
                }
            }

            $response = new JsonResponse($result);
            $this->deviceTokenService->addTokenCookie($response, $deviceToken);

            return $response;
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while fetching available trainings'], 
                \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Gets user's booked trainings
     * 
     * @throws \JsonException
     */
    #[OA\Get(
        path: '/api/trainings/user',
        summary: 'Get user\'s booked trainings'
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
        description: 'List of user\'s booked trainings',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
                    new OA\Property(property: 'description', type: 'string', example: 'Beginner-friendly yoga session'),
                    new OA\Property(property: 'dateFormatted', type: 'string', example: '01.01.2023'),
                    new OA\Property(property: 'timeFormatted', type: 'string', example: '18:00'),
                    new OA\Property(property: 'totalSlots', type: 'integer', example: 20),
                    new OA\Property(property: 'bookedSlots', type: 'integer', example: 15),
                    new OA\Property(property: 'userBooked', type: 'boolean', example: true),
                    new OA\Property(property: 'userBookingId', type: 'integer', example: 5),
                ]
            )
        )
    )]
    #[Route('/user', name: 'api_trainings_user', methods: ['GET'])]
    public function userTrainings(Request $request): JsonResponse
    {
        try {
            $deviceToken = $this->deviceTokenService->getDeviceToken($request);
            $userBookings = $this->bookingRepository->findActiveByDeviceToken($deviceToken);

            $result = [];
            foreach ($userBookings as $booking) {
                try {
                    $training = $booking->getTraining();
                    if (!$training) {
                        continue;
                    }
                    
                    $trainingData = $this->serializeTraining($training);
                    $trainingData['userBooked'] = true;
                    $trainingData['userBookingId'] = $booking->getId();

                    $result[] = $trainingData;
                } catch (\Exception $e) {
                    // Skip this booking if there's an error
                    continue;
                }
            }

            $response = new JsonResponse($result);
            $this->deviceTokenService->addTokenCookie($response, $deviceToken);

            return $response;
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while fetching user trainings'], 
                \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Gets specific training by ID
     * 
     * @throws \JsonException
     */
    #[OA\Get(
        path: '/api/trainings/{id}',
        summary: 'Get specific training by ID'
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'Training ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'X-Device-Token',
        description: 'Device token for user identification',
        in: 'header',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Training details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
                new OA\Property(property: 'description', type: 'string', example: 'Beginner-friendly yoga session'),
                new OA\Property(property: 'dateFormatted', type: 'string', example: '01.01.2023'),
                new OA\Property(property: 'timeFormatted', type: 'string', example: '18:00'),
                new OA\Property(property: 'totalSlots', type: 'integer', example: 20),
                new OA\Property(property: 'bookedSlots', type: 'integer', example: 15),
                new OA\Property(property: 'userBooked', type: 'boolean', example: true),
                new OA\Property(property: 'userBookingId', type: 'integer', example: 5, nullable: true),
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
    #[Route('/{id}', name: 'api_trainings_show', methods: ['GET'])]
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $training = $this->trainingRepository->find($id);

            if (!$training) {
                return $this->json(['error' => 'Training not found'], Response::HTTP_NOT_FOUND);
            }

            $deviceToken = $this->deviceTokenService->getDeviceToken($request);
            $userBookings = $this->bookingRepository->findActiveByDeviceToken($deviceToken);

            $trainingData = $this->serializeTraining($training);
            $trainingData['userBooked'] = false;

            foreach ($userBookings as $booking) {
                $bookingTraining = $booking->getTraining();
                if ($bookingTraining && $bookingTraining->getId() === $training->getId()) {
                    $trainingData['userBooked'] = true;
                    $trainingData['userBookingId'] = $booking->getId();
                    break;
                }
            }

            $response = new JsonResponse($trainingData);
            $this->deviceTokenService->addTokenCookie($response, $deviceToken);

            return $response;
        } catch (\JsonException $e) {
            return $this->json(['error' => 'Error processing training data'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json(['error' => 'An unexpected error occurred'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serializes a training entity to an array with formatted date and time
     * 
     * @param Training $training The training entity to serialize
     * @return array The serialized training data
     * @throws \JsonException
     */
    private function serializeTraining(Training $training): array
    {
        try {
            $data = json_decode($this->serializer->serialize($training, 'json', [
                'groups' => ['training:read'],
            ]), true, 512, JSON_THROW_ON_ERROR);

            // Format date and time with null checks
            $data['dateFormatted'] = $training->getDate() ? $training->getDate()->format('d.m.Y') : null;
            $data['timeFormatted'] = $training->getTime() ? $training->getTime()->format('H:i') : null;

            return $data;
        } catch (\JsonException $e) {
            // Log the error
            throw $e;
        }
    }
}
