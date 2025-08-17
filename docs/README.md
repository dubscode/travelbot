# TravelBot Documentation

## Core System Documentation

### 🧠 [Travel Recommendation RAG System](./travel-recommendation-rag/README.md)
The heart of TravelBot - A sophisticated Retrieval-Augmented Generation (RAG) system that combines:
- **TravelQueryAnalyzer** for natural language understanding
- **VectorSearchService** for semantic similarity search
- **SearchResultRanker** for multi-criteria result ranking
- **RAGContextBuilder** for context aggregation
- **TravelPreferenceTracker** for user personalization

### 🔍 [Vector Search & AI System](./pgvector-ai/README.md)
PostgreSQL pgvector implementation with AWS Bedrock integration:
- pgvector extension with HNSW indexes for fast similarity search
- AWS Bedrock Titan V2 embeddings (1024 dimensions)
- Semantic search across destinations, resorts, and amenities
- AI-powered data seeding system
- Async embedding generation with Symfony Messenger

## Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | Symfony 7.3, PHP 8.1+ (8.3 in Docker), Doctrine ORM |
| **AI** | Claude AI via AWS Bedrock, Titan V2 Embeddings |
| **Database** | PostgreSQL with pgvector extension (v15 local, Neon cloud in production) |
| **Frontend** | Twig, Hotwire Turbo, Tailwind CSS 4 |
| **Infrastructure** | AWS ECS Fargate, CloudWatch, Neon PostgreSQL |

## API Endpoints

The system provides several REST API endpoints documented in the RAG system documentation:
- `POST /chat/query-analysis` - Analyze travel queries
- `GET /chat/personalized-recommendations` - Get personalized recommendations
- `POST /chat/track-interaction` - Track user interactions
- `GET /chat/conversation-context/{id}` - Get conversation context
- `GET /chat/smart-suggestions/{id}` - Get contextual suggestions
- `GET /chat/stream/{id}` - Server-sent events for streaming responses