# Code Examples and Implementation Patterns

## Complete Embedding Generation Workflow

### 1. Entity Definition with Vector Support

```php
<?php
// src/Entity/Destination.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Destination
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $country = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $activities = null;

    // Vector embedding column (1024 dimensions for Titan V2)
    #[ORM\Column(type: 'vector', length: 1024, nullable: true)]
    private ?array $embedding = null;

    // Standard getters and setters...
    public function getEmbedding(): ?array
    {
        return $this->embedding;
    }

    public function setEmbedding(?array $embedding): static
    {
        $this->embedding = $embedding;
        return $this;
    }
}
```

### 2. Advanced Vector Search Service

```php
<?php
// src/Service/AdvancedVectorSearchService.php

namespace App\Service;

use App\Service\AI\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Psr\Log\LoggerInterface;

class AdvancedVectorSearchService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AIService $aiService,
        private LoggerInterface $logger
    ) {}

    /**
     * Multi-entity semantic search with geographic filtering
     */
    public function searchTravelRecommendations(
        string $query,
        ?array $coordinates = null,
        ?float $radiusKm = null,
        int $limit = 20,
        float $similarityThreshold = 0.6
    ): array {
        $this->logger->info('Performing multi-entity travel search', [
            'query' => $query,
            'coordinates' => $coordinates,
            'radius_km' => $radiusKm,
            'limit' => $limit,
            'threshold' => $similarityThreshold
        ]);

        // Generate embedding for search query
        $queryEmbedding = $this->aiService->generateEmbedding($query);
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        // Build geographic constraints
        $geoConstraint = '';
        $geoParams = [];
        
        if ($coordinates && $radiusKm) {
            $geoConstraint = "AND ST_DWithin(
                ST_Point(d.longitude, d.latitude)::geography,
                ST_Point(:search_lng, :search_lat)::geography,
                :radius_meters
            )";
            $geoParams = [
                'search_lng' => $coordinates[0],
                'search_lat' => $coordinates[1],
                'radius_meters' => $radiusKm * 1000
            ];
        }

        $sql = "
            WITH destination_matches AS (
                SELECT 
                    d.id,
                    d.name,
                    d.country,
                    d.city,
                    d.tags,
                    d.activities,
                    (1 - (d.embedding <=> :query_vector::vector)) as similarity
                FROM destination d
                WHERE d.embedding IS NOT NULL
                  AND (1 - (d.embedding <=> :query_vector::vector)) >= :threshold
                  {$geoConstraint}
            ),
            resort_matches AS (
                SELECT 
                    r.id,
                    r.name,
                    r.star_rating,
                    r.destination_id,
                    rc.name as category_name,
                    dm.similarity as dest_similarity
                FROM resort r
                JOIN resort_category rc ON r.category_id = rc.id
                JOIN destination_matches dm ON r.destination_id = dm.id
            ),
            amenity_matches AS (
                SELECT 
                    rm.id as resort_id,
                    array_agg(a.name ORDER BY a.name) as amenities,
                    count(a.id) as amenity_count
                FROM resort_matches rm
                JOIN resort_amenity ra ON rm.id = ra.resort_id
                JOIN amenity a ON ra.amenity_id = a.id
                WHERE a.embedding IS NOT NULL
                  AND (1 - (a.embedding <=> :query_vector::vector)) >= :amenity_threshold
                GROUP BY rm.id
            )
            SELECT 
                dm.name as destination_name,
                dm.country,
                dm.city,
                dm.tags as destination_tags,
                rm.name as resort_name,
                rm.star_rating,
                rm.category_name,
                COALESCE(am.amenities, ARRAY[]::text[]) as matching_amenities,
                COALESCE(am.amenity_count, 0) as amenity_match_count,
                dm.similarity as semantic_similarity,
                -- Composite scoring
                (dm.similarity * 0.6 + 
                 (rm.star_rating / 5.0) * 0.2 + 
                 (COALESCE(am.amenity_count, 0) / 10.0) * 0.2) as composite_score
            FROM destination_matches dm
            JOIN resort_matches rm ON dm.id = rm.destination_id
            LEFT JOIN amenity_matches am ON rm.id = am.resort_id
            ORDER BY composite_score DESC, dm.similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('destination_name', 'destination_name');
        $rsm->addScalarResult('country', 'country');
        $rsm->addScalarResult('city', 'city');
        $rsm->addScalarResult('destination_tags', 'destination_tags');
        $rsm->addScalarResult('resort_name', 'resort_name');
        $rsm->addScalarResult('star_rating', 'star_rating', 'integer');
        $rsm->addScalarResult('category_name', 'category_name');
        $rsm->addScalarResult('matching_amenities', 'matching_amenities');
        $rsm->addScalarResult('amenity_match_count', 'amenity_match_count', 'integer');
        $rsm->addScalarResult('semantic_similarity', 'semantic_similarity', 'float');
        $rsm->addScalarResult('composite_score', 'composite_score', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('query_vector', $vectorString);
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('amenity_threshold', $similarityThreshold - 0.1);
        $query->setParameter('limit', $limit);

        // Add geographic parameters if provided
        foreach ($geoParams as $param => $value) {
            $query->setParameter($param, $value);
        }

        $results = $query->getResult();
        
        $this->logger->info('Vector search completed', [
            'results_count' => count($results),
            'top_similarity' => $results[0]['semantic_similarity'] ?? 0
        ]);

        return $results;
    }

    /**
     * Contextual amenity search with resort compatibility
     */
    public function searchAmenitiesForResortType(
        string $amenityQuery,
        string $resortCategory,
        int $starRating,
        int $limit = 15
    ): array {
        $amenityEmbedding = $this->aiService->generateEmbedding($amenityQuery);
        $categoryEmbedding = $this->aiService->generateEmbedding($resortCategory);
        
        $amenityVector = '[' . implode(',', $amenityEmbedding) . ']';
        $categoryVector = '[' . implode(',', $categoryEmbedding) . ']';

        $sql = "
            WITH compatible_categories AS (
                SELECT rc.id
                FROM resort_category rc
                WHERE rc.embedding IS NOT NULL
                  AND (1 - (rc.embedding <=> :category_vector::vector)) >= 0.7
            ),
            star_appropriate_amenities AS (
                SELECT DISTINCT a.*
                FROM amenity a
                JOIN resort_amenity ra ON a.id = ra.amenity_id
                JOIN resort r ON ra.resort_id = r.id
                JOIN compatible_categories cc ON r.category_id = cc.id
                WHERE r.star_rating >= :min_star_rating
                  AND a.embedding IS NOT NULL
            )
            SELECT 
                saa.id,
                saa.name,
                saa.type,
                saa.description,
                (1 - (saa.embedding <=> :amenity_vector::vector)) as similarity,
                count(ra.resort_id) as resort_count
            FROM star_appropriate_amenities saa
            JOIN resort_amenity ra ON saa.id = ra.amenity_id
            WHERE (1 - (saa.embedding <=> :amenity_vector::vector)) >= 0.6
            GROUP BY saa.id, saa.name, saa.type, saa.description, saa.embedding
            ORDER BY similarity DESC, resort_count DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('similarity', 'similarity', 'float');
        $rsm->addScalarResult('resort_count', 'resort_count', 'integer');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('amenity_vector', $amenityVector);
        $query->setParameter('category_vector', $categoryVector);
        $query->setParameter('min_star_rating', max(1, $starRating - 1));
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }
}
```

