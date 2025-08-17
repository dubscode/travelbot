# Enhanced Destination Data with AI-Powered Resort Search - Implementation Tasks

## Overview
Enhance the travel application with:
- 100+ AI-generated destinations
- Resort entities with amenities
- Vector search capabilities using pgvector
- AWS Bedrock Titan embeddings

## Phase 1: Setup & Configuration

### Task 1: Install pgvector Support
**Goal:** Add PostgreSQL vector extension support to Symfony/Doctrine

**Steps:**
1. Install the partitech/doctrine-pgvector package:
   ```bash
   composer require partitech/doctrine-pgvector
   ```
2. Verify installation in composer.json
3. Clear Symfony cache

**Files to create/modify:**
- `composer.json` (modified)

**Acceptance criteria:**
- Package installed successfully
- No dependency conflicts
- Cache cleared without errors

---

### Task 2: Enable pgvector Extension
**Goal:** Create database migration to enable pgvector in PostgreSQL

**Steps:**
1. Create new migration file:
   ```bash
   php bin/console make:migration
   ```
2. Add SQL to enable pgvector extension:
   ```sql
   CREATE EXTENSION IF NOT EXISTS vector;
   ```
3. Test migration on development database

**Files to create/modify:**
- `migrations/VersionXXXXXX_EnablePgvector.php` (new)

**Acceptance criteria:**
- Migration runs successfully
- pgvector extension enabled in database
- No SQL errors

---

### Task 3: Configure Doctrine Vector Support
**Goal:** Configure Doctrine to work with vector types and functions

**Steps:**
1. Update `config/packages/doctrine.yaml` to add:
   - Vector type mappings
   - DQL string functions for distance calculations
2. Add configuration for partitech/doctrine-pgvector
3. Test configuration loads without errors

**Files to create/modify:**
- `config/packages/doctrine.yaml` (modified)

**Example configuration:**
```yaml
doctrine:
    dbal:
        types:
            vector: Partitech\DoctrineVectorType\VectorType
        
        mapping_types:
            vector: vector
            
    orm:
        dql:
            string_functions:
                COSINE_DISTANCE: Partitech\DoctrineVectorType\Query\CosineDistance
                L2_DISTANCE: Partitech\DoctrineVectorType\Query\L2Distance
                INNER_PRODUCT: Partitech\DoctrineVectorType\Query\InnerProduct
```

**Acceptance criteria:**
- Doctrine loads without errors
- Vector types recognized
- DQL functions available

---

## Phase 2: Entity Creation

### Task 4: Create Amenity Entity
**Goal:** Create Amenity entity with vector embedding support

**Steps:**
1. Create entity using Symfony maker:
   ```bash
   php bin/console make:entity Amenity
   ```
2. Add fields:
   - `id` (UUID, primary key)
   - `name` (string, 255, not null)
   - `type` (string, 100, not null) - pool, restaurant, spa, etc.
   - `description` (text, nullable)
   - `embedding` (vector, 1024 dimensions)
   - `createdAt` (datetime)
   - `updatedAt` (datetime)
3. Add getter/setter methods
4. Add repository class

**Files to create/modify:**
- `src/Entity/Amenity.php` (new)
- `src/Repository/AmenityRepository.php` (new)

**Example entity structure:**
```php
#[ORM\Entity(repositoryClass: AmenityRepository::class)]
class Amenity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'vector', nullable: true)]
    private ?array $embedding = null;

    // ... other fields and methods
}
```

**Acceptance criteria:**
- Entity created successfully
- Vector field properly configured
- Repository class generated

---

### Task 5: Create ResortCategory Entity
**Goal:** Create categories for resorts with vector embeddings

**Steps:**
1. Create ResortCategory entity
2. Add fields:
   - `id` (UUID, primary key)
   - `name` (string, 100, not null) - "Beach Resort", "Mountain Lodge", etc.
   - `description` (text, nullable)
   - `embedding` (vector, 1024 dimensions)
   - `createdAt` (datetime)
   - `updatedAt` (datetime)
3. Add methods for vector operations

**Files to create/modify:**
- `src/Entity/ResortCategory.php` (new)
- `src/Repository/ResortCategoryRepository.php` (new)

**Acceptance criteria:**
- Entity created with vector support
- Repository methods for similarity search
- Proper relationships defined

---

### Task 6: Create Resort Entity
**Goal:** Create main Resort entity with all relationships

**Steps:**
1. Create Resort entity with fields:
   - `id` (UUID, primary key)
   - `name` (string, 255, not null)
   - `starRating` (integer, 1-5)
   - `totalRooms` (integer)
   - `description` (text, nullable)
   - `embedding` (vector, 1024 dimensions)
   - `createdAt` (datetime)
   - `updatedAt` (datetime)
