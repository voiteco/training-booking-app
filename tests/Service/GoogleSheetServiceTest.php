<?php

namespace App\Tests\Service;

use App\Entity\Training;
use App\Repository\TrainingRepository;
use App\Service\GoogleSheetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class GoogleSheetServiceTest extends TestCase
{
    private $entityManager;
    private $trainingRepository;
    private $cache;
    private $logger;
    private $googleSheetService;
    // Declare the property properly
    private \ReflectionMethod $updateTrainingsMethod;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->trainingRepository = $this->createMock(TrainingRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->googleSheetService = new GoogleSheetService(
            $this->entityManager,
            $this->trainingRepository,
            $this->cache,
            $this->logger,
            'test-sheet-id',
            'test-api-key'
        );

        // Access private method using Reflection
        $reflectionClass = new \ReflectionClass($this->googleSheetService);
        $this->updateTrainingsMethod = $reflectionClass->getMethod('updateTrainingsFromData');
    }

    public function testGetCachedTrainings(): void
    {
        $expectedTrainings = [
            $this->createTraining('1', 'Йога', '2025-03-25'),
            $this->createTraining('2', 'Пилатес', '2025-03-26')
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use ($expectedTrainings) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(3600);
                return $callback($item);
            });

        $this->trainingRepository->expects($this->once())
            ->method('findUpcoming')
            ->willReturn($expectedTrainings);

        $result = $this->googleSheetService->getCachedTrainings();
        $this->assertSame($expectedTrainings, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testUpdateTrainingsFromData(): void
    {
        $data = [
            [
                'id' => '1',
                'date' => '2025-03-25',
                'time' => '10:00',
                'title' => 'Йога',
                'slots' => 20,
                'price' => 1000
            ],
            [
                'id' => '2',
                'date' => '2025-03-26',
                'time' => '11:00',
                'title' => 'Пилатес',
                'slots' => 15,
                'price' => 1200
            ]
        ];

        $existingTraining = $this->createTraining('1', 'Йога (старое название)', '2025-03-25');

        $this->trainingRepository->expects($this->once())
            ->method('findByGoogleSheetIds')
            ->with(['1', '2'])
            ->willReturn([$existingTraining]);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Call private method through reflection
        $this->updateTrainingsMethod->invoke($this->googleSheetService, $data);

        // Verify data was updated
        $this->assertEquals('Йога', $existingTraining->getTitle());
        $this->assertEquals(20, $existingTraining->getSlots());
        $this->assertEquals(1000, $existingTraining->getPrice());
    }

    private function createTraining($googleSheetId, $title, $date): Training
    {
        $training = new Training();
        $training->setGoogleSheetId($googleSheetId);
        $training->setTitle($title);
        $training->setDate(new \DateTimeImmutable($date));
        $training->setTime(new \DateTimeImmutable('10:00'));
        $training->setSlots(10);
        $training->setSlotsAvailable(10);
        $training->setPrice(1000);

        return $training;
    }
}