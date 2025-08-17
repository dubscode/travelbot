# Technical Diagrams and Deep Dive

## Vector Search Query Flow

```mermaid
flowchart TD
    A[User Query: 'beach resorts with spa'] --> B[VectorSearchService]
    B --> C[Generate Query Embedding]
    C --> D[TitanEmbeddingsService]
    D --> E{Cache Hit?}
    E -->|Yes| F[Return Cached Embedding]
    E -->|No| G[Call AWS Bedrock Titan V2]
    G --> H[Generate 1024-dim Vector]
    H --> I[Cache Result 24h TTL]
    I --> F
    F --> J[Format Vector for PostgreSQL]
    J --> K[Execute Native SQL Query]
    K --> L[PostgreSQL pgvector Engine]
    L --> M[HNSW Index Lookup]
    M --> N[Cosine Distance Calculation]
    N --> O[Apply Similarity Threshold]
    O --> P[Sort by Similarity DESC]
    P --> Q[Return Top Results]
    Q --> R[Format Response Array]
    R --> S[Return to Application]
    
    subgraph "Embedding Generation"
        D
        E
        F
        G
        H
        I
    end
    
    subgraph "Vector Database Operations"
        L
        M
        N
        O
        P
    end
    
    style D fill:#e1f5fe
    style L fill:#f3e5f5
    style M fill:#fff3e0
```

## Entity Relationship Flow with Vector Connections

```mermaid
graph TB
    subgraph "Data Layer"
        D[Destination Entity]
        R[Resort Entity]
        RC[ResortCategory Entity]
        A[Amenity Entity]
    end
    
    subgraph "Vector Embeddings"
        DE[Destination Embedding<br/>1024-dim vector]
        RE[Resort Embedding<br/>1024-dim vector]
        RCE[Category Embedding<br/>1024-dim vector]
        AE[Amenity Embedding<br/>1024-dim vector]
    end
    
    subgraph "HNSW Indexes"
        DEI[idx_destination_embedding_hnsw]
        RCEI[idx_resort_category_embedding_hnsw]
        AEI[idx_amenity_embedding_hnsw]
    end
    
    subgraph "Semantic Search Queries"
        Q1["Find tropical destinations"]
        Q2["Search luxury resorts"]
        Q3["Locate spa amenities"]
        Q4["Beach resort categories"]
    end
    
    D --> DE
    R --> RE
    RC --> RCE
    A --> AE
    
    DE --> DEI
    RCE --> RCEI
    AE --> AEI
    
    Q1 --> DEI
    Q2 --> DEI
    Q3 --> AEI
    Q4 --> RCEI
    
    D -->|1:many| R
    RC -->|1:many| R
    R -->|many:many| A
    
    style DE fill:#e8f5e8
    style RE fill:#e8f5e8
    style RCE fill:#e8f5e8
    style AE fill:#e8f5e8
    style DEI fill:#fff3cd
    style RCEI fill:#fff3cd
    style AEI fill:#fff3cd
```

## Seed System Data Generation Pipeline

```mermaid
sequenceDiagram
    participant CLI as Command Line
    participant DC as DestinationCommand
    participant DG as DestinationGenerator
    participant RC as ResortCommand
    participant RG as ResortGenerator
    participant AC as AmenityCommand
    participant AG as AmenityGenerator
    participant EC as EmbeddingCommand
    participant MQ as Messenger Queue
    participant EH as EmbeddingHandler
    participant DB as PostgreSQL
    
    CLI->>DC: npm run db:seed
    DC->>DG: generateDestinations(count: 10)
    DG->>DG: Generate country/city combinations
    DG->>DG: Create climate and activity data
    DG->>DG: Generate geographic coordinates
    DG->>DB: INSERT destinations
    
    DC->>RC: Chain to resort seeding
    RC->>RG: generateForDestination(count: 5)
    RG->>RG: Determine destination type
    RG->>RG: Select appropriate categories
    RG->>RG: Generate star rating distribution
    RG->>DB: INSERT resorts and categories
    
    RC->>AC: Chain to amenity seeding
    AC->>AG: generateAmenities()
    AG->>AG: Create 117 base amenities
    AG->>AG: Assign amenities to resorts by rating
    AG->>DB: INSERT amenities and relationships
    
    AC->>EC: Chain to embedding generation
    EC->>MQ: Dispatch 142 embedding jobs
    
    loop For each entity
        MQ->>EH: Process GenerateEmbeddingMessage
        EH->>EH: Load entity and generate text
        EH->>EH: Call TitanEmbeddingsService
        EH->>DB: UPDATE entity.embedding
    end
    
    Note over CLI,DB: Complete seed process with 10 destinations,<br/>50 resorts, 117 amenities, all with embeddings
```

