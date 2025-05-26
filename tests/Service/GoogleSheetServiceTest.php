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

    /**
     * @throws \DateMalformedStringException
     */
    public function testGetCachedTrainings(): void
    {
        $expectedTrainings = [
            $this->createTraining('1', 'Йога', '2025-03-25'),
            $this->createTraining('2', 'Пилатес', '2025-03-26'),
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
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
                'price' => '1000',
            ],
            [
                'id' => '2',
                'date' => '2025-03-26',
                'time' => '11:00',
                'title' => 'Пилатес',
                'slots' => 15,
                'price' => '1200',
            ],
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
        $this->assertEquals('1000', $existingTraining->getPrice());
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function createTraining($googleSheetId, $title, $date): Training
    {
        $training = new Training();
        $training->setGoogleSheetId($googleSheetId);
        $training->setTitle($title);
        $training->setDate(new \DateTimeImmutable($date));
        $training->setTime(new \DateTimeImmutable('10:00'));
        $training->setSlots(10);
        $training->setSlotsAvailable(10);
        $training->setPrice('1000');

        return $training;
    }

    /**
     * Test formatDate method with valid date formats
     */
    public function testFormatDateWithValidFormats(): void
    {
        $reflectionClass = new \ReflectionClass($this->googleSheetService);
        $formatDateMethod = $reflectionClass->getMethod('formatDate');
        $formatDateMethod->setAccessible(true);

        // Test d.m.y format
        $result = $formatDateMethod->invoke($this->googleSheetService, '25.03.23');
        $this->assertEquals('2023-03-25', $result);

        // Test Y-m-d format
        $result = $formatDateMethod->invoke($this->googleSheetService, '2023-03-25');
        $this->assertEquals('2023-03-25', $result);
    }

    /**
     * Test formatDate method with invalid date formats
     */
    public function testFormatDateWithInvalidFormats(): void
    {
        $reflectionClass = new \ReflectionClass($this->googleSheetService);
        $formatDateMethod = $reflectionClass->getMethod('formatDate');
        $formatDateMethod->setAccessible(true);

        // Test invalid format
        $result = $formatDateMethod->invoke($this->googleSheetService, 'invalid-date');
        $this->assertNull($result);

        // Test potentially malicious input
        $result = $formatDateMethod->invoke($this->googleSheetService, "'; DROP TABLE trainings; --");
        $this->assertNull($result);
    }

    /**
     * Test formatTime method with valid time formats
     */
    public function testFormatTimeWithValidFormats(): void
    {
        $reflectionClass = new \ReflectionClass($this->googleSheetService);
        $formatTimeMethod = $reflectionClass->getMethod('formatTime');
        $formatTimeMethod->setAccessible(true);

        // Test H:i format
        $result = $formatTimeMethod->invoke($this->googleSheetService, '14:30');
        $this->assertEquals('14:30:00', $result);

        // Test H.i format
        $result = $formatTimeMethod->invoke($this->googleSheetService, '14.30');
        $this->assertEquals('14:30:00', $result);
    }

    /**
     * Test formatTime method with invalid time formats
     */
    public function testFormatTimeWithInvalidFormats(): void
    {
        $reflectionClass = new \ReflectionClass($this->googleSheetService);
        $formatTimeMethod = $reflectionClass->getMethod('formatTime');
        $formatTimeMethod->setAccessible(true);

        // Test invalid format
        $result = $formatTimeMethod->invoke($this->googleSheetService, 'invalid-time');
        $this->assertNull($result);

        // Test potentially malicious input
        $result = $formatTimeMethod->invoke($this->googleSheetService, "'; DROP TABLE trainings; --");
        $this->assertNull($result);
    }

    /**
     * Test that fetchDataFromGoogleSheet skips rows with invalid date or time formats
     */
    public function testFetchDataFromGoogleSheetSkipsInvalidDateTimeRows(): void
    {
        $reflectionClass = new \ReflectionClass($this->googleSheetService);
        $fetchDataMethod = $reflectionClass->getMethod('fetchDataFromGoogleSheet');
        $fetchDataMethod->setAccessible(true);
        
        // Create a mock Sheets service
        $mockSheetsService = $this->createMock(\Google\Service\Sheets::class);
        $mockValues = $this->createMock(\Google\Service\Sheets\Resource\SpreadsheetsValues::class);
        $mockSheetsService->spreadsheets_values = $mockValues;
        
        // Set up the mock response
        $mockResponse = $this->createMock(\Google\Service\Sheets\ValueRange::class);
        $mockResponse->method('getValues')->willReturn([
            ['1', '25.03.23', 'Monday', '10:00', 'Valid Training', '20', '1000'], // Valid row
            ['2', 'invalid-date', 'Tuesday', '11:00', 'Invalid Date Training', '15', '1200'], // Invalid date
            ['3', '26.03.23', 'Wednesday', 'invalid-time', 'Invalid Time Training', '10', '1500'], // Invalid time
        ]);
        
        $mockValues->method('get')->willReturn($mockResponse);
        
        // Replace the real service with our mock
        $sheetsServiceProperty = $reflectionClass->getProperty('sheetsService');
        $sheetsServiceProperty->setAccessible(true);
        $sheetsServiceProperty->setValue($this->googleSheetService, $mockSheetsService);
        
        // Set up logger expectations before calling the method
        $this->logger->expects($this->atLeast(2))
            ->method('warning')
            ->with(
                $this->equalTo('Skipping row with invalid date or time format'),
                $this->anything()
            );
        
        // Call the method
        $result = $fetchDataMethod->invoke($this->googleSheetService);
        
        // Verify that only the valid row was included in the result
        $this->assertCount(1, $result);
        $this->assertEquals('1', $result[0]['id']);
        $this->assertEquals('2023-03-25', $result[0]['date']);
        $this->assertEquals('10:00:00', $result[0]['time']);
    }
}
