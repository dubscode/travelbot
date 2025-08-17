<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clear-all',
    description: 'Clear all seeded data from the database (destinations, resorts, amenities, categories)',
)]
class ClearAllDataCommand extends Command
{
    private array $entities = [
        'resort_amenity' => 'Resort-Amenity relationships',
        'resort' => 'Resorts',
        'amenity' => 'Amenities',
        'destination' => 'Destinations',
        'resort_category' => 'Resort Categories'
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Clear only specific entities (comma-separated: resorts,amenities,destinations,categories)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $only = $input->getOption('only');

        // Determine which entities to clear
        $entitiesToClear = $this->determineEntitiesToClear($only);

        if (empty($entitiesToClear)) {
            $io->error('No valid entities specified to clear.');
            return Command::FAILURE;
        }

        // Show what will be cleared
        $io->title('Data Clearing Operation');
        $io->section('The following data will be permanently deleted:');
        foreach ($entitiesToClear as $table => $description) {
            $io->text(sprintf('  • %s', $description));
        }

        // Confirmation prompt unless forced
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'Are you sure you want to delete all this data? This action cannot be undone. (yes/no) ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Execute clearing in the correct order
        $io->section('Clearing data...');
        $io->progressStart(count($entitiesToClear));

        $connection = $this->entityManager->getConnection();
        
        try {
            $connection->beginTransaction();

            foreach ($entitiesToClear as $table => $description) {
                $io->text(sprintf('Clearing %s...', $description));
                
                if ($table === 'resort_amenity') {
                    // This is a junction table, use raw SQL
                    $connection->executeStatement('DELETE FROM resort_amenity');
                } else {
                    // Use DQL for entity tables
                    $entityClass = $this->getEntityClass($table);
                    if ($entityClass) {
                        $query = $this->entityManager->createQuery(sprintf('DELETE FROM %s', $entityClass));
                        $deleted = $query->execute();
                    }
                }
                
                $io->progressAdvance();
            }

            $connection->commit();
            $io->progressFinish();
            
            // Clear entity manager to free memory
            $this->entityManager->clear();
            
            $io->success('All specified data has been successfully cleared!');
            
        } catch (\Exception $e) {
            $connection->rollBack();
            $io->error(sprintf('Failed to clear data: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function determineEntitiesToClear(?string $only): array
    {
        if ($only === null) {
            return $this->entities;
        }

        $requested = array_map('trim', explode(',', $only));
        $entitiesToClear = [];
        
        $mapping = [
            'resorts' => ['resort_amenity', 'resort'],
            'amenities' => ['resort_amenity', 'amenity'],
            'destinations' => ['resort_amenity', 'resort', 'destination'],
            'categories' => ['resort_amenity', 'resort', 'resort_category']
        ];

        foreach ($requested as $request) {
            if (isset($mapping[$request])) {
                foreach ($mapping[$request] as $table) {
                    $entitiesToClear[$table] = $this->entities[$table];
                }
            }
        }

        // Ensure proper order if multiple entities selected
        $orderedEntities = [];
        foreach ($this->entities as $table => $description) {
            if (isset($entitiesToClear[$table])) {
                $orderedEntities[$table] = $description;
            }
        }

        return $orderedEntities;
    }

    private function getEntityClass(string $table): ?string
    {
        return match ($table) {
            'resort' => 'App\Entity\Resort',
            'amenity' => 'App\Entity\Amenity',
            'destination' => 'App\Entity\Destination',
            'resort_category' => 'App\Entity\ResortCategory',
            default => null
        };
    }
}