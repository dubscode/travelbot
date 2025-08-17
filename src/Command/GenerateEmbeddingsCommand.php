<?php

namespace App\Command;

use App\Entity\Amenity;
use App\Entity\Destination;
use App\Entity\Resort;
use App\Entity\ResortCategory;
use App\Message\GenerateEmbeddingMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:generate-embeddings',
    description: 'Generate vector embeddings for amenities and resort categories using AWS Bedrock Titan',
)]
class GenerateEmbeddingsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Entity to process: amenity, category, destination, resort, or all', 'all')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Regenerate embeddings even if they already exist')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Batch size for processing', 10)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entity = $input->getOption('entity');
        $force = $input->getOption('force');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('Generating Vector Embeddings with AWS Bedrock Titan');

        $io->note('Dispatching embedding generation jobs to message queue...');

        switch ($entity) {
            case 'amenity':
                $this->dispatchAmenityEmbeddings($io, $force);
                break;
            case 'category':
                $this->dispatchCategoryEmbeddings($io, $force);
                break;
            case 'destination':
                $this->dispatchDestinationEmbeddings($io, $force);
                break;
            case 'resort':
                $this->dispatchResortEmbeddings($io, $force);
                break;
            case 'all':
                $this->dispatchAmenityEmbeddings($io, $force);
                $this->dispatchCategoryEmbeddings($io, $force);
                $this->dispatchDestinationEmbeddings($io, $force);
                $this->dispatchResortEmbeddings($io, $force);
                break;
            default:
                $io->error("Invalid entity type. Use 'amenity', 'category', 'destination', 'resort', or 'all'.");
                return Command::FAILURE;
        }

        $io->success('Successfully dispatched embedding generation jobs to queue!');
        $io->note('Use bin/console messenger:consume embeddings to process the jobs.');
        return Command::SUCCESS;
    }


    private function dispatchAmenityEmbeddings(SymfonyStyle $io, bool $force): void
    {
        $amenityRepo = $this->entityManager->getRepository(Amenity::class);
        
        $qb = $amenityRepo->createQueryBuilder('a')
            ->select('a.id');
        if (!$force) {
            $qb->where('a.embedding IS NULL');
        }
        
        $amenityIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($amenityIds)) {
            $io->note('No amenities need embedding generation.');
            return;
        }

        $io->section('Dispatching Amenity Embedding Jobs');
        $io->note(sprintf('Dispatching %d amenity embedding jobs...', count($amenityIds)));
        
        foreach ($amenityIds as $amenityId) {
            $message = new GenerateEmbeddingMessage('amenity', (string) $amenityId, $force);
            $this->messageBus->dispatch($message);
        }
        
        $io->success(sprintf('Dispatched %d amenity embedding jobs to queue.', count($amenityIds)));
    }

    private function dispatchCategoryEmbeddings(SymfonyStyle $io, bool $force): void
    {
        $categoryRepo = $this->entityManager->getRepository(ResortCategory::class);
        
        $qb = $categoryRepo->createQueryBuilder('c')
            ->select('c.id');
        if (!$force) {
            $qb->where('c.embedding IS NULL');
        }
        
        $categoryIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($categoryIds)) {
            $io->note('No resort categories need embedding generation.');
            return;
        }

        $io->section('Dispatching Category Embedding Jobs');
        $io->note(sprintf('Dispatching %d category embedding jobs...', count($categoryIds)));
        
        foreach ($categoryIds as $categoryId) {
            $message = new GenerateEmbeddingMessage('category', (string) $categoryId, $force);
            $this->messageBus->dispatch($message);
        }
        
        $io->success(sprintf('Dispatched %d category embedding jobs to queue.', count($categoryIds)));
    }

    private function dispatchDestinationEmbeddings(SymfonyStyle $io, bool $force): void
    {
        $destinationRepo = $this->entityManager->getRepository(Destination::class);
        
        $qb = $destinationRepo->createQueryBuilder('d')
            ->select('d.id');
        if (!$force) {
            $qb->where('d.embedding IS NULL');
        }
        
        $destinationIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($destinationIds)) {
            $io->note('No destinations need embedding generation.');
            return;
        }

        $io->section('Dispatching Destination Embedding Jobs');
        $io->note(sprintf('Dispatching %d destination embedding jobs...', count($destinationIds)));
        
        foreach ($destinationIds as $destinationId) {
            $message = new GenerateEmbeddingMessage('destination', (string) $destinationId, $force);
            $this->messageBus->dispatch($message);
        }
        
        $io->success(sprintf('Dispatched %d destination embedding jobs to queue.', count($destinationIds)));
    }

    private function dispatchResortEmbeddings(SymfonyStyle $io, bool $force): void
    {
        $resortRepo = $this->entityManager->getRepository(Resort::class);
        
        $qb = $resortRepo->createQueryBuilder('r')
            ->select('r.id');
        if (!$force) {
            $qb->where('r.embedding IS NULL');
        }
        
        $resortIds = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($resortIds)) {
            $io->note('No resorts need embedding generation.');
            return;
        }

        $io->section('Dispatching Resort Embedding Jobs');
        $io->note(sprintf('Dispatching %d resort embedding jobs...', count($resortIds)));
        
        foreach ($resortIds as $resortId) {
            $message = new GenerateEmbeddingMessage('resort', (string) $resortId, $force);
            $this->messageBus->dispatch($message);
        }
        
        $io->success(sprintf('Dispatched %d resort embedding jobs to queue.', count($resortIds)));
    }
}