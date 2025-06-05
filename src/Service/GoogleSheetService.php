<?php

namespace App\Service;

use App\Entity\Training;
use App\Repository\TrainingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Exception;
use Google\Service\Sheets;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class GoogleSheetService
{
    private const string CACHE_KEY = 'google_sheet_trainings';
    private const int CACHE_TTL = 3600; // 1 hour
    private const string SHEET_RANGE = 'Trainings!A2:G'; // Начиная со второй строки (после заголовков)

    private ?Sheets $sheetsService = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TrainingRepository $trainingRepository,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private string $googleSheetId,
        private string $googleApiKey,
    ) {
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function syncTrainings(): void
    {
        try {
            $data = $this->fetchDataFromGoogleSheet();
            if (empty($data)) {
                $this->logger->warning('No data retrieved from Google Sheet');

                return;
            }

            $this->updateTrainingsFromData($data);

            // Обновляем кеш
            $this->cache->delete(self::CACHE_KEY);
            $this->logger->info('Trainings synchronized successfully from Google Sheet');
        } catch (\Exception $e) {
            $this->logger->error('Error syncing trainings from Google Sheet: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getCachedTrainings(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->trainingRepository->findUpcoming();
        });
    }

    /**
     * @throws Exception
     */
    private function fetchDataFromGoogleSheet(): array
    {
        $sheetsService = $this->getSheetsService();

        // Получаем данные из Google Sheet
        $response = $sheetsService->spreadsheets_values->get(
            $this->googleSheetId,
            self::SHEET_RANGE
        );

        $values = $response->getValues();

        if (empty($values)) {
            return [];
        }

        // Преобразуем данные из Google Sheet в структурированный массив
        $formattedData = [];
        foreach ($values as $row) {
            // Проверяем, что в строке достаточно данных
            if (count($row) < 7) {
                $this->logger->warning('Skipping incomplete row in Google Sheet', ['row' => $row]);
                continue;
            }

            // Предполагаем, что столбцы в таблице идут в порядке:
            // ID, Дата, Время, Название, Места, Цена
            $formattedData[] = [
                'id' => $row[0],
                'date' => $this->formatDate($row[1]),
                'dayOfWeek' => $row[2],
                'time' => $this->formatTime($row[3]),
                'title' => $row[4],
                'slots' => (int) $row[5],
                'price' => (float) $row[6],
            ];
        }

        return $formattedData;
    }

    private function formatDate(string $dateString): string
    {
        // Преобразуем дату из формата, используемого в Google Sheet, в формат Y-m-d
        try {
            $date = \DateTime::createFromFormat('d.m.y', $dateString);
            if (!$date) {
                // Пробуем другой формат
                $date = \DateTime::createFromFormat('Y-m-d', $dateString);
            }

            if (!$date) {
                throw new \Exception("Invalid date format: $dateString");
            }

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            $this->logger->warning('Error formatting date', [
                'date' => $dateString,
                'error' => $e->getMessage(),
            ]);

'error' => $e->getMessage(),
            ]);

            throw new \InvalidArgumentException("Invalid date format: $dateString", 0, $e);
        }
    }
        }
    }

    private function formatTime(string $timeString): string
    {
        // Преобразуем время из формата, используемого в Google Sheet, в формат H:i
        try {
            $time = \DateTime::createFromFormat('H:i', $timeString);
            if (!$time) {
                // Пробуем другой формат
                $time = \DateTime::createFromFormat('H.i', $timeString);
            }

            if (!$time) {
                throw new \Exception("Invalid time format: $timeString");
            }

            return $time->format('H:i:s');
        } catch (\Exception $e) {
            $this->logger->warning('Error formatting time', [
                'time' => $timeString,
                'error' => $e->getMessage(),
            ]);

            return $timeString.':00'; // Добавляем секунды, если не удалось преобразовать
        }
    }

    private function updateTrainingsFromData(array $data): void
    {
        // Validate data before processing
        $validatedData = [];
        $invalidRows = 0;
        
        foreach ($data as $index => $row) {
            if ($this->validateTrainingData($row, $index)) {
                $validatedData[] = $row;
            } else {
                $invalidRows++;
            }
        }
        
        if (empty($validatedData)) {
            $this->logger->warning('No valid training data found to process');
            return;
        }
        
        $googleSheetIds = array_column($validatedData, 'id');
        $existingTrainings = $this->trainingRepository->findByGoogleSheetIds($googleSheetIds);

        // Создаем хеш-карту существующих тренировок для быстрого доступа
        $existingTrainingsMap = [];
        foreach ($existingTrainings as $training) {
            $existingTrainingsMap[$training->getGoogleSheetId()] = $training;
        }

        $updatedTrainings = 0;
        $newTrainings = 0;

        foreach ($validatedData as $row) {
            $googleSheetId = $row['id'];
            $training = $existingTrainingsMap[$googleSheetId] ?? null;

            if (!$training) {
                $training = new Training();
                $training->setGoogleSheetId($googleSheetId);
                $training->setSlotsAvailable($row['slots']); // Изначально все места свободны
                ++$newTrainings;
            } else {
                ++$updatedTrainings;
            }

            try {
                $training->setDate(new \DateTime($row['date']));
                $training->setTime(new \DateTime($row['time']));
                $training->setTitle($row['title']);
                $training->setSlots($row['slots']);
                $training->setPrice($row['price']);

                $this->entityManager->persist($training);
            } catch (\Exception $e) {
                $this->logger->error('Error processing training row', [
                    'google_sheet_id' => $googleSheetId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Trainings update completed', [
            'new_trainings' => $newTrainings,
            'updated_trainings' => $updatedTrainings,
'new_trainings' => $newTrainings,
            'updated_trainings' => $updatedTrainings,
            'invalid_rows' => $invalidRows,
            'invalid_row_details' => $this->invalidRowDetails, // Add this line
        ]);
    }
        ]);
    }
    
    /**
     * Validates a single row of training data from Google Sheets
     * 
     * @param array $row The row data to validate
     * @param int $rowIndex The index of the row for logging purposes
     * @return bool True if the data is valid, false otherwise
     */
    private function validateTrainingData(array $row, int $rowIndex): bool
    {
        $requiredFields = ['id', 'date', 'time', 'title', 'slots', 'price'];
        
        // Check if all required fields exist
        foreach ($requiredFields as $field) {
            if (!isset($row[$field]) || $row[$field] === '') {
                $this->logger->warning('Missing required field in training data', [
                    'row_index' => $rowIndex,
                    'missing_field' => $field,
                    'row_data' => $row,
                ]);
                return false;
            }
        }
        
        // Validate ID
        if (!is_string($row['id']) || trim($row['id']) === '') {
            $this->logger->warning('Invalid ID in training data', [
                'row_index' => $rowIndex,
                'id' => $row['id'],
            ]);
            return false;
        }
        
        // Validate date
        try {
            $date = new \DateTime($row['date']);
            if (!$date) {
                throw new \Exception('Invalid date format');
            }
        } catch (\Exception $e) {
            $this->logger->warning('Invalid date format in training data', [
                'row_index' => $rowIndex,
                'date' => $row['date'],
                'error' => $e->getMessage(),
            ]);
            return false;
        }
        
        // Validate time
        try {
            $time = new \DateTime($row['time']);
            if (!$time) {
                throw new \Exception('Invalid time format');
            }
        } catch (\Exception $e) {
            $this->logger->warning('Invalid time format in training data', [
                'row_index' => $rowIndex,
                'time' => $row['time'],
                'error' => $e->getMessage(),
            ]);
            return false;
        }
        
        // Validate title
        if (!is_string($row['title']) || trim($row['title']) === '') {
            $this->logger->warning('Invalid title in training data', [
                'row_index' => $rowIndex,
                'title' => $row['title'],
            ]);
            return false;
        }
        
        // Validate slots
        if (!is_numeric($row['slots']) || (int)$row['slots'] <= 0) {
            $this->logger->warning('Invalid slots in training data', [
                'row_index' => $rowIndex,
                'slots' => $row['slots'],
            ]);
            return false;
        }
        
        // Validate price
        if (!is_numeric($row['price']) || (float)$row['price'] < 0) {
            $this->logger->warning('Invalid price in training data', [
                'row_index' => $rowIndex,
                'price' => $row['price'],
            ]);
            return false;
        }
        
        return true;
    }

    private function getSheetsService(): Sheets
    {
        if ($this->sheetsService === null) {
            $client = new Client();
            $client->setApplicationName('Training Booking System');
            $client->setDeveloperKey($this->googleApiKey);

            $this->sheetsService = new Sheets($client);
        }

        return $this->sheetsService;
    }
}