### 3. Enhanced Embedding Generation with Context

```php
<?php
// src/MessageHandler/AdvancedEmbeddingHandler.php

namespace App\MessageHandler;

use App\Message\GenerateEmbeddingMessage;
use App\Service\AI\Providers\TitanEmbeddingsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AdvancedEmbeddingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TitanEmbeddingsService $embeddingsService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(GenerateEmbeddingMessage $message): void
    {
        $entityType = $message->getEntityType();
        $entityId = $message->getEntityId();
        $force = $message->isForce();

        try {
            $entity = $this->loadEntity($entityType, $entityId);
            
            if (!$entity) {
                throw new \RuntimeException("Entity not found: {$entityType}:{$entityId}");
            }

            // Skip if embedding exists and not forcing
            if (!$force && $this->hasValidEmbedding($entity)) {
                $this->logger->debug('Embedding already exists', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ]);
                return;
            }

            // Generate rich contextual text
            $embeddingText = $this->generateContextualEmbeddingText($entity, $entityType);
            
            if (empty($embeddingText)) {
                throw new \RuntimeException("No text generated for embedding");
            }

            // Generate embedding with retry logic
            $embedding = $this->generateEmbeddingWithRetry($embeddingText, 3);
            
            // Validate embedding dimensions
            if (count($embedding) !== 1024) {
                throw new \RuntimeException("Invalid embedding dimensions: " . count($embedding));
            }

            // Save with transaction
            $this->entityManager->beginTransaction();
            try {
                $entity->setEmbedding($embedding);
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
                $this->entityManager->commit();
                
                $this->logger->info('Successfully generated contextual embedding', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'text_length' => strlen($embeddingText),
                    'embedding_size' => count($embedding)
                ]);
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate embedding', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    private function generateContextualEmbeddingText(object $entity, string $entityType): string
    {
        return match ($entityType) {
            'destination' => $this->generateDestinationContextualText($entity),
            'resort' => $this->generateResortContextualText($entity),
            'amenity' => $this->generateAmenityContextualText($entity),
            'category' => $this->generateCategoryContextualText($entity),
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}")
        };
    }

    private function generateDestinationContextualText($destination): string
    {
        $parts = [];
        
        // Core information
        $parts[] = $destination->getName();
        $parts[] = "Located in {$destination->getCountry()}";
        
        if ($destination->getCity()) {
            $parts[] = "City: {$destination->getCity()}";
        }
        
        // Rich description
        if ($destination->getDescription()) {
            $parts[] = $destination->getDescription();
        }
        
        // Climate context for seasonal recommendations
        if ($destination->getClimate()) {
            $climate = is_array($destination->getClimate()) 
                ? implode(', ', $destination->getClimate())
                : $destination->getClimate();
            $parts[] = "Climate characteristics: {$climate}";
        }
        
        // Activity-based context
        if ($destination->getActivities()) {
            $activities = is_array($destination->getActivities()) 
                ? implode(', ', $destination->getActivities())
                : $destination->getActivities();
            $parts[] = "Popular activities include: {$activities}";
        }
        
        // Traveler profile tags
        if ($destination->getTags()) {
            $tags = is_array($destination->getTags()) 
                ? implode(', ', $destination->getTags())
                : $destination->getTags();
            $parts[] = "Perfect for travelers seeking: {$tags}";
        }
        
        // Cost context for budget planning
        if ($destination->getAverageCostPerDay()) {
            $parts[] = "Average daily cost: ${$destination->getAverageCostPerDay()}";
        }
        
        // Seasonal context
        if ($destination->getBestMonthsToVisit()) {
            $months = is_array($destination->getBestMonthsToVisit()) 
                ? implode(', ', $destination->getBestMonthsToVisit())
                : $destination->getBestMonthsToVisit();
            $parts[] = "Best time to visit: {$months}";
        }

        return implode('. ', array_filter($parts));
    }

    private function generateResortContextualText($resort): string
    {
        $parts = [];
        
        // Basic information
        $parts[] = $resort->getName();
        
        // Star rating context
        $starText = match($resort->getStarRating()) {
            1 => "budget-friendly accommodation",
            2 => "comfortable hotel",
            3 => "quality resort",
            4 => "luxury resort",
            5 => "ultra-luxury resort",
            default => "accommodation"
        };
        $parts[] = "A {$starText}";
        
        // Category context
        if ($resort->getCategory()) {
            $parts[] = "Category: {$resort->getCategory()->getName()}";
        }
        
        // Destination context
        if ($resort->getDestination()) {
            $parts[] = "Located in {$resort->getDestination()->getName()}, {$resort->getDestination()->getCountry()}";
        }
        
        // Size indicator
        if ($resort->getTotalRooms()) {
            $sizeText = match(true) {
                $resort->getTotalRooms() < 50 => "intimate boutique property",
                $resort->getTotalRooms() < 150 => "medium-sized resort",
                $resort->getTotalRooms() < 300 => "large resort",
                default => "expansive resort complex"
            };
            $parts[] = "A {$sizeText} with {$resort->getTotalRooms()} rooms";
        }
        
        // Detailed description
        if ($resort->getDescription()) {
            $parts[] = $resort->getDescription();
        }

        return implode('. ', array_filter($parts));
    }

    private function hasValidEmbedding(object $entity): bool
    {
        $embedding = $entity->getEmbedding();
        return $embedding !== null && is_array($embedding) && count($embedding) === 1024;
    }

    private function generateEmbeddingWithRetry(string $text, int $maxRetries): array
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->embeddingsService->generateEmbedding($text);
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning("Embedding generation attempt {$attempt} failed", [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt < $maxRetries) {
                    // Exponential backoff
                    sleep(pow(2, $attempt));
                }
            }
        }
        
        throw new \RuntimeException(
            "Failed to generate embedding after {$maxRetries} attempts",
            0,
            $lastException
        );
    }
}
```

