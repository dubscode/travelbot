# 🔌 Travel Recommendation RAG API Reference

> Complete API documentation for the Travel Recommendation RAG system endpoints and services.

## 📋 Overview

The Travel Recommendation RAG system exposes several API endpoints that enable intelligent query analysis, personalized recommendations, interaction tracking, and conversation management.

### Base URL
```
https://travelbot.tech/chat
```

### Authentication
All endpoints require user authentication via session cookies.

## 🔍 Query Analysis API

### Analyze Travel Query

Analyzes natural language travel queries to extract structured information.

**Endpoint:** `POST /chat/query-analysis`

**Purpose:** Extract travel dates, budget, preferences, and requirements from user messages.

**Request Body:**
```json
{
  "message": "I want a romantic beach vacation in Greece for my anniversary in June"
}
```

**Response:**
```json
{
  "success": true,
  "analysis": {
    "travel_dates": {
      "start_date": null,
      "end_date": null,
      "flexible": true,
      "season": "summer",
      "duration_days": null
    },
    "budget": {
      "min_per_day": null,
      "max_per_day": null,
      "total_budget": null,
      "currency": "USD",
      "budget_level": null
    },
    "destination_preferences": {
      "destination_type": ["beach"],
      "climate": ["mediterranean"],
      "specific_locations": ["Greece"],
      "avoid_locations": []
    },
    "traveler_info": {
      "group_size": null,
      "traveler_types": ["couple"],
      "ages": ["adult"],
      "special_needs": []
    },
    "activity_preferences": ["relaxation", "romantic"],
    "amenity_requirements": [],
    "accommodation_preferences": {
      "star_rating": null,
      "room_type": [],
      "property_type": []
    },
    "urgency": "flexible",
    "query_intent": "recommendation"
  },
  "suggestions": [
    {
      "type": "budget",
      "text": "What's your travel budget?",
      "action": "clarify_budget"
    },
    {
      "type": "dates",
      "text": "When are you planning to travel?",
      "action": "clarify_dates"
    }
  ]
}
```

**Error Response:**
```json
{
  "error": "Failed to analyze query"
}
```

## 🎯 Personalized Recommendations API

### Get Personalized Recommendations

Retrieves travel recommendations based on user's historical preferences and behavior.

**Endpoint:** `GET /chat/personalized-recommendations`

**Parameters:**
- `limit` (optional): Number of recommendations (default: 5, max: 10)

**Request:**
```bash
GET /chat/personalized-recommendations?limit=5
```

**Response:**
```json
{
  "success": true,
  "recommendations": {
    "destinations": [
      {
        "id": "dest-123",
        "name": "Santorini",
        "country": "Greece",
        "description": "Iconic romantic destination...",
        "similarity": 0.942
      }
    ],
    "based_on": "user_preferences",
    "search_term": "beach romantic spa"
  },
  "user_preferences_available": true
}
```

**Error Response:**
```json
{
  "error": "Failed to get personalized recommendations"
}
```

## 📊 Interaction Tracking API

### Track User Interaction

Records user interactions for preference learning and personalization.

**Endpoint:** `POST /chat/track-interaction`

**Request Body:**
```json
{
  "type": "destination_click",
  "data": {
    "destination_id": "dest-123",
    "destination_name": "Santorini",
    "country": "Greece"
  }
}
```

**Interaction Types:**

| Type | Purpose | Data Fields |
|------|---------|-------------|
| `destination_click` | User clicked on destination | `destination_id`, `destination_name`, `country` |
| `resort_view` | User viewed resort details | `resort_id`, `resort_category`, `star_rating` |
| `amenity_interest` | User showed interest in amenity | `amenity_name`, `amenity_type` |
| `booking_intent` | User expressed booking interest | `destination_type`, `budget_range` |

**Response:**
```json
{
  "success": true,
  "message": "Interaction tracked successfully"
}
```

## 💬 Conversation Context API

### Get Conversation Context

Retrieves conversation state, patterns, and suggested follow-ups.

**Endpoint:** `GET /chat/conversation-context/{conversationId}`

**Response:**
```json
{
  "success": true,
  "context": {
    "message_count": 5,
    "patterns": {
      "mentioned_destinations": ["Greece", "Italy"],
      "mentioned_activities": ["beach", "culture", "food"],
      "budget_references": ["mid-range", "not too expensive"],
      "preference_indicators": []
    },
    "follow_up_suggestions": [
      "Are there any specific amenities you're looking for?",
      "Would you like recommendations for similar destinations?"
    ],
    "conversation_stage": "preference_gathering"
  }
}
```

**Conversation Stages:**
- `initial_inquiry` - First 1-2 messages
- `preference_gathering` - Messages 3-5, collecting details
- `recommendation_refinement` - Messages 6-10, comparing options
- `action_planning` - 10+ messages, booking and planning

## 🧠 Smart Suggestions API

### Get Smart Suggestions

Generates contextual suggestions based on message analysis.

**Endpoint:** `GET /chat/smart-suggestions/{messageId}`