## Vector Similarity Mathematics

### Cosine Distance Calculation

```mermaid
graph LR
    A[Vector A<br/>1024 dimensions] --> C[Dot Product<br/>A • B]
    B[Vector B<br/>1024 dimensions] --> C
    
    A --> D[Magnitude ||A||<br/>√(a₁² + a₂² + ... + a₁₀₂₄²)]
    B --> E[Magnitude ||B||<br/>√(b₁² + b₂² + ... + b₁₀₂₄²)]
    
    C --> F[Cosine Similarity<br/>(A • B) / (||A|| × ||B||)]
    D --> F
    E --> F
    
    F --> G[Cosine Distance<br/>1 - Cosine Similarity]
    
    G --> H[PostgreSQL Query<br/>1 - (embedding <=> query::vector)]
    
    style F fill:#e1f5fe
    style G fill:#f3e5f5
    style H fill:#fff3e0
```

### Mathematical Properties

**Vector Space Properties:**
- **Dimension**: 1024 (Titan V2 embedding size)
- **Range**: Each dimension typically [-1, 1] after normalization
- **Distance Metric**: Cosine distance ∈ [0, 1]
- **Similarity Interpretation**: 
  - 0.9-1.0: Nearly identical semantic meaning
  - 0.7-0.9: Strong semantic similarity
  - 0.5-0.7: Moderate similarity
  - 0.3-0.5: Weak similarity
  - 0.0-0.3: Little to no similarity

**HNSW Algorithm Benefits:**
- **Complexity**: O(log N) average search time
- **Accuracy**: 95%+ recall for top-k results
- **Memory**: ~1.5x storage overhead
- **Parallelization**: Inherently parallel search

## Middleware Architecture for Index Management

```mermaid
classDiagram
    class PostgreSQLPlatformMiddleware {
        +wrap(Driver): Driver
    }
    
    class PostgreSQLPlatformDriver {
        -wrapped: Driver
        +getDatabasePlatform(): PostgreSQLPlatform
        +createDatabasePlatformForVersion(): PostgreSQLPlatform
    }
    
    class PostgreSQLPlatform {
        +createSchemaManager(): PostgreSQLSchemaManager
    }
    
    class PostgreSQLSchemaManager {
        -IGNORED_INDEXES: array
        +_getPortableTableIndexesList(): array
        -filterIgnoredIndexes(): array
    }
    
    class DoctrineORM {
        +schema:validate
        +schema:update
    }
    
    PostgreSQLPlatformMiddleware --> PostgreSQLPlatformDriver
    PostgreSQLPlatformDriver --> PostgreSQLPlatform
    PostgreSQLPlatform --> PostgreSQLSchemaManager
    DoctrineORM --> PostgreSQLSchemaManager
    
    note for PostgreSQLSchemaManager "Filters out HNSW indexes:\n- idx_amenity_embedding_hnsw\n- idx_resort_category_embedding_hnsw\n- idx_destination_embedding_hnsw"
```

## Async Embedding Processing Architecture

```mermaid
graph TB
    subgraph "Embedding Generation Command"
        C[GenerateEmbeddingsCommand]
        C --> M1[Dispatch Amenity Messages]
        C --> M2[Dispatch Category Messages]
        C --> M3[Dispatch Destination Messages]
    end
    
    subgraph "Symfony Messenger"
        M1 --> Q[embeddings Queue]
        M2 --> Q
        M3 --> Q
        Q --> W1[Worker Process 1]
        Q --> W2[Worker Process 2]
        Q --> W3[Worker Process N]
    end
    
    subgraph "Message Processing"
        W1 --> H[GenerateEmbeddingHandler]
        W2 --> H
        W3 --> H
        H --> L[Load Entity]
        L --> T[Generate Text]
        T --> E[Call TitanEmbeddings]
        E --> S[Save Embedding]
    end
    
    subgraph "External Services"
        E --> AWS[AWS Bedrock Titan V2]
        E --> CACHE[Symfony Cache]
    end
    
    subgraph "Persistence"
        S --> DB[(PostgreSQL)]
    end
    
    subgraph "Error Handling"
        H --> R[Retry Strategy]
        R --> F[Failed Queue]
    end
    
    style Q fill:#e1f5fe
    style AWS fill:#ff9800
    style CACHE fill:#4caf50
    style DB fill:#2196f3
    style F fill:#f44336
```

## Performance Characteristics

### Query Performance by Dataset Size

```mermaid
xychart-beta
    title "Vector Search Performance"
    x-axis [1K, 5K, 10K, 25K, 50K, 100K]
    y-axis "Query Time (ms)" 0 --> 200
    line [2, 3, 5, 12, 25, 45]
```