### 4. Performance Monitoring and Health Checks

```php
<?php
// src/Command/VectorHealthCheckCommand.php

namespace App\Command;

use App\Service\VectorSearchService;
use App\Service\AI\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:vector-health-check',
    description: 'Comprehensive health check for vector search system'
)]
class VectorHealthCheckCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VectorSearchService $vectorSearchService,
        private AIService $aiService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vector Search System Health Check');

        $allPassed = true;

        // 1. Check pgvector extension
        $io->section('1. Checking pgvector Extension');
        if (!$this->checkPgvectorExtension($io)) {
            $allPassed = false;
        }

        // 2. Check vector indexes
        $io->section('2. Checking HNSW Indexes');
        if (!$this->checkVectorIndexes($io)) {
            $allPassed = false;
        }

        // 3. Check embedding coverage
        $io->section('3. Checking Embedding Coverage');
        if (!$this->checkEmbeddingCoverage($io)) {
            $allPassed = false;
        }

        // 4. Test embedding generation
        $io->section('4. Testing Embedding Generation');
        if (!$this->testEmbeddingGeneration($io)) {
            $allPassed = false;
        }

        // 5. Test vector search performance
        $io->section('5. Testing Vector Search Performance');
        if (!$this->testSearchPerformance($io)) {
            $allPassed = false;
        }

        // 6. Check semantic quality
        $io->section('6. Testing Semantic Quality');
        if (!$this->testSemanticQuality($io)) {
            $allPassed = false;
        }

        $io->newLine();
        if ($allPassed) {
            $io->success('All vector search health checks passed! 🎉');
            return Command::SUCCESS;
        } else {
            $io->error('Some health checks failed. Please review the issues above.');
            return Command::FAILURE;
        }
    }

    private function checkPgvectorExtension(SymfonyStyle $io): bool
    {
        try {
            $sql = "SELECT * FROM pg_extension WHERE extname = 'vector'";
            $result = $this->entityManager->getConnection()->fetchAssociative($sql);
            
            if ($result) {
                $io->success("✓ pgvector extension is installed (version: {$result['extversion']})");
                return true;
            } else {
                $io->error('✗ pgvector extension is not installed');
                return false;
            }
        } catch (\Exception $e) {
            $io->error("✗ Error checking pgvector extension: " . $e->getMessage());
            return false;
        }
    }

    private function checkVectorIndexes(SymfonyStyle $io): bool
    {
        $expectedIndexes = [
            'idx_amenity_embedding_hnsw' => 'amenity',
            'idx_resort_category_embedding_hnsw' => 'resort_category',
            'idx_destination_embedding_hnsw' => 'destination'
        ];

        $allIndexesExist = true;

        foreach ($expectedIndexes as $indexName => $tableName) {
            try {
                $sql = "
                    SELECT indexname, tablename 
                    FROM pg_indexes 
                    WHERE indexname = :index_name AND tablename = :table_name
                ";
                $result = $this->entityManager->getConnection()->fetchAssociative($sql, [
                    'index_name' => $indexName,
                    'table_name' => $tableName
                ]);

                if ($result) {
                    $io->text("✓ HNSW index {$indexName} exists on {$tableName}");
                } else {
                    $io->error("✗ HNSW index {$indexName} missing on {$tableName}");
                    $allIndexesExist = false;
                }
            } catch (\Exception $e) {
                $io->error("✗ Error checking index {$indexName}: " . $e->getMessage());
                $allIndexesExist = false;
            }
        }

        return $allIndexesExist;
    }

    private function checkEmbeddingCoverage(SymfonyStyle $io): bool
    {
        $entities = [
            'destination' => 'Destination',
            'amenity' => 'Amenity',
            'resort_category' => 'Resort Category'
        ];

        $allCoverageGood = true;
        $coverageData = [];

        foreach ($entities as $table => $displayName) {
            try {
                $totalSql = "SELECT COUNT(*) FROM {$table}";
                $embeddedSql = "SELECT COUNT(*) FROM {$table} WHERE embedding IS NOT NULL";
                
                $total = $this->entityManager->getConnection()->fetchOne($totalSql);
                $embedded = $this->entityManager->getConnection()->fetchOne($embeddedSql);
                
                $coverage = $total > 0 ? ($embedded / $total) * 100 : 0;
                $coverageData[] = [$displayName, $total, $embedded, number_format($coverage, 1) . '%'];
                
                if ($coverage < 80) {
                    $allCoverageGood = false;
                }
            } catch (\Exception $e) {
                $io->error("✗ Error checking coverage for {$displayName}: " . $e->getMessage());
                $allCoverageGood = false;
            }
        }

        $io->table(['Entity Type', 'Total', 'With Embeddings', 'Coverage'], $coverageData);

        if ($allCoverageGood) {
            $io->success('✓ Embedding coverage is good (>80% for all entity types)');
        } else {
            $io->warning('⚠ Some entity types have low embedding coverage (<80%)');
        }

        return $allCoverageGood;
    }

    private function testEmbeddingGeneration(SymfonyStyle $io): bool
    {
        try {
            $testTexts = [
                'luxury beach resort with spa',
                'family-friendly mountain destination',
                'business hotel with conference facilities'
            ];

            $startTime = microtime(true);
            
            foreach ($testTexts as $text) {
                $embedding = $this->aiService->generateEmbedding($text);
                
                if (!is_array($embedding) || count($embedding) !== 1024) {
                    $io->error("✗ Invalid embedding generated for: {$text}");
                    return false;
                }
            }
            
            $duration = (microtime(true) - $startTime) * 1000;
            $avgDuration = $duration / count($testTexts);
            
            $io->success("✓ Embedding generation test passed");
            $io->text("   Average generation time: " . number_format($avgDuration, 1) . "ms");
            
            return true;
        } catch (\Exception $e) {
            $io->error("✗ Embedding generation test failed: " . $e->getMessage());
            return false;
        }
    }

    private function testSearchPerformance(SymfonyStyle $io): bool
    {
        try {
            $queries = [
                'beach resort',
                'spa facilities',
                'mountain skiing'
            ];

            $performanceData = [];
            $allFast = true;

            foreach ($queries as $query) {
                $startTime = microtime(true);
                $results = $this->vectorSearchService->searchSimilarAmenities($query, 10, 0.6);
                $duration = (microtime(true) - $startTime) * 1000;
                
                $performanceData[] = [
                    $query,
                    count($results),
                    number_format($duration, 1) . 'ms'
                ];
                
                if ($duration > 100) { // Slow if > 100ms
                    $allFast = false;
                }
            }

            $io->table(['Query', 'Results', 'Duration'], $performanceData);

            if ($allFast) {
                $io->success('✓ Vector search performance is good (<100ms per query)');
            } else {
                $io->warning('⚠ Some vector searches are slow (>100ms)');
            }

            return $allFast;
        } catch (\Exception $e) {
            $io->error("✗ Search performance test failed: " . $e->getMessage());
            return false;
        }
    }

    private function testSemanticQuality(SymfonyStyle $io): bool
    {
        try {
            // Test semantic similarity expectations
            $testCases = [
                [
                    'query' => 'swimming pool',
                    'expected_high' => ['pool', 'swimming', 'water'],
                    'expected_low' => ['restaurant', 'business', 'meeting']
                ],
                [
                    'query' => 'spa wellness',
                    'expected_high' => ['massage', 'sauna', 'relaxation'],
                    'expected_low' => ['golf', 'skiing', 'nightclub']
                ]
            ];

            $semanticQualityGood = true;

            foreach ($testCases as $testCase) {
                $results = $this->vectorSearchService->searchSimilarAmenities(
                    $testCase['query'], 
                    20, 
                    0.3
                );

                // Check if expected high similarity terms appear in top results
                $topResults = array_slice($results, 0, 5);
                $topResultsText = strtolower(implode(' ', array_column($topResults, 'name')));

                $highMatches = 0;
                foreach ($testCase['expected_high'] as $term) {
                    if (str_contains($topResultsText, strtolower($term))) {
                        $highMatches++;
                    }
                }

                $expectedHighRatio = $highMatches / count($testCase['expected_high']);
                
                if ($expectedHighRatio < 0.5) {
                    $semanticQualityGood = false;
                    $io->error("✗ Poor semantic quality for query: {$testCase['query']}");
                } else {
                    $io->text("✓ Good semantic quality for query: {$testCase['query']}");
                }
            }

            if ($semanticQualityGood) {
                $io->success('✓ Semantic search quality is good');
            } else {
                $io->warning('⚠ Semantic search quality needs improvement');
            }

            return $semanticQualityGood;
        } catch (\Exception $e) {
            $io->error("✗ Semantic quality test failed: " . $e->getMessage());
            return false;
        }
    }
}
```