2. Add relationships:
   - Many-to-one with Destination
   - Many-to-one with ResortCategory
   - Many-to-many with Amenity
3. Create join table for resort_amenity

**Files to create/modify:**
- `src/Entity/Resort.php` (new)
- `src/Repository/ResortRepository.php` (new)

**Example relationships:**
```php
#[ORM\ManyToOne(targetEntity: Destination::class)]
#[ORM\JoinColumn(nullable: false)]
private ?Destination $destination = null;

#[ORM\ManyToOne(targetEntity: ResortCategory::class)]
#[ORM\JoinColumn(nullable: false)]
private ?ResortCategory $category = null;

#[ORM\ManyToMany(targetEntity: Amenity::class)]
#[ORM\JoinTable(name: 'resort_amenity')]
private Collection $amenities;
```

**Acceptance criteria:**
- Resort entity with all fields
- Proper relationships configured
- Junction table for amenities

---

### Task 7: Update Destination Entity
**Goal:** Add relationship to resorts in existing Destination entity

**Steps:**
1. Add OneToMany relationship to Resort entity
2. Add getter method for resorts collection
3. Update existing tests if any

**Files to create/modify:**
- `src/Entity/Destination.php` (modified)

**Example addition:**
```php
#[ORM\OneToMany(mappedBy: 'destination', targetEntity: Resort::class)]
private Collection $resorts;

public function __construct()
{
    // existing code...
    $this->resorts = new ArrayCollection();
}
```

**Acceptance criteria:**
- Bidirectional relationship established
- Collection properly initialized
- No breaking changes to existing code

---

## Phase 3: Services & Generators

### Task 8: Create EmbeddingsService
**Goal:** Service to generate embeddings using AWS Bedrock Titan

**Steps:**
1. Create service class that uses existing AWS Bedrock client
2. Add method to generate embeddings for text
3. Add caching mechanism to avoid regenerating same embeddings
4. Configure service in Symfony container

**Files to create/modify:**
- `src/Service/EmbeddingsService.php` (new)
- `config/services.yaml` (modified)

**Example service structure:**
```php
class EmbeddingsService
{
    public function __construct(
        private BedrockRuntimeClient $bedrockClient,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}

    public function generateEmbedding(string $text): array
    {
        $cacheKey = 'embedding_' . md5($text);
        
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $result = $this->bedrockClient->invokeModel([
            'modelId' => 'amazon.titan-embed-text-v2:0',
            'body' => json_encode([
                'inputText' => $text,
                'dimensions' => 1024,
                'normalize' => true
            ])
        ]);

        $embedding = json_decode($result['body'], true)['embedding'];
        
        $this->cache->getItem($cacheKey)->set($embedding);
        
        return $embedding;
    }
}
```

**Acceptance criteria:**
- Service integrates with existing Bedrock client
- Caching implemented
- Error handling included
- Logging for debugging

---

### Task 9: Create DestinationGenerator
**Goal:** Generator class for creating diverse destination data

**Steps:**
1. Create generator with data pools for:
   - Countries and major cities
   - Climate types
   - Popular activities
   - Tags and descriptors
   - Realistic coordinate ranges
   - Cost variations by region
2. Implement weighted randomization
3. Ensure no duplicate destinations

**Files to create/modify:**
- `src/Generator/DestinationGenerator.php` (new)

**Data pools to include:**
- 50+ countries with major cities
- Climate types: tropical, temperate, desert, alpine, etc.
- Activities: beaches, hiking, museums, nightlife, etc.
- Tags: romantic, adventure, culture, luxury, budget, etc.
- Cost ranges: $20-200 per day based on region

**Example structure:**
```php
class DestinationGenerator
{
    private array $countries = [
        'France' => ['Paris', 'Nice', 'Lyon', 'Marseille'],
        'Japan' => ['Tokyo', 'Kyoto', 'Osaka', 'Hiroshima'],
        // ... more countries
    ];

    private array $climateTypes = [
        'tropical', 'temperate', 'mediterranean', 'continental'
    ];

    public function generate(int $count = 100): array
    {
        // Implementation to generate $count unique destinations
    }
}
```

**Acceptance criteria:**
- Generates 100+ unique destinations
- Realistic data combinations
- No duplicates
- Proper coordinate ranges

---

### Task 10: Create ResortGenerator
**Goal:** Generator for creating resorts linked to destinations

**Steps:**
1. Create generator with resort name patterns
2. Logic for star rating distribution (fewer 5-star resorts)
3. Room count logic based on star rating
4. Resort categories matched to destination types
5. Generate 5-20 resorts per destination

**Files to create/modify:**
- `src/Generator/ResortGenerator.php` (new)

