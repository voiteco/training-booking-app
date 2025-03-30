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

            return $dateString; // Возвращаем исходную строку, если не удалось преобразовать
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
        $googleSheetIds = array_column($data, 'id');
        $existingTrainings = $this->trainingRepository->findByGoogleSheetIds($googleSheetIds);

        // Создаем хеш-карту существующих тренировок для быстрого доступа
        $existingTrainingsMap = [];
        foreach ($existingTrainings as $training) {
            $existingTrainingsMap[$training->getGoogleSheetId()] = $training;
        }

        $updatedTrainings = 0;
        $newTrainings = 0;

        foreach ($data as $row) {
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

            $training->setDate(new \DateTime($row['date']));
            $training->setTime(new \DateTime($row['time']));
            $training->setTitle($row['title']);
            $training->setSlots($row['slots']);
            $training->setPrice($row['price']);

            $this->entityManager->persist($training);
        }

        $this->entityManager->flush();

        $this->logger->info('Trainings update completed', [
            'new_trainings' => $newTrainings,
            'updated_trainings' => $updatedTrainings,
        ]);
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
