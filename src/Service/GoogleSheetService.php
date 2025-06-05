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
    private const string SHEET_RANGE = 'Trainings!A2:G'; // Starting from the second row (after headers)

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
     * Synchronizes trainings from Google Sheet
     * 
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

            // Update cache
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

    /**
     * Gets cached trainings or fetches them from the repository
     * 
     * @return array The list of upcoming trainings
     * @throws InvalidArgumentException
     */
    public function getCachedTrainings(): array
    {
        try {
            return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
                $item->expiresAfter(self::CACHE_TTL);
                return $this->trainingRepository->findUpcoming();
            });
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Cache error when getting trainings: ' . $e->getMessage());
            // Fallback to direct repository call if cache fails
            return $this->trainingRepository->findUpcoming();
        }
    }

    /**
     * Fetches data from Google Sheet
     * 
     * @return array The formatted data from Google Sheet
     * @throws Exception
     */
    private function fetchDataFromGoogleSheet(): array
    {
        try {
            $sheetsService = $this->getSheetsService();

            // Get data from Google Sheet
            $response = $sheetsService->spreadsheets_values->get(
                $this->googleSheetId,
                self::SHEET_RANGE
            );

            $values = $response->getValues();

            if (empty($values)) {
                return [];
            }

            // Transform data from Google Sheet into a structured array
            $formattedData = [];
            foreach ($values as $rowIndex => $row) {
                // Check if the row has enough data
                if (count($row) < 7) {
                    $this->logger->warning('Skipping incomplete row in Google Sheet', [
                        'row_index' => $rowIndex + 2, // +2 because we start from A2
                        'row_data' => $row
                    ]);
                    continue;
                }

                // Assume columns in the table are in order:
                // ID, Date, Day of Week, Time, Title, Slots, Price
                $formattedDate = $this->formatDate($row[1]);
                $formattedTime = $this->formatTime($row[3]);
                
                // Skip rows with invalid dates or times
                if ($formattedDate === null || $formattedTime === null) {
                    $this->logger->warning('Skipping row with invalid date or time format', [
                        'row_index' => $rowIndex + 2,
                        'date' => $row[1] ?? 'missing',
                        'time' => $row[3] ?? 'missing'
                    ]);
                    continue;
                }
                
                // Validate numeric fields
                $slots = $this->validateInteger($row[5], 0);
                $price = $this->validateFloat($row[6], 0.0);
                
                $formattedData[] = [
                    'id' => trim($row[0]),
                    'date' => $formattedDate,
                    'dayOfWeek' => trim($row[2] ?? ''),
                    'time' => $formattedTime,
                    'title' => trim($row[4] ?? ''),
                    'slots' => $slots,
                    'price' => $price,
                ];
            }

            return $formattedData;
        } catch (Exception $e) {
            $this->logger->error('Google Sheets API error: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching data from Google Sheet: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Formats a date string to Y-m-d format
     * 
     * @param string $dateString The date string to format
     * @return string|null Formatted date string or null if parsing fails
     */
    private function formatDate(string $dateString): ?string
    {
        // Convert date from the format used in Google Sheet to Y-m-d format
        try {
            $dateString = trim($dateString);
            $date = \DateTime::createFromFormat('d.m.y', $dateString);
            if (!$date) {
                // Try another format
                $date = \DateTime::createFromFormat('Y-m-d', $dateString);
            }

            if (!$date) {
                // Try one more format
                $date = \DateTime::createFromFormat('d.m.Y', $dateString);
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

            return null;
        }
    }

    /**
     * Formats a time string to H:i:s format
     * 
     * @param string $timeString The time string to format
     * @return string|null Formatted time string or null if parsing fails
     */
    private function formatTime(string $timeString): ?string
    {
        // Convert time from the format used in Google Sheet to H:i:s format
        try {
            $timeString = trim($timeString);
            $time = \DateTime::createFromFormat('H:i', $timeString);
            if (!$time) {
                // Try another format
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

            return null;
        }
    }

    /**
     * Validates and converts a value to integer
     * 
     * @param mixed $value The value to validate
     * @param int $default The default value if validation fails
     * @return int The validated integer
     */
    private function validateInteger($value, int $default = 0): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        return $default;
    }
    
    /**
     * Validates and converts a value to float
     * 
     * @param mixed $value The value to validate
     * @param float $default The default value if validation fails
     * @return float The validated float
     */
    private function validateFloat($value, float $default = 0.0): float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        
        return $default;
    }

    /**
     * Updates trainings from the formatted data
     * 
     * @param array $data The formatted data from Google Sheet
     */
    private function updateTrainingsFromData(array $data): void
    {
        if (empty($data)) {
            $this->logger->info('No data to update trainings');
            return;
        }

        $googleSheetIds = array_column($data, 'id');
        $existingTrainings = $this->trainingRepository->findByGoogleSheetIds($googleSheetIds);

        // Create a hash map of existing trainings for quick access
        $existingTrainingsMap = [];
        foreach ($existingTrainings as $training) {
            $existingTrainingsMap[$training->getGoogleSheetId()] = $training;
        }

        $updatedTrainings = 0;
        $newTrainings = 0;
        $skippedTrainings = 0;

        foreach ($data as $row) {
            try {
                $googleSheetId = $row['id'];
                if (empty($googleSheetId)) {
                    $this->logger->warning('Skipping row with empty Google Sheet ID');
                    $skippedTrainings++;
                    continue;
                }

                $training = $existingTrainingsMap[$googleSheetId] ?? null;

                if (!$training) {
                    $training = new Training();
                    $training->setGoogleSheetId($googleSheetId);
                    $training->setSlotsAvailable($row['slots']); // Initially all slots are available
                    ++$newTrainings;
                } else {
                    ++$updatedTrainings;
                }

                $training->setDate(new \DateTime($row['date']));
                $training->setTime(new \DateTime($row['time']));
                $training->setTitle($row['title']);
                $training->setSlots($row['slots']);
                $training->setPrice($row['price']);

                $this->entityManager->persist($training);
            } catch (\Exception $e) {
                $this->logger->error('Error updating training from row: ' . $e->getMessage(), [
                    'row' => $row,
                    'error' => $e->getMessage()
                ]);
                $skippedTrainings++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Trainings update completed', [
            'new_trainings' => $newTrainings,
            'updated_trainings' => $updatedTrainings,
            'skipped_trainings' => $skippedTrainings,
        ]);
    }

    /**
     * Gets or creates a Google Sheets service
     * 
     * @return Sheets The Google Sheets service
     */
    private function getSheetsService(): Sheets
    {
        if ($this->sheetsService === null) {
            try {
                $client = new Client();
                $client->setApplicationName('Training Booking System');
                $client->setDeveloperKey($this->googleApiKey);

                $this->sheetsService = new Sheets($client);
            } catch (\Exception $e) {
                $this->logger->error('Error creating Google Sheets service: ' . $e->getMessage());
                throw $e;
            }
        }

        return $this->sheetsService;
    }
}