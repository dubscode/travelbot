<?php

namespace App\Command;

use App\Service\VectorSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-vector-search',
    description: 'Test vector similarity search functionality',
)]
class TestVectorSearchCommand extends Command
{
    public function __construct(
        private VectorSearchService $vectorSearchService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('query', InputArgument::REQUIRED, 'Search query text')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $query = $input->getArgument('query');

        $io->title('Testing Vector Similarity Search');
        $io->note(sprintf('Searching for: "%s"', $query));

        try {
            // Test destination search
            $io->section('Destination Search Results');
            $destinations = $this->vectorSearchService->searchSimilarDestinations($query, 5, 0.1);
            
            if (empty($destinations)) {
                $io->warning('No destinations found with embeddings.');
            } else {
                $rows = [];
                foreach ($destinations as $result) {
                    $rows[] = [
                        $result['name'],
                        $result['country'],
                        round($result['similarity'], 4)
                    ];
                }
                $io->table(['Name', 'Country', 'Similarity'], $rows);
            }

            // Test amenity search
            $io->section('Amenity Search Results');
            $amenities = $this->vectorSearchService->searchSimilarAmenities($query, 5, 0.1);
            
            if (empty($amenities)) {
                $io->warning('No amenities found with embeddings.');
            } else {
                $rows = [];
                foreach ($amenities as $result) {
                    $rows[] = [
                        $result['name'],
                        $result['type'],
                        round($result['similarity'], 4)
                    ];
                }
                $io->table(['Name', 'Type', 'Similarity'], $rows);
            }

            $io->success('Vector search test completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to test vector search: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}