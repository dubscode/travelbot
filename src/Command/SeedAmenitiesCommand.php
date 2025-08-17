<?php

namespace App\Command;

use App\Entity\Amenity;
use App\Entity\Resort;
use App\Generator\AmenityGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-amenities',
    description: 'Seed the database with amenities and assign them to resorts',
)]
class SeedAmenitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AmenityGenerator $amenityGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing amenities before seeding')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $io->note('Clearing existing amenities and resort-amenity relationships...');
            // Clear the many-to-many relationships first
            $this->entityManager->createQuery('DELETE FROM App\Entity\Amenity')->execute();
        }

        // First, create all amenities
        $this->seedAmenities($io);

        // Then assign amenities to resorts
        $this->assignAmenitiesToResorts($io);

        $io->success('Successfully seeded amenities and assigned them to resorts!');
        return Command::SUCCESS;
    }

    private function seedAmenities(SymfonyStyle $io): void
    {
        $amenityRepo = $this->entityManager->getRepository(Amenity::class);
        $existingCount = $amenityRepo->count([]);

        if ($existingCount > 0) {
            $io->note(sprintf('Found %d existing amenities, skipping amenity creation.', $existingCount));
            return;
        }

        $io->note('Creating all available amenities...');
        $amenities = $this->amenityGenerator->generateAmenities();

        foreach ($amenities as $amenityData) {
            $amenity = new Amenity();
            $amenity->setName($amenityData['name']);
            $amenity->setType($amenityData['type']);
            $amenity->setDescription($amenityData['description']);

            $this->entityManager->persist($amenity);
        }

        $this->entityManager->flush();
        $io->note(sprintf('Created %d amenities across %d categories.', 
            count($amenities), 
            count($this->amenityGenerator->getAmenityCategories())
        ));
    }

    private function assignAmenitiesToResorts(SymfonyStyle $io): void
    {
        $resortRepo = $this->entityManager->getRepository(Resort::class);
        $amenityRepo = $this->entityManager->getRepository(Amenity::class);

        // Get total count without loading all entities
        $totalResorts = $resortRepo->count([]);

        if ($totalResorts === 0) {
            $io->error('No resorts found. Please run app:seed-resorts first.');
            return;
        }

        $io->note(sprintf('Assigning amenities to %d resorts...', $totalResorts));
        $io->progressStart($totalResorts);

        $batchSize = 20;
        $offset = 0;
        $processedCount = 0;
        
        // Process resorts in batches to avoid memory exhaustion
        while ($offset < $totalResorts) {
            // Load only a batch of resorts
            $resorts = $resortRepo->createQueryBuilder('r')
                ->leftJoin('r.category', 'c')
                ->addSelect('c')  // Preload category to avoid lazy loading
                ->orderBy('r.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($resorts as $resort) {
                $starRating = $resort->getStarRating() ?: 3;
                $categoryName = $resort->getCategory()?->getName() ?? 'Urban Hotel';

                // Clear existing amenities for this resort
                foreach ($resort->getAmenities() as $amenity) {
                    $resort->removeAmenity($amenity);
                }

                // Get appropriate amenities for this resort
                $selectedAmenities = $this->selectAmenitiesForResort($amenityRepo, $starRating, $categoryName);

                // Add selected amenities
                foreach ($selectedAmenities as $amenity) {
                    $resort->addAmenity($amenity);
                }

                $io->progressAdvance();
                $processedCount++;
            }
            
            // Flush and clear to free memory after each batch
            $this->entityManager->flush();
            $this->entityManager->clear();
            
            $offset += $batchSize;
            
            // Log memory usage every 100 resorts for monitoring
            if ($processedCount % 100 === 0) {
                $memoryUsage = memory_get_usage(true) / 1024 / 1024;
                $io->text(sprintf('   Processed %d/%d resorts. Memory usage: %.2f MB', 
                    $processedCount, $totalResorts, $memoryUsage));
            }
        }

        $io->progressFinish();
        $io->success(sprintf('Successfully assigned amenities to %d resorts!', $totalResorts));
    }

    private function selectAmenitiesForResort($amenityRepo, int $starRating, string $categoryName): array
    {
        // Get target count for this star rating
        $targetCount = $this->amenityGenerator->getAmenityCountForRating($starRating);
        
        // Always include basic amenities (dining, business)
        $basicAmenities = $amenityRepo->createQueryBuilder('a')
            ->where('a.type IN (:types)')
            ->setParameter('types', ['dining', 'business'])
            ->getQuery()
            ->getResult();

        $selectedAmenities = $basicAmenities;
        $remainingCount = $targetCount - count($basicAmenities);

        if ($remainingCount > 0) {
            // Get amenity types allowed for this star rating
            $allowedTypes = $this->getAllowedTypesForRating($starRating);
            
            // Get category-specific amenities first
            $categoryAmenities = $this->getCategorySpecificAmenities($amenityRepo, $categoryName, $allowedTypes);
            
            // Add category amenities up to limit
            $addCount = min($remainingCount, count($categoryAmenities));
            $selectedAmenities = array_merge($selectedAmenities, array_slice($categoryAmenities, 0, $addCount));
            $remainingCount -= $addCount;

            // Fill remaining slots with random appropriate amenities
            if ($remainingCount > 0) {
                $existingIds = array_map(fn($a) => $a->getId(), $selectedAmenities);
                
                $qb = $amenityRepo->createQueryBuilder('a')
                    ->where('a.type IN (:types)')
                    ->setParameter('types', $allowedTypes);
                    
                if (!empty($existingIds)) {
                    $qb->andWhere('a.id NOT IN (:existing)')
                       ->setParameter('existing', $existingIds);
                }
                
                // Get all matching amenities and randomly select from them
                $availableAmenities = $qb->getQuery()->getResult();
                if (!empty($availableAmenities)) {
                    shuffle($availableAmenities);
                    $additionalAmenities = array_slice($availableAmenities, 0, $remainingCount);
                    $selectedAmenities = array_merge($selectedAmenities, $additionalAmenities);
                }
            }
        }

        return $selectedAmenities;
    }

    private function getAllowedTypesForRating(int $starRating): array
    {
        $basicTypes = ['dining', 'business', 'transportation'];
        
        return match ($starRating) {
            1 => $basicTypes,
            2 => array_merge($basicTypes, ['recreation']),
            3 => array_merge($basicTypes, ['recreation', 'entertainment']),
            4 => array_merge($basicTypes, ['recreation', 'entertainment', 'wellness', 'family']),
            5 => array_merge($basicTypes, ['recreation', 'entertainment', 'wellness', 'family', 'luxury', 'technology']),
            default => array_merge($basicTypes, ['recreation', 'entertainment'])
        };
    }

    private function getCategorySpecificAmenities($amenityRepo, string $categoryName, array $allowedTypes): array
    {
        $categoryLower = strtolower($categoryName);
        $preferredNames = [];

        // Beach/Ocean resorts
        if (str_contains($categoryLower, 'beach') || str_contains($categoryLower, 'island') || str_contains($categoryLower, 'ocean')) {
            $preferredNames = ['Swimming Pool', 'Beach Access', 'Water Sports', 'Poolside Bar', 'Beachside Grill', 'Marina'];
        }
        // Mountain/Ski resorts
        elseif (str_contains($categoryLower, 'mountain') || str_contains($categoryLower, 'ski') || str_contains($categoryLower, 'alpine')) {
            $preferredNames = ['Spa', 'Fitness Center', 'Hiking Trails', 'Sauna', 'Steakhouse'];
        }
        // Spa/Wellness resorts
        elseif (str_contains($categoryLower, 'spa') || str_contains($categoryLower, 'wellness')) {
            $preferredNames = ['Spa', 'Yoga Classes', 'Meditation Garden', 'Massage Therapy', 'Sauna', 'Steam Room'];
        }
        // Business hotels
        elseif (str_contains($categoryLower, 'business') || str_contains($categoryLower, 'urban')) {
            $preferredNames = ['Business Center', 'Meeting Rooms', 'Executive Lounge', 'High-Speed WiFi', 'Conference Center'];
        }
        // Family resorts
        elseif (str_contains($categoryLower, 'family')) {
            $preferredNames = ['Kids Club', 'Playground', 'Family Pool', 'Game Room'];
        }
        // Golf resorts
        elseif (str_contains($categoryLower, 'golf')) {
            $preferredNames = ['Golf Course', 'Golf Pro Shop', 'Driving Range', 'Golf Lessons'];
        }

        if (empty($preferredNames)) {
            return [];
        }

        return $amenityRepo->createQueryBuilder('a')
            ->where('a.name IN (:names)')
            ->andWhere('a.type IN (:types)')
            ->setParameter('names', $preferredNames)
            ->setParameter('types', $allowedTypes)
            ->getQuery()
            ->getResult();
    }
}