**Response:**
```json
{
  "success": true,
  "suggestions": [
    {
      "text": "Show me more beach destinations",
      "action": "search_similar_destinations",
      "params": {
        "type": "beach"
      }
    },
    {
      "text": "Find destinations great for relaxation",
      "action": "search_by_activity",
      "params": {
        "activity": "relaxation"
      }
    }
  ],
  "query_analysis": {
    "destination_preferences": {
      "destination_type": ["beach"]
    },
    "activity_preferences": ["relaxation"]
  }
}
```

## 🔄 Core RAG Services Integration

### TravelRecommenderService

The main service orchestrating the RAG pipeline.

**Key Methods:**

```php
// Generate recommendation with full RAG pipeline
public function generateRecommendation(
    string $userMessage,
    ?User $user = null,
    array $previousMessages = [],
    bool $preferFastResponse = false
): array

// Stream recommendation with RAG
public function streamRecommendation(
    string $userMessage,
    ?User $user = null,
    array $previousMessages = [],
    bool $preferFastResponse = false
): \Generator

// Get query analysis
public function getQueryAnalysis(string $userMessage): array

// Get personalized recommendations
public function getPersonalizedRecommendations(User $user, int $limit = 5): array

// Track user interaction
public function trackUserInteraction(User $user, string $interactionType, array $data = []): void
```

### Vector Search Integration

**Search Methods:**

```php
// Multi-entity search
VectorSearchService::searchSimilarDestinations(string $query, int $limit, float $threshold): array
VectorSearchService::searchSimilarResorts(string $query, int $limit, float $threshold): array
VectorSearchService::searchSimilarAmenities(string $query, int $limit, float $threshold): array

// Entity-to-entity similarity
VectorSearchService::findSimilarDestinations(Destination $destination, int $limit): array
```

## 📊 Ranking and Scoring

### Multi-Criteria Ranking

**Default Weights:**
```php
$weights = [
    'semantic_similarity' => 0.40,  // Vector similarity score
    'user_preferences' => 0.25,     // Historical preferences
    'popularity' => 0.15,           // General popularity
    'budget_match' => 0.10,         // Budget compatibility
    'temporal_relevance' => 0.05,   // Seasonal factors
    'availability' => 0.05          // Current availability
];
```

**Custom Ranking:**
```php
$rankedResults = $resultRanker->rankSearchResults(
    $searchResults,
    $queryAnalysis,
    $user,
    $customWeights  // Optional custom weights
);
```

### Score Explanation

**Get ranking explanation:**
```php
$explanation = $resultRanker->explainRanking($rankedResult);
```

**Response:**
```json
{
  "semantic_similarity": {
    "score": 0.942,
    "description": "How well this matches your search query",
    "impact": "Very Positive"
  },
  "user_preferences": {
    "score": 0.850,
    "description": "Alignment with your personal travel preferences",
    "impact": "Positive"
  },
  "composite_score": 0.871
}
```

## 🛠️ Configuration Options

### Search Thresholds

```php
$thresholds = [
    'destinations' => 0.6,  // 60% minimum similarity
    'resorts' => 0.6,       // 60% minimum similarity
    'amenities' => 0.5,     // 50% minimum similarity
    'fallback' => 0.4       // 40% for broad search
];
```

### Context Limits

```php
$limits = [
    'max_context_length' => 8000,      // Characters
    'max_destinations' => 8,           // Top destinations
    'max_resorts_per_dest' => 4,       // Resorts per destination
    'max_similar_destinations' => 4,   // Similar suggestions
];
```

## 🔄 Streaming Responses

### Server-Sent Events

**Endpoint:** `GET /chat/stream/{messageId}`

**Event Types:**

| Event | Purpose | Data |
|-------|---------|------|
| `ready` | Connection established | `{"type":"ready"}` |
| `start` | Streaming begins | `{"type":"start"}` |
| `token` | Content chunk | `{"type":"token","text":"..."}` |
| `complete` | Stream finished | `{"type":"complete","stopReason":"end_turn"}` |
| `error` | Error occurred | `{"type":"error","message":"..."}` |

**JavaScript Client Example:**
```javascript
const eventSource = new EventSource(`/chat/stream/${messageId}`);

eventSource.addEventListener('token', (event) => {
  const data = JSON.parse(event.data);
  appendToMessage(data.text);
});

eventSource.addEventListener('complete', (event) => {
  eventSource.close();
  finalizeMessage();
});
```

## 🚀 Performance Considerations

### Caching

- Embedding generation: 24 hours TTL (in TitanEmbeddingsService)
- Search results: No caching implemented
- User preferences: Stored in database

## 🔒 Security

### Current Implementation

- Session-based authentication required
- Input validation on endpoints
- User queries and interactions are stored in database
- No automatic data retention policies implemented

## 🧪 Testing Examples

### cURL Examples

**Query Analysis:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"message":"Beach vacation in Thailand with spa"}' \
  https://travelbot.tech/chat/query-analysis
```

**Track Interaction:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"type":"destination_click","data":{"destination_id":"123","destination_name":"Bali"}}' \
  https://travelbot.tech/chat/track-interaction
```

### Response Validation

All API responses include:
- Success/error status
- Structured data with consistent schema
- Helpful error messages
- Request correlation IDs

---

*This API reference provides complete technical documentation for integrating with the Travel Recommendation RAG system.*