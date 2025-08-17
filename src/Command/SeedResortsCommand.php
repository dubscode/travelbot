<?php

namespace App\Command;

use App\Entity\Resort;
use App\Entity\Destination;
use App\Entity\ResortCategory;
use App\Generator\ResortGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-resorts',
    description: 'Seed the database with resorts for existing destinations',
)]
class SeedResortsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResortGenerator $resortGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing resorts before seeding')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Minimum number of resorts per destination', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $io->note('Clearing existing resorts...');
            $this->entityManager->createQuery('DELETE FROM App\Entity\Resort')->execute();
        }

        // First, seed resort categories if they don't exist
        $this->seedResortCategories($io);

        // Get all destinations
        $destinationRepo = $this->entityManager->getRepository(Destination::class);
        $categoryRepo = $this->entityManager->getRepository(ResortCategory::class);
        $resortRepo = $this->entityManager->getRepository(Resort::class);
        
        $minResortsPerDestination = (int) $input->getOption('count');
        
        // Get total count without loading all entities
        $totalDestinations = $destinationRepo->count([]);

        if ($totalDestinations === 0) {
            $io->error('No destinations found. Please run app:seed-destinations first.');
            return Command::FAILURE;
        }

        // Load categories once (small dataset, unlikely to cause memory issues)
        $categories = $categoryRepo->findAll();
        if (empty($categories)) {
            $io->error('No resort categories found. Unable to continue.');
            return Command::FAILURE;
        }

        $io->note(sprintf('Ensuring at least %d resorts per destination for %d destinations...', $minResortsPerDestination, $totalDestinations));
        $io->progressStart($totalDestinations);

        $totalNewResorts = 0;
        $batchSize = 10;
        $offset = 0;
        $processedCount = 0;
        
        // Process destinations in batches to avoid memory exhaustion
        while ($offset < $totalDestinations) {
            // Load only a batch of destinations
            $destinations = $destinationRepo->createQueryBuilder('d')
                ->orderBy('d.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            foreach ($destinations as $destination) {
                // Check existing resort count for this destination
                $existingCount = $resortRepo->count(['destination' => $destination]);
                $neededCount = max(0, $minResortsPerDestination - $existingCount);

                if ($neededCount > 0) {
                    $resortData = $this->resortGenerator->generateForDestination($destination, $categories, $neededCount);

                    foreach ($resortData as $data) {
                        $resort = new Resort();
                        $resort->setName($data['name']);
                        $resort->setStarRating($data['starRating']);
                        $resort->setTotalRooms($data['totalRooms']);
                        $resort->setDescription($data['description']);
                        $resort->setDestination($data['destination']);
                        $resort->setCategory($data['category']);

                        $this->entityManager->persist($resort);
                        $totalNewResorts++;
                    }
                }

                $io->progressAdvance();
                $processedCount++;
            }

            // Flush and clear to free memory after each batch
            $this->entityManager->flush();
            $this->entityManager->clear();
            
            // Re-fetch categories since we cleared
            $categories = $categoryRepo->findAll();
            
            $offset += $batchSize;
            
            // Log memory usage every 50 destinations for monitoring
            if ($processedCount % 50 === 0) {
                $memoryUsage = memory_get_usage(true) / 1024 / 1024;
                $io->text(sprintf('   Processed %d/%d destinations. Memory usage: %.2f MB', 
                    $processedCount, $totalDestinations, $memoryUsage));
            }
        }

        $io->progressFinish();
        $io->success(sprintf('Successfully ensured minimum resorts! Added %d new resorts across %d destinations.', $totalNewResorts, $totalDestinations));
        
        return Command::SUCCESS;
    }

    private function seedResortCategories(SymfonyStyle $io): void
    {
        $categoryRepo = $this->entityManager->getRepository(ResortCategory::class);
        $existingCount = $categoryRepo->count([]);

        if ($existingCount > 0) {
            $io->note(sprintf('Found %d existing resort categories, skipping category seeding.', $existingCount));
            return;
        }

        $io->note('Seeding resort categories...');
        $categories = $this->resortGenerator->generateCategories();

        foreach ($categories as $categoryData) {
            $category = new ResortCategory();
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description']);

            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();
        $io->note(sprintf('Seeded %d resort categories.', count($categories)));
    }
}