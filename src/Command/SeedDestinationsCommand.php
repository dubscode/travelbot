<?php

namespace App\Command;

use App\Entity\Destination;
use App\Generator\DestinationGenerator;
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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DestinationGenerator $destinationGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing destinations before seeding')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Number of destinations to generate', 100)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');

        if ($input->getOption('clear')) {
            $io->note('Clearing existing destinations...');
            $this->entityManager->createQuery('DELETE FROM App\Entity\Destination')->execute();
        }

        $io->note(sprintf('Generating %d destinations using AI-powered data...', $count));
        $io->progressStart($count);

        $batchSize = 20;
        $destinations = $this->destinationGenerator->generateDestinations($count);

        foreach ($destinations as $index => $destinationData) {
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
            $io->progressAdvance();

            // Flush in batches to avoid memory issues
            if (($index + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        // Final flush for remaining entities
        $this->entityManager->flush();
        $this->entityManager->clear();
        
        $io->progressFinish();
        $io->success(sprintf('Successfully generated and seeded %d destinations!', count($destinations)));
        
        return Command::SUCCESS;
    }
}
