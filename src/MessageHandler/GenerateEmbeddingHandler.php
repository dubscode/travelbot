<?php

namespace App\MessageHandler;

use App\Entity\Amenity;
use App\Entity\Destination;
use App\Entity\Resort;
use App\Entity\ResortCategory;
use App\Message\GenerateEmbeddingMessage;
use App\Service\AI\Providers\TitanEmbeddingsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateEmbeddingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TitanEmbeddingsService $embeddingsService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(GenerateEmbeddingMessage $message): void
    {
        $entityType = $message->getEntityType();
        $entityId = $message->getEntityId();
        $force = $message->isForce();

        $this->logger->info('Processing embedding generation', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'force' => $force
        ]);

        try {
            $entity = $this->loadEntity($entityType, $entityId);
            
            if (!$entity) {
                $this->logger->warning('Entity not found', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ]);
                return;
            }

            // Skip if embedding already exists and not forcing
            $existingEmbedding = $entity->getEmbedding();
            if (!$force && $existingEmbedding !== null && !empty($existingEmbedding)) {
                $this->logger->debug('Embedding already exists, skipping', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ]);
                return;
            }

            // Generate embedding text based on entity type
            $text = $this->generateEmbeddingText($entity, $entityType);
            
            if (empty($text)) {
                $this->logger->warning('No text to embed', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ]);
                return;
            }

            // Generate vector embedding
            $embedding = $this->embeddingsService->generateEmbedding($text);
            
            // Save to entity
            $entity->setEmbedding($embedding);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            $this->logger->info('Successfully generated embedding', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'embedding_size' => count($embedding)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate embedding', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    private function loadEntity(string $entityType, string $entityId): ?object
    {
        $repository = match ($entityType) {
            'amenity' => $this->entityManager->getRepository(Amenity::class),
            'category' => $this->entityManager->getRepository(ResortCategory::class),
            'destination' => $this->entityManager->getRepository(Destination::class),
            'resort' => $this->entityManager->getRepository(Resort::class),
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}")
        };

        return $repository->find($entityId);
    }

    private function generateEmbeddingText(object $entity, string $entityType): string
    {
        return match ($entityType) {
            'amenity' => $this->generateAmenityText($entity),
            'category' => $this->generateCategoryText($entity),
            'destination' => $this->generateDestinationText($entity),
            'resort' => $this->generateResortText($entity),
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}")
        };
    }

    private function generateAmenityText(Amenity $amenity): string
    {
        $parts = [];
        
        if ($amenity->getName()) {
            $parts[] = $amenity->getName();
        }
        
        if ($amenity->getType()) {
            $parts[] = "Type: {$amenity->getType()}";
        }
        
        if ($amenity->getDescription()) {
            $parts[] = $amenity->getDescription();
        }

        return implode('. ', $parts);
    }

    private function generateCategoryText(ResortCategory $category): string
    {
        $parts = [];
        
        if ($category->getName()) {
            $parts[] = $category->getName();
        }
        
        if ($category->getDescription()) {
            $parts[] = $category->getDescription();
        }

        return implode('. ', $parts);
    }

    private function generateDestinationText(Destination $destination): string
    {
        $parts = [];
        
        if ($destination->getName()) {
            $parts[] = $destination->getName();
        }
        
        if ($destination->getCountry()) {
            $parts[] = "Country: {$destination->getCountry()}";
        }
        
        if ($destination->getCity()) {
            $parts[] = "City: {$destination->getCity()}";
        }
        
        if ($destination->getDescription()) {
            $parts[] = $destination->getDescription();
        }
        
        // Add activities as context
        if ($destination->getActivities()) {
            $activities = is_array($destination->getActivities()) 
                ? implode(', ', $destination->getActivities())
                : $destination->getActivities();
            $parts[] = "Activities: {$activities}";
        }
        
        // Add tags as context
        if ($destination->getTags()) {
            $tags = is_array($destination->getTags()) 
                ? implode(', ', $destination->getTags())
                : $destination->getTags();
            $parts[] = "Tags: {$tags}";
        }

        return implode('. ', $parts);
    }

    private function generateResortText(Resort $resort): string
    {
        $parts = [];
        
        // Basic information
        if ($resort->getName()) {
            $parts[] = $resort->getName();
        }
        
        // Star rating context for quality level
        if ($resort->getStarRating()) {
            $starText = match($resort->getStarRating()) {
                1 => "budget-friendly accommodation",
                2 => "comfortable hotel",
                3 => "quality resort",
                4 => "luxury resort",
                5 => "ultra-luxury resort",
                default => "accommodation"
            };
            $parts[] = "A {$starText}";
        }
        
        // Category context for resort type
        if ($resort->getCategory()) {
            $parts[] = "Category: {$resort->getCategory()->getName()}";
        }
        
        // Location context for geography and culture
        if ($resort->getDestination()) {
            $location = $resort->getDestination()->getName();
            if ($resort->getDestination()->getCountry()) {
                $location .= ", {$resort->getDestination()->getCountry()}";
            }
            $parts[] = "Located in {$location}";
        }
        
        // Size context for accommodation scale
        if ($resort->getTotalRooms()) {
            $sizeText = match(true) {
                $resort->getTotalRooms() < 50 => "intimate boutique property",
                $resort->getTotalRooms() < 150 => "medium-sized resort",
                $resort->getTotalRooms() < 300 => "large resort",
                default => "expansive resort complex"
            };
            $parts[] = "A {$sizeText} with {$resort->getTotalRooms()} rooms";
        }
        
        // Detailed description for unique features
        if ($resort->getDescription()) {
            $parts[] = $resort->getDescription();
        }

        return implode('. ', array_filter($parts));
    }
}