<?php

namespace App\Service;

use App\Entity\Amenity;
use App\Entity\Destination;
use App\Entity\Resort;
use App\Entity\ResortCategory;
use App\Service\AI\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class VectorSearchService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AIService $aiService
    ) {}

    /**
     * Search for similar amenities using vector similarity
     */
    public function searchSimilarAmenities(string $query, int $limit = 10, float $similarityThreshold = 0.7): array
    {
        // Generate embedding for the search query
        $queryEmbedding = $this->aiService->generateEmbedding($query);
        
        // Convert array to PostgreSQL vector format
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT 
                a.id,
                a.name,
                a.type,
                a.description,
                (1 - (a.embedding <=> :query_vector::vector)) as similarity
            FROM amenity a
            WHERE a.embedding IS NOT NULL
              AND (1 - (a.embedding <=> :query_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('query_vector', $vectorString);
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Search for similar resort categories using vector similarity
     */
    public function searchSimilarCategories(string $query, int $limit = 5, float $similarityThreshold = 0.7): array
    {
        // Generate embedding for the search query
        $queryEmbedding = $this->aiService->generateEmbedding($query);
        
        // Convert array to PostgreSQL vector format
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT 
                rc.id,
                rc.name,
                rc.description,
                (1 - (rc.embedding <=> :query_vector::vector)) as similarity
            FROM resort_category rc
            WHERE rc.embedding IS NOT NULL
              AND (1 - (rc.embedding <=> :query_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('query_vector', $vectorString);
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Search for similar destinations using vector similarity
     */
    public function searchSimilarDestinations(string $query, int $limit = 10, float $similarityThreshold = 0.7): array
    {
        // Generate embedding for the search query
        $queryEmbedding = $this->aiService->generateEmbedding($query);
        
        // Convert array to PostgreSQL vector format
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT 
                d.id,
                d.name,
                d.country,
                d.city,
                d.description,
                d.popularity_score,
                (1 - (d.embedding <=> :query_vector::vector)) as similarity
            FROM destination d
            WHERE d.embedding IS NOT NULL
              AND (1 - (d.embedding <=> :query_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('country', 'country');
        $rsm->addScalarResult('city', 'city');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('popularity_score', 'popularity_score', 'integer');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('query_vector', $vectorString);
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Search for similar resorts using vector similarity
     */
    public function searchSimilarResorts(string $query, int $limit = 20, float $similarityThreshold = 0.7): array
    {
        // Generate embedding for the search query
        $queryEmbedding = $this->aiService->generateEmbedding($query);
        
        // Convert array to PostgreSQL vector format
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT 
                r.id,
                r.name,
                r.star_rating,
                r.total_rooms,
                r.description,
                d.name as destination_name,
                d.country as destination_country,
                rc.name as category_name,
                (1 - (r.embedding <=> :query_vector::vector)) as similarity
            FROM resort r
            LEFT JOIN destination d ON r.destination_id = d.id
            LEFT JOIN resort_category rc ON r.category_id = rc.id
            WHERE r.embedding IS NOT NULL
              AND (1 - (r.embedding <=> :query_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('star_rating', 'star_rating', 'integer');
        $rsm->addScalarResult('total_rooms', 'total_rooms', 'integer');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('destination_name', 'destination_name');
        $rsm->addScalarResult('destination_country', 'destination_country');
        $rsm->addScalarResult('category_name', 'category_name');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('query_vector', $vectorString);
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Find amenities similar to a given amenity
     */
    public function findSimilarAmenities(Amenity $amenity, int $limit = 10, float $similarityThreshold = 0.8): array
    {
        if (!$amenity->getEmbedding()) {
            throw new \InvalidArgumentException('Amenity must have an embedding to find similar amenities');
        }

        $vectorString = '[' . implode(',', $amenity->getEmbedding()) . ']';

        $sql = "
            SELECT 
                a.id,
                a.name,
                a.type,
                a.description,
                (1 - (a.embedding <=> :amenity_vector::vector)) as similarity
            FROM amenity a
            WHERE a.embedding IS NOT NULL
              AND a.id != :amenity_id
              AND (1 - (a.embedding <=> :amenity_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('amenity_vector', $vectorString);
        $query->setParameter('amenity_id', $amenity->getId());
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Find categories similar to a given category
     */
    public function findSimilarCategories(ResortCategory $category, int $limit = 5, float $similarityThreshold = 0.8): array
    {
        if (!$category->getEmbedding()) {
            throw new \InvalidArgumentException('Category must have an embedding to find similar categories');
        }

        $vectorString = '[' . implode(',', $category->getEmbedding()) . ']';

        $sql = "
            SELECT 
                rc.id,
                rc.name,
                rc.description,
                (1 - (rc.embedding <=> :category_vector::vector)) as similarity
            FROM resort_category rc
            WHERE rc.embedding IS NOT NULL
              AND rc.id != :category_id
              AND (1 - (rc.embedding <=> :category_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('category_vector', $vectorString);
        $query->setParameter('category_id', $category->getId());
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Find destinations similar to a given destination
     */
    public function findSimilarDestinations(Destination $destination, int $limit = 10, float $similarityThreshold = 0.8): array
    {
        if (!$destination->getEmbedding()) {
            throw new \InvalidArgumentException('Destination must have an embedding to find similar destinations');
        }

        $vectorString = '[' . implode(',', $destination->getEmbedding()) . ']';

        $sql = "
            SELECT 
                d.id,
                d.name,
                d.country,
                d.city,
                d.description,
                d.popularity_score,
                (1 - (d.embedding <=> :destination_vector::vector)) as similarity
            FROM destination d
            WHERE d.embedding IS NOT NULL
              AND d.id != :destination_id
              AND (1 - (d.embedding <=> :destination_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('country', 'country');
        $rsm->addScalarResult('city', 'city');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('popularity_score', 'popularity_score', 'integer');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('destination_vector', $vectorString);
        $query->setParameter('destination_id', $destination->getId());
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Find resorts similar to a given resort
     */
    public function findSimilarResorts(Resort $resort, int $limit = 10, float $similarityThreshold = 0.8): array
    {
        if (!$resort->getEmbedding()) {
            throw new \InvalidArgumentException('Resort must have an embedding to find similar resorts');
        }

        $vectorString = '[' . implode(',', $resort->getEmbedding()) . ']';

        $sql = "
            SELECT 
                r.id,
                r.name,
                r.star_rating,
                r.total_rooms,
                r.description,
                d.name as destination_name,
                d.country as destination_country,
                rc.name as category_name,
                (1 - (r.embedding <=> :resort_vector::vector)) as similarity
            FROM resort r
            LEFT JOIN destination d ON r.destination_id = d.id
            LEFT JOIN resort_category rc ON r.category_id = rc.id
            WHERE r.embedding IS NOT NULL
              AND r.id != :resort_id
              AND (1 - (r.embedding <=> :resort_vector::vector)) >= :threshold
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('star_rating', 'star_rating', 'integer');
        $rsm->addScalarResult('total_rooms', 'total_rooms', 'integer');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('destination_name', 'destination_name');
        $rsm->addScalarResult('destination_country', 'destination_country');
        $rsm->addScalarResult('category_name', 'category_name');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('resort_vector', $vectorString);
        $query->setParameter('resort_id', $resort->getId());
        $query->setParameter('threshold', $similarityThreshold);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Get amenities by semantic type search
     */
    public function searchAmenitiesByType(string $typeQuery, int $limit = 20): array
    {
        // Generate embedding for the type query
        $queryEmbedding = $this->aiService->generateEmbedding($typeQuery);
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT 
                a.id,
                a.name,
                a.type,
                a.description,
                (1 - (a.embedding <=> :type_vector::vector)) as similarity
            FROM amenity a
            WHERE a.embedding IS NOT NULL
            ORDER BY similarity DESC
            LIMIT :limit
        ";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('description', 'description');
        $rsm->addScalarResult('similarity', 'similarity', 'float');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('type_vector', $vectorString);
        $query->setParameter('limit', $limit);

        return $query->getResult();
    }

    /**
     * Get embedding statistics for monitoring
     */
    public function getEmbeddingStats(): array
    {
        $amenityStats = $this->entityManager->createQuery('
            SELECT 
                COUNT(a.id) as total,
                COUNT(a.embedding) as with_embeddings
            FROM App\Entity\Amenity a
        ')->getSingleResult();

        $categoryStats = $this->entityManager->createQuery('
            SELECT 
                COUNT(c.id) as total,
                COUNT(c.embedding) as with_embeddings
            FROM App\Entity\ResortCategory c
        ')->getSingleResult();

        $destinationStats = $this->entityManager->createQuery('
            SELECT 
                COUNT(d.id) as total,
                COUNT(d.embedding) as with_embeddings
            FROM App\Entity\Destination d
        ')->getSingleResult();

        $resortStats = $this->entityManager->createQuery('
            SELECT 
                COUNT(r.id) as total,
                COUNT(r.embedding) as with_embeddings
            FROM App\Entity\Resort r
        ')->getSingleResult();

        return [
            'amenities' => [
                'total' => (int) $amenityStats['total'],
                'with_embeddings' => (int) $amenityStats['with_embeddings'],
                'coverage_percent' => $amenityStats['total'] > 0 
                    ? round(($amenityStats['with_embeddings'] / $amenityStats['total']) * 100, 2)
                    : 0
            ],
            'categories' => [
                'total' => (int) $categoryStats['total'],
                'with_embeddings' => (int) $categoryStats['with_embeddings'],
                'coverage_percent' => $categoryStats['total'] > 0 
                    ? round(($categoryStats['with_embeddings'] / $categoryStats['total']) * 100, 2)
                    : 0
            ],
            'destinations' => [
                'total' => (int) $destinationStats['total'],
                'with_embeddings' => (int) $destinationStats['with_embeddings'],
                'coverage_percent' => $destinationStats['total'] > 0 
                    ? round(($destinationStats['with_embeddings'] / $destinationStats['total']) * 100, 2)
                    : 0
            ],
            'resorts' => [
                'total' => (int) $resortStats['total'],
                'with_embeddings' => (int) $resortStats['with_embeddings'],
                'coverage_percent' => $resortStats['total'] > 0 
                    ? round(($resortStats['with_embeddings'] / $resortStats['total']) * 100, 2)
                    : 0
            ]
        ];
    }

    /**
     * Test vector similarity functionality
     */
    public function testVectorSimilarity(): array
    {
        try {
            // Test basic embedding generation
            $testEmbedding = $this->aiService->generateEmbedding('spa and wellness facilities');
            
            // Test amenity search
            $amenityResults = $this->searchSimilarAmenities('spa and relaxation', 3, 0.5);
            
            // Test category search  
            $categoryResults = $this->searchSimilarCategories('wellness resort', 2, 0.5);
            
            // Test destination search
            $destinationResults = $this->searchSimilarDestinations('tropical paradise beach', 3, 0.5);
            
            // Test resort search
            $resortResults = $this->searchSimilarResorts('luxury beachfront hotel', 3, 0.5);
            
            return [
                'embedding_dimensions' => count($testEmbedding),
                'amenity_results_count' => count($amenityResults),
                'category_results_count' => count($categoryResults),
                'destination_results_count' => count($destinationResults),
                'resort_results_count' => count($resortResults),
                'sample_amenity_results' => array_slice($amenityResults, 0, 2),
                'sample_category_results' => array_slice($categoryResults, 0, 2),
                'sample_destination_results' => array_slice($destinationResults, 0, 2),
                'sample_resort_results' => array_slice($resortResults, 0, 2),
                'stats' => $this->getEmbeddingStats()
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'stats' => $this->getEmbeddingStats()
            ];
        }
    }
}