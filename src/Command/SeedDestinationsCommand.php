<?php

namespace App\Command;

use App\Entity\Destination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-destinations',
    description: 'Seed the database with curated travel destinations',
)]
class SeedDestinationsCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing destinations before seeding')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $io->note('Clearing existing destinations...');
            $this->entityManager->createQuery('DELETE FROM App\Entity\Destination')->execute();
        }

        $destinations = $this->getDestinationData();
        $io->note(sprintf('Seeding %d destinations...', count($destinations)));

        foreach ($destinations as $destinationData) {
            $destination = new Destination();
            $destination->setName($destinationData['name']);
            $destination->setCountry($destinationData['country']);
            $destination->setCity($destinationData['city'] ?? null);
            $destination->setDescription($destinationData['description']);
            $destination->setTags($destinationData['tags']);
            $destination->setClimate($destinationData['climate']);
            $destination->setAverageCostPerDay($destinationData['averageCostPerDay']);
            $destination->setActivities($destinationData['activities']);
            $destination->setBestMonthsToVisit($destinationData['bestMonthsToVisit']);
            $destination->setLatitude($destinationData['latitude'] ?? null);
            $destination->setLongitude($destinationData['longitude'] ?? null);
            $destination->setImageUrl($destinationData['imageUrl'] ?? null);
            $destination->setPopularityScore($destinationData['popularityScore'] ?? 50);

            $this->entityManager->persist($destination);
        }

        $this->entityManager->flush();

        $io->success('Successfully seeded destinations database!');
        return Command::SUCCESS;
    }

    private function getDestinationData(): array
    {
        return [
            [
                'name' => 'Paris',
                'country' => 'France',
                'city' => 'Paris',
                'description' => 'The City of Light, famous for the Eiffel Tower, world-class museums, romantic atmosphere, and exceptional cuisine.',
                'tags' => ['romantic', 'culture', 'art', 'history', 'food', 'architecture'],
                'climate' => ['temperate', 'oceanic'],
                'averageCostPerDay' => '120.00',
                'activities' => ['museums', 'sightseeing', 'dining', 'shopping', 'walking tours'],
                'bestMonthsToVisit' => ['April', 'May', 'September', 'October'],
                'latitude' => '48.8566140',
                'longitude' => '2.3522220',
                'popularityScore' => 95
            ],
            [
                'name' => 'Tokyo',
                'country' => 'Japan',
                'city' => 'Tokyo',
                'description' => 'A vibrant metropolis blending ultra-modern technology with traditional culture, amazing food, and unique experiences.',
                'tags' => ['technology', 'culture', 'food', 'anime', 'traditional', 'modern'],
                'climate' => ['humid subtropical'],
                'averageCostPerDay' => '100.00',
                'activities' => ['temples', 'shopping', 'dining', 'nightlife', 'technology tours'],
                'bestMonthsToVisit' => ['March', 'April', 'May', 'October', 'November'],
                'latitude' => '35.6762000',
                'longitude' => '139.6503000',
                'popularityScore' => 90
            ],
            [
                'name' => 'Bali',
                'country' => 'Indonesia',
                'city' => null,
                'description' => 'Tropical paradise with stunning beaches, lush rice terraces, spiritual temples, and vibrant culture.',
                'tags' => ['tropical', 'beaches', 'spiritual', 'nature', 'relaxation', 'adventure'],
                'climate' => ['tropical'],
                'averageCostPerDay' => '50.00',
                'activities' => ['beach', 'surfing', 'temples', 'yoga', 'hiking', 'cultural tours'],
                'bestMonthsToVisit' => ['April', 'May', 'June', 'July', 'August', 'September'],
                'latitude' => '-8.3405389',
                'longitude' => '115.0919509',
                'popularityScore' => 85
            ],
            [
                'name' => 'New York City',
                'country' => 'United States',
                'city' => 'New York',
                'description' => 'The city that never sleeps, featuring iconic landmarks, Broadway shows, world-class dining, and endless energy.',
                'tags' => ['urban', 'culture', 'nightlife', 'entertainment', 'shopping', 'diverse'],
                'climate' => ['humid continental'],
                'averageCostPerDay' => '150.00',
                'activities' => ['Broadway shows', 'museums', 'shopping', 'dining', 'sightseeing'],
                'bestMonthsToVisit' => ['April', 'May', 'September', 'October', 'November'],
                'latitude' => '40.7127753',
                'longitude' => '-74.0059728',
                'popularityScore' => 92
            ],
            [
                'name' => 'Santorini',
                'country' => 'Greece',
                'city' => null,
                'description' => 'Stunning Greek island with white-washed buildings, blue-domed churches, breathtaking sunsets, and crystal-clear waters.',
                'tags' => ['romantic', 'island', 'beaches', 'photography', 'wine', 'sunset'],
                'climate' => ['mediterranean'],
                'averageCostPerDay' => '80.00',
                'activities' => ['beach', 'wine tasting', 'photography', 'boat tours', 'hiking'],
                'bestMonthsToVisit' => ['April', 'May', 'June', 'September', 'October'],
                'latitude' => '36.3932000',
                'longitude' => '25.4615000',
                'popularityScore' => 88
            ],
            [
                'name' => 'Machu Picchu',
                'country' => 'Peru',
                'city' => 'Cusco',
                'description' => 'Ancient Incan citadel high in the Andes Mountains, one of the New Seven Wonders of the World.',
                'tags' => ['history', 'adventure', 'hiking', 'ancient', 'mountains', 'spiritual'],
                'climate' => ['subtropical highland'],
                'averageCostPerDay' => '60.00',
                'activities' => ['hiking', 'historical tours', 'photography', 'trekking'],
                'bestMonthsToVisit' => ['May', 'June', 'July', 'August', 'September'],
                'latitude' => '-13.1631000',
                'longitude' => '-72.5450000',
                'popularityScore' => 91
            ],
            [
                'name' => 'Dubai',
                'country' => 'United Arab Emirates',
                'city' => 'Dubai',
                'description' => 'Ultra-modern city with luxury shopping, futuristic architecture, desert adventures, and world-class dining.',
                'tags' => ['luxury', 'modern', 'shopping', 'desert', 'architecture', 'business'],
                'climate' => ['hot desert'],
                'averageCostPerDay' => '130.00',
                'activities' => ['shopping', 'desert safari', 'dining', 'architecture tours', 'beaches'],
                'bestMonthsToVisit' => ['November', 'December', 'January', 'February', 'March'],
                'latitude' => '25.2048000',
                'longitude' => '55.2708000',
                'popularityScore' => 82
            ],
            [
                'name' => 'Iceland',
                'country' => 'Iceland',
                'city' => null,
                'description' => 'Land of fire and ice with dramatic landscapes, geysers, waterfalls, hot springs, and the Northern Lights.',
                'tags' => ['nature', 'adventure', 'northern lights', 'geothermal', 'dramatic landscapes'],
                'climate' => ['subarctic'],
                'averageCostPerDay' => '110.00',
                'activities' => ['northern lights', 'hot springs', 'glacier tours', 'hiking', 'photography'],
                'bestMonthsToVisit' => ['June', 'July', 'August', 'September'],
                'latitude' => '64.9630000',
                'longitude' => '-19.0208000',
                'popularityScore' => 79
            ],
            [
                'name' => 'Cape Town',
                'country' => 'South Africa',
                'city' => 'Cape Town',
                'description' => 'Stunning coastal city with Table Mountain, wine regions, beautiful beaches, and rich cultural heritage.',
                'tags' => ['nature', 'wine', 'beaches', 'culture', 'adventure', 'wildlife'],
                'climate' => ['mediterranean'],
                'averageCostPerDay' => '45.00',
                'activities' => ['wine tasting', 'hiking', 'beaches', 'wildlife tours', 'cultural tours'],
                'bestMonthsToVisit' => ['November', 'December', 'January', 'February', 'March'],
                'latitude' => '-33.9249000',
                'longitude' => '18.4241000',
                'popularityScore' => 83
            ],
            [
                'name' => 'Kyoto',
                'country' => 'Japan',
                'city' => 'Kyoto',
                'description' => 'Former imperial capital with over 2,000 temples, traditional gardens, geisha districts, and preserved culture.',
                'tags' => ['traditional', 'temples', 'culture', 'history', 'spiritual', 'gardens'],
                'climate' => ['humid subtropical'],
                'averageCostPerDay' => '85.00',
                'activities' => ['temples', 'gardens', 'cultural tours', 'traditional experiences', 'hiking'],
                'bestMonthsToVisit' => ['March', 'April', 'May', 'October', 'November'],
                'latitude' => '35.0116000',
                'longitude' => '135.7681000',
                'popularityScore' => 87
            ]
        ];
    }
}