**Features:**
- Resort name templates: "[Location] [Type]", "[Adjective] [Type] Resort"
- Star rating weights: 1-star (5%), 2-star (15%), 3-star (40%), 4-star (30%), 5-star (10%)
- Room counts: 1-star (20-50), 2-star (30-80), 3-star (50-150), 4-star (100-300), 5-star (50-200)
- Categories matched to destinations (beach destinations → beach resorts)

**Acceptance criteria:**
- Realistic resort distributions
- Proper category matching
- Varied star ratings and room counts
- 5-20 resorts per destination

---

### Task 11: Create AmenityGenerator
**Goal:** Generate comprehensive amenity database

**Steps:**
1. Create predefined amenity categories:
   - Recreation: pools, tennis courts, golf courses, spa
   - Dining: restaurants, bars, room service, buffet
   - Business: meeting rooms, business center, WiFi
   - Entertainment: casino, nightclub, live music, shows
   - Family: kids club, playground, babysitting, family rooms
   - Luxury: concierge, butler, private beach, yacht
2. Generate descriptions for each amenity
3. Assign amenities to resorts based on star rating and category

**Files to create/modify:**
- `src/Generator/AmenityGenerator.php` (new)

**Amenity distribution logic:**
- 1-2 star: 5-10 basic amenities
- 3 star: 10-20 amenities
- 4 star: 15-25 amenities  
- 5 star: 20-30+ premium amenities

**Acceptance criteria:**
- 80-100 unique amenities created
- Realistic descriptions
- Proper distribution by resort tier
- Category-appropriate amenities

---

## Phase 4: Database Migrations

### Task 12: Generate Entity Migrations
**Goal:** Create database migrations for all new entities

**Steps:**
1. Generate migrations for new entities:
   ```bash
   php bin/console make:migration
   ```
2. Review generated SQL
3. Test migrations on development database
4. Verify all relationships and constraints

**Files to create/modify:**
- Migration files for Amenity, ResortCategory, Resort tables
- Junction table for resort_amenity relationship

**Acceptance criteria:**
- All tables created successfully
- Foreign key constraints working
- Vector columns properly defined
- Junction table functional

---

### Task 13: Add Vector Indexes
**Goal:** Optimize vector similarity searches with HNSW indexes

**Steps:**
1. Create migration to add HNSW indexes on vector columns
2. Configure index parameters for optimal performance
3. Test index creation and query performance

**Example SQL:**
```sql
CREATE INDEX amenity_embedding_idx ON amenity 
USING hnsw (embedding vector_cosine_ops);

CREATE INDEX resort_category_embedding_idx ON resort_category 
USING hnsw (embedding vector_cosine_ops);

CREATE INDEX resort_embedding_idx ON resort 
USING hnsw (embedding vector_cosine_ops);
```

**Files to create/modify:**
- Migration file for vector indexes

**Acceptance criteria:**
- HNSW indexes created successfully
- Query performance improved
- Vector similarity searches optimized

---

## Phase 5: Console Commands

### Task 14: Update SeedDestinationsCommand
**Goal:** Enhance existing command to use DestinationGenerator

**Steps:**
1. Inject DestinationGenerator service
2. Replace hardcoded array with generator
3. Add options for count and clear existing data
4. Add progress bar for large datasets

**Files to create/modify:**
- `src/Command/SeedDestinationsCommand.php` (modified)

**New features:**
```bash
php bin/console app:seed-destinations --count=150 --clear
```

**Acceptance criteria:**
- Generates configurable number of destinations
- Progress bar for user feedback
- Clear option works correctly
- No duplicate destinations

---

### Task 15: Create SeedAmenitiesCommand
**Goal:** Command to seed amenity database

**Steps:**
1. Create command using Symfony maker
2. Use AmenityGenerator to create amenities
3. Generate embeddings for each amenity description
4. Batch insert for performance

**Files to create/modify:**
- `src/Command/SeedAmenitiesCommand.php` (new)

**Command signature:**
```bash
php bin/console app:seed-amenities [--clear]
```

**Acceptance criteria:**
- All amenities created with embeddings
- Batch processing for performance
- Clear existing data option
- Progress feedback

---

### Task 16: Create SeedResortsCommand
**Goal:** Command to generate resorts and link with amenities

**Steps:**
1. Create command to generate resorts for existing destinations
2. Use ResortGenerator for resort creation
3. Assign amenities based on resort category and star rating
4. Generate embeddings for resort descriptions

**Files to create/modify:**
- `src/Command/SeedResortsCommand.php` (new)

**Features:**
- Generate resorts for all destinations
- Assign appropriate amenities
- Create embeddings for descriptions
- Progress tracking

**Acceptance criteria:**
- 5-20 resorts per destination
- Realistic amenity assignments
- All embeddings generated
- Performance optimized with batching

---