### Memory Usage During Embedding Generation

```mermaid
xychart-beta
    title "Memory Usage During Batch Processing"
    x-axis [0, 50, 100, 150, 200, 250]
    y-axis "Memory (MB)" 0 --> 500
    line [100, 120, 180, 220, 280, 200]
```

## Database Schema with Vector Columns

```sql
-- Core entity with vector embedding
CREATE TABLE destination (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    country VARCHAR(100) NOT NULL,
    city VARCHAR(100),
    description TEXT,
    tags JSONB,
    climate JSONB,
    average_cost_per_day VARCHAR(20),
    activities JSONB,
    best_months_to_visit JSONB,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    image_url TEXT,
    popularity_score INTEGER DEFAULT 5,
    embedding vector(1024), -- pgvector column
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- HNSW index for fast similarity search
CREATE INDEX idx_destination_embedding_hnsw 
ON destination USING hnsw (embedding vector_cosine_ops);

-- GIN index for JSONB columns
CREATE INDEX idx_destination_tags ON destination USING gin (tags);
CREATE INDEX idx_destination_activities ON destination USING gin (activities);

-- Geographic indexes
CREATE INDEX idx_destination_location ON destination (latitude, longitude);
CREATE INDEX idx_destination_country ON destination (country);
```

## Vector Search Query Examples

### Basic Similarity Search
```sql
-- Find destinations similar to "tropical beach paradise"
SELECT 
    d.name,
    d.country,
    d.tags,
    (1 - (d.embedding <=> '[0.1,0.2,0.3,...]'::vector)) as similarity
FROM destination d
WHERE d.embedding IS NOT NULL
  AND (1 - (d.embedding <=> '[0.1,0.2,0.3,...]'::vector)) >= 0.7
ORDER BY similarity DESC
LIMIT 10;
```

### Complex Multi-Entity Search
```sql
-- Find beach resorts with specific amenities
WITH similar_categories AS (
    SELECT rc.id, rc.name,
           (1 - (rc.embedding <=> :beach_embedding::vector)) as cat_similarity
    FROM resort_category rc
    WHERE rc.embedding IS NOT NULL
      AND (1 - (rc.embedding <=> :beach_embedding::vector)) >= 0.7
), similar_amenities AS (
    SELECT a.id, a.name,
           (1 - (a.embedding <=> :spa_embedding::vector)) as amenity_similarity
    FROM amenity a
    WHERE a.embedding IS NOT NULL
      AND (1 - (a.embedding <=> :spa_embedding::vector)) >= 0.6
)
SELECT DISTINCT
    r.id,
    r.name,
    r.star_rating,
    d.name as destination,
    sc.name as category,
    array_agg(sa.name) as matching_amenities,
    avg(sc.cat_similarity) as category_match,
    avg(sa.amenity_similarity) as amenity_match
FROM resort r
JOIN destination d ON r.destination_id = d.id
JOIN similar_categories sc ON r.category_id = sc.id
JOIN resort_amenity ra ON r.id = ra.resort_id
JOIN similar_amenities sa ON ra.amenity_id = sa.id
GROUP BY r.id, r.name, r.star_rating, d.name, sc.name
HAVING count(sa.id) >= 2  -- At least 2 matching amenities
ORDER BY category_match DESC, amenity_match DESC, r.star_rating DESC
LIMIT 15;
```

### Geographic + Semantic Search
```sql
-- Find family destinations in Europe with high similarity
SELECT 
    d.name,
    d.country,
    d.city,
    d.tags,
    ST_Distance(
        ST_Point(d.longitude, d.latitude)::geography,
        ST_Point(2.3522, 48.8566)::geography  -- Distance from Paris
    ) / 1000 as distance_km,
    (1 - (d.embedding <=> :family_embedding::vector)) as similarity,
    CASE 
        WHEN d.tags @> '["family-friendly"]'::jsonb THEN 1.0
        ELSE 0.0 
    END as tag_bonus
FROM destination d
WHERE d.embedding IS NOT NULL
  AND d.latitude BETWEEN 35.0 AND 70.0  -- Europe bounds
  AND d.longitude BETWEEN -10.0 AND 40.0
  AND (
      (1 - (d.embedding <=> :family_embedding::vector)) >= 0.6
      OR d.tags @> '["family-friendly"]'::jsonb
  )
ORDER BY 
    tag_bonus DESC,
    similarity DESC,
    distance_km ASC
LIMIT 20;
```

This technical deep-dive provides the mathematical foundation and implementation details necessary to understand and extend the vector search system.