### 5. Advanced Query Builder for Complex Searches

```php
<?php
// src/Service/VectorQueryBuilder.php

namespace App\Service;

use App\Service\AI\AIService;
use Doctrine\ORM\EntityManagerInterface;

class VectorQueryBuilder
{
    private array $embeddings = [];
    private array $filters = [];
    private array $weights = [];
    private int $limit = 20;
    private float $threshold = 0.6;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AIService $aiService
    ) {}

    public function searchDestinations(string $query): self
    {
        $this->embeddings['destination'] = $this->aiService->generateEmbedding($query);
        return $this;
    }

    public function searchAmenities(string $query, float $weight = 1.0): self
    {
        $this->embeddings['amenity'] = $this->aiService->generateEmbedding($query);
        $this->weights['amenity'] = $weight;
        return $this;
    }

    public function searchCategories(string $query, float $weight = 1.0): self
    {
        $this->embeddings['category'] = $this->aiService->generateEmbedding($query);
        $this->weights['category'] = $weight;
        return $this;
    }

    public function inCountries(array $countries): self
    {
        $this->filters['countries'] = $countries;
        return $this;
    }

    public function withStarRating(int $minRating, ?int $maxRating = null): self
    {
        $this->filters['star_rating_min'] = $minRating;
        if ($maxRating) {
            $this->filters['star_rating_max'] = $maxRating;
        }
        return $this;
    }

    public function withinRadius(float $lat, float $lng, float $radiusKm): self
    {
        $this->filters['location'] = [$lat, $lng, $radiusKm];
        return $this;
    }

    public function withTags(array $tags): self
    {
        $this->filters['tags'] = $tags;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function threshold(float $threshold): self
    {
        $this->threshold = $threshold;
        return $this;
    }

    public function execute(): array
    {
        $sql = $this->buildComplexQuery();
        $params = $this->buildParameters();
        
        $rsm = $this->buildResultSetMapping();
        
        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        foreach ($params as $param => $value) {
            $query->setParameter($param, $value);
        }

        return $query->getResult();
    }

    private function buildComplexQuery(): string
    {
        $selects = [];
        $joins = [];
        $wheres = [];
        $orderByComponents = [];

        // Base destination query
        $selects[] = "d.id as destination_id";
        $selects[] = "d.name as destination_name";
        $selects[] = "d.country";
        $selects[] = "d.tags as destination_tags";

        // Destination similarity if queried
        if (isset($this->embeddings['destination'])) {
            $selects[] = "(1 - (d.embedding <=> :dest_vector::vector)) as dest_similarity";
            $wheres[] = "(1 - (d.embedding <=> :dest_vector::vector)) >= :threshold";
            $orderByComponents[] = "dest_similarity DESC";
        }

        // Resort information
        $selects[] = "r.id as resort_id";
        $selects[] = "r.name as resort_name";
        $selects[] = "r.star_rating";
        $selects[] = "rc.name as category_name";
        
        $joins[] = "JOIN resort r ON d.id = r.destination_id";
        $joins[] = "JOIN resort_category rc ON r.category_id = rc.id";

        // Category similarity if queried
        if (isset($this->embeddings['category'])) {
            $selects[] = "(1 - (rc.embedding <=> :cat_vector::vector)) as category_similarity";
            $wheres[] = "(1 - (rc.embedding <=> :cat_vector::vector)) >= :threshold";
            $orderByComponents[] = "category_similarity DESC";
        }

        // Amenity matching if queried
        if (isset($this->embeddings['amenity'])) {
            $joins[] = "LEFT JOIN LATERAL (
                SELECT 
                    array_agg(a.name ORDER BY (1 - (a.embedding <=> :amenity_vector::vector)) DESC) as matching_amenities,
                    avg(1 - (a.embedding <=> :amenity_vector::vector)) as avg_amenity_similarity,
                    count(*) as amenity_count
                FROM resort_amenity ra
                JOIN amenity a ON ra.amenity_id = a.id
                WHERE ra.resort_id = r.id 
                  AND a.embedding IS NOT NULL
                  AND (1 - (a.embedding <=> :amenity_vector::vector)) >= :amenity_threshold
            ) amenity_matches ON true";
            
            $selects[] = "COALESCE(amenity_matches.matching_amenities, ARRAY[]::text[]) as matching_amenities";
            $selects[] = "COALESCE(amenity_matches.avg_amenity_similarity, 0) as avg_amenity_similarity";
            $selects[] = "COALESCE(amenity_matches.amenity_count, 0) as amenity_match_count";
            
            $orderByComponents[] = "avg_amenity_similarity DESC";
        }

        // Apply filters
        $wheres = array_merge($wheres, $this->buildFilterConditions());

        // Build composite score if multiple embeddings
        if (count($this->embeddings) > 1) {
            $scoreComponents = [];
            
            if (isset($this->embeddings['destination'])) {
                $weight = $this->weights['destination'] ?? 1.0;
                $scoreComponents[] = "dest_similarity * {$weight}";
            }
            
            if (isset($this->embeddings['category'])) {
                $weight = $this->weights['category'] ?? 1.0;
                $scoreComponents[] = "category_similarity * {$weight}";
            }
            
            if (isset($this->embeddings['amenity'])) {
                $weight = $this->weights['amenity'] ?? 1.0;
                $scoreComponents[] = "avg_amenity_similarity * {$weight}";
            }
            
            $selects[] = "(" . implode(' + ', $scoreComponents) . ") / " . count($scoreComponents) . " as composite_score";
            $orderByComponents = ["composite_score DESC"];
        }

        // Build final query
        $sql = "SELECT " . implode(', ', $selects) . "
                FROM destination d " . implode(' ', $joins);
        
        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }
        
        if (!empty($orderByComponents)) {
            $sql .= " ORDER BY " . implode(', ', $orderByComponents);
        }
        
        $sql .= " LIMIT :limit";

        return $sql;
    }

    private function buildFilterConditions(): array
    {
        $conditions = [];

        if (isset($this->filters['countries'])) {
            $conditions[] = "d.country = ANY(:countries)";
        }

        if (isset($this->filters['star_rating_min'])) {
            $conditions[] = "r.star_rating >= :star_rating_min";
        }

        if (isset($this->filters['star_rating_max'])) {
            $conditions[] = "r.star_rating <= :star_rating_max";
        }

        if (isset($this->filters['location'])) {
            $conditions[] = "ST_DWithin(
                ST_Point(d.longitude, d.latitude)::geography,
                ST_Point(:search_lng, :search_lat)::geography,
                :radius_meters
            )";
        }

        if (isset($this->filters['tags'])) {
            $conditions[] = "d.tags ?| :tags";
        }

        return $conditions;
    }

    private function buildParameters(): array
    {
        $params = [
            'threshold' => $this->threshold,
            'limit' => $this->limit
        ];

        // Add embedding vectors
        foreach ($this->embeddings as $type => $embedding) {
            $vectorString = '[' . implode(',', $embedding) . ']';
            
            $paramName = match($type) {
                'destination' => 'dest_vector',
                'category' => 'cat_vector',
                'amenity' => 'amenity_vector'
            };
            
            $params[$paramName] = $vectorString;
        }

        // Add amenity threshold if amenity search is used
        if (isset($this->embeddings['amenity'])) {
            $params['amenity_threshold'] = $this->threshold - 0.1;
        }

        // Add filter parameters
        if (isset($this->filters['countries'])) {
            $params['countries'] = $this->filters['countries'];
        }

        if (isset($this->filters['star_rating_min'])) {
            $params['star_rating_min'] = $this->filters['star_rating_min'];
        }

        if (isset($this->filters['star_rating_max'])) {
            $params['star_rating_max'] = $this->filters['star_rating_max'];
        }

        if (isset($this->filters['location'])) {
            [$lat, $lng, $radiusKm] = $this->filters['location'];
            $params['search_lat'] = $lat;
            $params['search_lng'] = $lng;
            $params['radius_meters'] = $radiusKm * 1000;
        }

        if (isset($this->filters['tags'])) {
            $params['tags'] = $this->filters['tags'];
        }

        return $params;
    }

    private function buildResultSetMapping()
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('destination_id', 'destination_id');
        $rsm->addScalarResult('destination_name', 'destination_name');
        $rsm->addScalarResult('country', 'country');
        $rsm->addScalarResult('destination_tags', 'destination_tags');
        $rsm->addScalarResult('resort_id', 'resort_id');
        $rsm->addScalarResult('resort_name', 'resort_name');
        $rsm->addScalarResult('star_rating', 'star_rating', 'integer');
        $rsm->addScalarResult('category_name', 'category_name');

        // Add conditional result mappings based on what embeddings were used
        if (isset($this->embeddings['destination'])) {
            $rsm->addScalarResult('dest_similarity', 'dest_similarity', 'float');
        }

        if (isset($this->embeddings['category'])) {
            $rsm->addScalarResult('category_similarity', 'category_similarity', 'float');
        }

        if (isset($this->embeddings['amenity'])) {
            $rsm->addScalarResult('matching_amenities', 'matching_amenities');
            $rsm->addScalarResult('avg_amenity_similarity', 'avg_amenity_similarity', 'float');
            $rsm->addScalarResult('amenity_match_count', 'amenity_match_count', 'integer');
        }

        if (count($this->embeddings) > 1) {
            $rsm->addScalarResult('composite_score', 'composite_score', 'float');
        }

        return $rsm;
    }

    // Usage example methods
    public static function findLuxuryBeachResorts(): self
    {
        return (new self())
            ->searchDestinations('tropical beach paradise luxury')
            ->searchCategories('beach resort island resort', 1.2)
            ->searchAmenities('spa pool luxury amenities', 0.8)
            ->withStarRating(4)
            ->limit(15)
            ->threshold(0.7);
    }

    public static function findFamilyMountainDestinations(): self
    {
        return (new self())
            ->searchDestinations('family mountain skiing snow winter')
            ->searchCategories('mountain lodge ski resort', 1.0)
            ->searchAmenities('kids family activities playground', 1.5)
            ->withTags(['family-friendly', 'mountainous'])
            ->limit(20)
            ->threshold(0.6);
    }
}
```

These code examples demonstrate production-ready implementations of vector search functionality with proper error handling, performance optimization, and comprehensive testing capabilities.