### Task 17: Create GenerateEmbeddingsCommand
**Goal:** Command to generate/update embeddings for existing data

**Steps:**
1. Create command to process existing records without embeddings
2. Batch process to avoid memory issues
3. Update embeddings for modified descriptions
4. Resume capability for large datasets

**Files to create/modify:**
- `src/Command/GenerateEmbeddingsCommand.php` (new)

**Features:**
```bash
php bin/console app:generate-embeddings [--entity=Amenity] [--force] [--batch-size=50]
```

**Acceptance criteria:**
- Processes all entities with vector fields
- Batch processing for memory efficiency
- Resume capability
- Force update option

---

## Phase 6: Search & Query Services

### Task 18: Create VectorSearchService
**Goal:** Service for vector-based similarity searches

**Steps:**
1. Create service with methods for:
   - Finding similar amenities
   - Finding resorts by amenity similarity
   - Category-based searches
   - Multi-vector searches
2. Use pgvector distance functions
3. Implement result ranking and filtering

**Files to create/modify:**
- `src/Service/VectorSearchService.php` (new)

**Example methods:**
```php
class VectorSearchService
{
    public function findSimilarAmenities(string $query, int $limit = 10): array;
    
    public function findResortsByAmenities(array $amenityNames, int $limit = 20): array;
    
    public function findResortsByCategory(string $categoryName, int $limit = 20): array;
    
    public function semanticSearch(string $query, array $entities = ['amenity', 'resort']): array;
}
```

**Search capabilities:**
- Cosine similarity for semantic matching
- Combined vector and traditional filters
- Result ranking and scoring
- Multi-entity searches

**Acceptance criteria:**
- Fast similarity searches
- Accurate semantic matching
- Flexible filtering options
- Proper result ranking

---

## Phase 7: Testing & Validation

### Task 19: Test pgvector Installation
**Goal:** Verify pgvector is working correctly

**Steps:**
1. Create test script to verify vector operations
2. Test basic vector operations (insert, select, distance)
3. Verify HNSW indexes are being used
4. Performance test with sample data

**Files to create/modify:**
- `src/Command/TestVectorCommand.php` (new - temporary)

**Test cases:**
- Vector insertion and retrieval
- Distance calculations (cosine, L2, inner product)
- Index usage verification
- Performance benchmarks

**Acceptance criteria:**
- All vector operations work
- Indexes improve performance
- Distance calculations accurate
- No SQL errors

---

### Task 20: Integration Testing
**Goal:** Test complete system with real data

**Steps:**
1. Run all seeding commands in sequence
2. Verify data relationships are correct
3. Test vector searches with sample queries
4. Validate embedding generation

**Test sequence:**
```bash
php bin/console app:seed-destinations --count=100 --clear
php bin/console app:seed-amenities --clear
php bin/console app:seed-resorts --clear
php bin/console app:generate-embeddings
```

**Acceptance criteria:**
- All commands execute successfully
- Data relationships intact
- Vector searches return relevant results
- No performance issues

---

### Task 21: Performance Optimization
**Goal:** Optimize for production use

**Steps:**
1. Add database indexes for frequently queried fields
2. Optimize batch sizes for seeding commands
3. Implement connection pooling considerations
4. Add query result caching where appropriate

**Files to create/modify:**
- Additional migration for performance indexes
- Service optimizations

**Optimizations:**
- Index on resort.star_rating, resort.total_rooms
- Index on amenity.type
- Batch size tuning for embeddings
- Query result caching

**Acceptance criteria:**
- Fast query performance
- Efficient bulk operations
- Memory usage optimized
- Production-ready performance

---

### Task 22: Documentation & Cleanup
**Goal:** Document the new system and clean up

**Steps:**
1. Update README with new features
2. Document vector search capabilities
3. Add API documentation for search methods
4. Remove any temporary test files

**Files to create/modify:**
- `README.md` (updated)
- `docs/VECTOR_SEARCH.md` (new)
- Code comments and docblocks

**Documentation should include:**
- How to use vector search
- Available amenity categories
- Resort generation logic
- Performance considerations

**Acceptance criteria:**
- Complete documentation
- Code properly commented
- Examples provided
- Clean codebase

---

## Summary

This implementation will provide:
- **100+ diverse destinations** generated using AI techniques
- **Resort system** with 500+ resorts across various categories
- **Comprehensive amenities** with 80+ unique offerings
- **Vector search capabilities** for semantic matching
- **AWS Bedrock integration** for state-of-the-art embeddings
- **Scalable architecture** for future enhancements

The system will enable advanced features like:
- "Find resorts similar to luxury beach properties"
- "Show destinations with spa and wellness amenities"  
- "Recommend resorts based on activity preferences"
- Foundation for AI-powered travel recommendations