<?php

namespace App\Command;

use App\Service\GoogleSheetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-trainings',
    description: 'Synchronize trainings from Google Sheet'
)]
class SyncTrainingsCommand extends Command
{
    public function __construct(
        private GoogleSheetService $googleSheetService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Starting synchronization with Google Sheets');

        try {
            $this->googleSheetService->syncTrainings();
            $io->success('Trainings synchronized successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error synchronizing trainings: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}