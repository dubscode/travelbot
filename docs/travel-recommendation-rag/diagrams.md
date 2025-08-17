# 📊 Travel Recommendation RAG System Diagrams

> Visual documentation of the Travel Recommendation RAG system architecture, data flows, and processing pipelines.

## 🏗️ System Architecture Overview

### High-Level RAG Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           TRAVELBOT RAG SYSTEM                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────┐    ┌──────────────────┐    ┌─────────────────────────────┐ │
│  │ User Query  │───▶│ TravelQuery      │───▶│ Structured Query Analysis   │ │
│  │ Natural     │    │ Analyzer         │    │ • Dates, Budget, Prefs     │ │
│  │ Language    │    │ (Claude AI)      │    │ • Intent Classification    │ │
│  └─────────────┘    └──────────────────┘    └─────────────────────────────┘ │
│         │                                                    │               │
│         │                                                    ▼               │
│         │            ┌─────────────────────────────────────────────────────┐ │
│         │            │         VECTOR SEARCH SERVICE                       │ │
│         │            │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐   │ │
│         │            │  │Destinations │ │   Resorts   │ │  Amenities  │   │ │
│         │            │  │   Search    │ │   Search    │ │   Search    │   │ │
│         │            │  │(pgvector)   │ │ (pgvector)  │ │ (pgvector)  │   │ │
│         │            │  └─────────────┘ └─────────────┘ └─────────────┘   │ │
│         │            └─────────────────────────────────────────────────────┘ │
│         │                                    │                               │
│         │                                    ▼                               │
│         │            ┌─────────────────────────────────────────────────────┐ │
│         │            │         SEARCH RESULT RANKER                        │ │
│         │            │  Multi-Criteria Scoring Engine:                     │ │
│         │            │  • Semantic Similarity (40%)                       │ │
│         │            │  • User Preferences (25%)                          │ │
│         │            │  • Popularity Score (15%)                          │ │
│         │            │  • Budget Compatibility (10%)                      │ │
│         │            │  • Temporal Relevance (5%)                         │ │
│         │            │  • Availability (5%)                               │ │
│         │            └─────────────────────────────────────────────────────┘ │
│         │                                    │                               │
│         │                                    ▼                               │
│         │            ┌─────────────────────────────────────────────────────┐ │
│         │            │         RAG CONTEXT BUILDER                         │ │
│         │            │  • Aggregate ranked results                        │ │
│         │            │  • Build structured context                        │ │
│         │            │  • Add user profile & preferences                  │ │
│         │            │  • Include temporal considerations                 │ │
│         │            │  • Format for Claude consumption                   │ │
│         │            └─────────────────────────────────────────────────────┘ │
│         │                                    │                               │
│         │                                    ▼                               │
│         │            ┌─────────────────────────────────────────────────────┐ │
│         │            │              CLAUDE AI                              │ │
│         │            │  Rich Context → Personalized Response              │ │
│         │            │  • Natural language generation                     │ │
│         │            │  • Travel expertise integration                    │ │
│         │            │  • Contextual recommendations                      │ │
│         │            └─────────────────────────────────────────────────────┘ │
│         │                                    │                               │
│         └────────────────────────────────────┼───────────────────────────────┘
│                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐
│  │              PREFERENCE TRACKING SYSTEM                                 │
│  │                                          │                               │
│  │  ┌─────────────────┐                     ▼                               │
│  │  │ User            │    ┌─────────────────────────────────────────────┐  │
│  │  │ Interactions    │───▶│        Preference Learning Engine          │  │
│  │  │ • Clicks        │    │  • Track behavior patterns                 │  │
│  │  │ • Views         │    │  • Update preference weights               │  │
│  │  │ • Bookings      │    │  • Improve future recommendations          │  │
│  │  │ • Queries       │    │  • Conversation history analysis           │  │
│  │  └─────────────────┘    └─────────────────────────────────────────────┘  │
│  └─────────────────────────────────────────────────────────────────────────┘
└─────────────────────────────────────────────────────────────────────────────┘
```

## 🔄 Query Processing Flow

### Complete Request-Response Lifecycle

```
┌─── User Input ───┐
│ "I want a beach  │
│ vacation in      │      ┌─── TravelQueryAnalyzer ───┐
│ Greece with spa  │────▶ │ Claude AI Analysis:       │
│ for my           │      │ • Extract dates           │
│ anniversary"     │      │ • Identify preferences    │
└──────────────────┘      │ • Detect intent           │
                          │ • Generate follow-ups     │
                          └───────────┬───────────────┘
                                      │
                          ┌───────────▼───────────────┐
                          │ Structured Analysis:      │
                          │ {                         │
                          │   destination_type: beach │
                          │   location: Greece        │
                          │   amenities: spa          │
                          │   occasion: anniversary   │
                          │   traveler_type: couple   │
                          │ }                         │
                          └───────────┬───────────────┘
                                      │
              ┌───────────────────────┼───────────────────────┐
              │                       │                       │
              ▼                       ▼                       ▼
┌─────────────────────┐ ┌─────────────────────┐ ┌─────────────────────┐
│ Destination Search  │ │   Resort Search     │ │  Amenity Search     │
│ Vector Query:       │ │ Vector Query:       │ │ Vector Query:       │
│ "beach Greece       │ │ "romantic luxury    │ │ "spa wellness       │
│  romantic"          │ │  resort"            │ │  couples"           │
│                     │ │                     │ │                     │
│ Results:            │ │ Results:            │ │ Results:            │
│ • Santorini (94.2%) │ │ • Grace Hotel (92%) │ │ • Spa (96.7%)       │
│ • Mykonos (87.3%)   │ │ • Canaves Oia (89%) │ │ • Couples Suite(91%)│
│ • Crete (85.1%)     │ │ • Mystique (87%)    │ │ • Private Beach(88%)│
└─────────────────────┘ └─────────────────────┘ └─────────────────────┘
              │                       │                       │
              └───────────────────────┼───────────────────────┘
                                      │
                          ┌───────────▼───────────────┐
                          │   SearchResultRanker      │
                          │                           │
                          │ Santorini Score:          │
                          │ • Similarity: 0.942×0.40  │
                          │ • User Pref:  0.850×0.25  │
                          │ • Popularity: 0.920×0.15  │
                          │ • Budget:     0.600×0.10  │
                          │ • Temporal:   0.850×0.05  │
                          │ • Available:  0.800×0.05  │
                          │ ────────────────────────── │
                          │ Total Score: 87.1%        │
                          └───────────┬───────────────┘
                                      │
                          ┌───────────▼───────────────┐
                          │   RAGContextBuilder       │
                          │                           │
                          │ Building context:         │
                          │ • User profile            │
                          │ • Query analysis          │
                          │ • Top destinations        │
                          │ • Featured resorts        │
                          │ • Relevant amenities      │
                          │ • Seasonal factors        │
                          │ • Budget considerations   │
                          └───────────┬───────────────┘
                                      │
                          ┌───────────▼───────────────┐
                          │      Claude AI            │
                          │                           │
                          │ Rich Context →            │
                          │ Personalized Response:    │
                          │                           │
                          │ "For your anniversary     │
                          │ trip to Greece, I highly  │
                          │ recommend Santorini...    │
                          │                           │
                          │ Based on your preference  │
                          │ for spa amenities, the    │
                          │ Grace Hotel offers..."     │
                          └───────────┬───────────────┘
                                      │
                          ┌───────────▼───────────────┐
                          │    User Response          │
                          │ Natural language travel   │
                          │ recommendations with:     │
                          │ • Specific suggestions    │
                          │ • Reasoning               │
                          │ • Practical advice        │
                          │ • Follow-up questions     │
                          └───────────────────────────┘
```

## 🎯 Multi-Criteria Ranking Visualization

### Ranking Weight Distribution

```
┌─── Ranking Criteria Weights ───┐
│                                 │
│ Semantic Similarity    ████████████████ 40%
│ User Preferences      ██████████ 25%
│ Popularity Score      ██████ 15%
│ Budget Match          ████ 10%
│ Temporal Relevance    ██ 5%
│ Availability          ██ 5%
│                                 │
└─────────────────────────────────┘

┌─── Sample Destination Scoring ───┐
│                                   │
│ Santorini, Greece                 │
│ ├─ Semantic: 0.942 × 0.40 = 0.377│
│ ├─ User Pref: 0.850 × 0.25 = 0.213│
│ ├─ Popular: 0.920 × 0.15 = 0.138 │
│ ├─ Budget: 0.600 × 0.10 = 0.060  │
│ ├─ Temporal: 0.850 × 0.05 = 0.043│
│ └─ Available: 0.800 × 0.05 = 0.040│
│ ─────────────────────────────────  │
│ Final Score: 0.871 (87.1%)        │
│                                   │
│ Mykonos, Greece                   │
│ ├─ Semantic: 0.873 × 0.40 = 0.349│
│ ├─ User Pref: 0.780 × 0.25 = 0.195│
│ ├─ Popular: 0.850 × 0.15 = 0.128 │
│ ├─ Budget: 0.550 × 0.10 = 0.055  │
│ ├─ Temporal: 0.800 × 0.05 = 0.040│
│ └─ Available: 0.900 × 0.05 = 0.045│
│ ─────────────────────────────────  │
│ Final Score: 0.812 (81.2%)        │
└───────────────────────────────────┘
```

## 🧠 Conversation Flow State Machine

### Progressive Information Gathering

```
                    ┌─────────────────┐
                    │ Initial Inquiry │
                    │                 │
                    │ • Basic intent  │
                    │ • Broad request │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Query Analysis  │
                    │                 │
                    │ Missing info?   │
                    └────┬────────┬───┘
                         │        │
                    YES  │        │ NO
                         │        │
                ┌────────▼────────┐│
                │ Preference      ││
                │ Gathering       ││
                │                 ││
                │ • Ask follow-ups││
                │ • Collect data  ││
                │ • Guide user    ││
                └────────┬────────┘│
                         │         │
                ┌────────▼────────┐│
                │ Information     ││
                │ Complete?       ││
                └────┬────────┬───┘│
                     │        │    │
                YES  │        │ NO │
                     │        │    │
                     │        └────┘
                     │
            ┌────────▼────────┐
            │ Recommendation  │
            │ Refinement      │
            │                 │
            │ • Compare opts  │
            │ • Show details  │
            │ • Get feedback  │
            └────────┬────────┘
                     │
            ┌────────▼────────┐
            │ Action Planning │
            │                 │
            │ • Booking help  │
            │ • Itinerary     │
            │ • Practical tips│
            └─────────────────┘

```

### Conversation Stage Detection

```
┌─── Conversation Analysis ───┐
│                             │
│ Message Count: 1-2          │
│ Stage: Initial Inquiry      │
│ Actions:                    │
│ • Understand basic intent   │
│ • Ask clarifying questions  │
│                             │
├─────────────────────────────┤
│                             │
│ Message Count: 3-5          │
│ Stage: Preference Gathering │
│ Actions:                    │
│ • Collect missing details   │
│ • Guide toward completeness │
│                             │
├─────────────────────────────┤
│                             │
│ Message Count: 6-10         │
│ Stage: Recommendation       │
│ Actions:                    │
│ • Present options           │
│ • Compare alternatives      │
│ • Refine based on feedback  │
│                             │
├─────────────────────────────┤
│                             │
│ Message Count: 10+          │
│ Stage: Action Planning      │
│ Actions:                    │
│ • Provide booking guidance  │
│ • Suggest itinerary         │
│ • Share practical tips      │
└─────────────────────────────┘
```

## 🔍 Vector Search Pipeline

### Embedding Generation and Storage

```
┌─── Text Input ───┐
│ "Beach resort    │
│ with spa and     │      ┌─── AWS Bedrock ───┐
│ romantic         │────▶ │ Titan Text V2     │
│ atmosphere for   │      │ Embeddings        │
│ couples"         │      │ 1024 dimensions   │
└──────────────────┘      └─────────┬─────────┘
                                    │
                          ┌─────────▼─────────┐
                          │ Vector Array      │
                          │ [0.123, -0.456,  │
                          │  0.789, 0.234,   │
                          │  -0.567, ...]     │
                          │ (1024 dimensions) │
                          └─────────┬─────────┘
                                    │
                          ┌─────────▼─────────┐
                          │ PostgreSQL        │
                          │ pgvector Storage  │
                          │                   │
                          │ HNSW Index:       │
                          │ • m = 16          │
                          │ • ef_construction │
                          │   = 64            │
                          └───────────────────┘
```

### Parallel Search Execution

```
┌─── Query Vector ───┐
│ [0.123, -0.456, ...] │
└─────────┬───────────┘
          │
    ┌─────┼─────┐
    │     │     │
    ▼     ▼     ▼
┌──────┐┌──────┐┌──────┐
│Dest. ││Resort││Ameni.│
│Search││Search││Search│
│      ││      ││      │
│<=>   ││<=>   ││<=>   │
│0.6   ││0.6   ││0.5   │
│thold ││thold ││thold │
└──┬───┘└──┬───┘└──┬───┘
   │       │       │
   ▼       ▼       ▼
┌──────┐┌──────┐┌──────┐
│ Top  ││ Top  ││ Top  │
│ 12   ││ 20   ││ 15   │
│Result││Result││Result│
└──┬───┘└──┬───┘└──┬───┘
   │       │       │
   └───────┼───────┘
           │
    ┌──────▼──────┐
    │ Aggregated  │
    │ Results     │
    │ with        │
    │ Similarity  │
    │ Scores      │
    └─────────────┘
```

## 📊 Preference Learning System

### Interaction Tracking and Weight Updates

```
┌─── User Interactions ───┐
│                         │
│ Click Destination   ────┼──▶ Weight: 2.0
│ View Resort Details ────┼──▶ Weight: 1.5  
│ Check Amenities     ────┼──▶ Weight: 1.0
│ Booking Intent      ────┼──▶ Weight: 3.0
│ Query Patterns      ────┼──▶ Weight: 1.0
│                         │
└─────────┬───────────────┘
          │
    ┌─────▼─────┐
    │ Preference │
    │ Calculator │
    └─────┬─────┘
          │
    ┌─────▼─────┐
    │ Normalized │
    │ Weights    │
    │            │
    │ beach: 0.85│
    │ spa: 0.72  │
    │ luxury:0.68│
    │ couple:0.91│
    └─────┬─────┘
          │
    ┌─────▼─────┐
    │ Future    │
    │ Search    │
    │ Boosting  │
    └───────────┘
```

### Preference Categories Visualization

```
┌─── User Preference Profile ───┐
│                               │
│ Destination Types:            │
│ ████████████████████ Beach 85%│
│ ███████████ Mountain 45%      │
│ ████████████████ City 67%     │
│                               │
│ Climate:                      │
│ ████████████████████ Tropical │
│ ██████████ Temperate 42%      │
│ ████ Cold 18%                 │
│                               │
│ Activities:                   │
│ ███████████████████ Relaxation│
│ ████████ Adventure 35%        │
│ ██████████████ Culture 60%    │
│                               │
│ Accommodation:                │
│ █████████████████ Luxury 78%  │
│ ████████████ Boutique 55%     │
│ ██████████████████ Resort 82% │
│                               │
│ Budget History:               │
│ $150-250: ████████████        │
│ $250-400: ███████████████████ │
│ $400+:    ███████             │
└───────────────────────────────┘
```

## 🔄 Real-Time Context Building

### Dynamic Context Assembly

```
┌─── Input Components ───┐
│                        │
│ Query Analysis         │
│ ├─ Extracted dates     │
│ ├─ Budget range        │
│ ├─ Group size          │
│ └─ Preferences         │
│                        │
│ Search Results         │
│ ├─ Top destinations    │
│ ├─ Matching resorts    │
│ └─ Relevant amenities  │
│                        │
│ User Profile           │
│ ├─ Historical prefs    │
│ ├─ Previous trips      │
│ └─ Interaction data    │
│                        │
│ Temporal Context       │
│ ├─ Current season      │
│ ├─ Travel timing       │
│ └─ Availability        │
└────────┬───────────────┘
         │
    ┌────▼────┐
    │ Context │
    │ Builder │
    └────┬────┘
         │
┌────────▼────────┐
│ Structured      │
│ Claude Context: │
│                 │
│ USER PROFILE:   │
│ - Budget: $250  │
│ - Interests:    │
│   Beach, Spa    │
│                 │
│ QUERY ANALYSIS: │
│ - Destination:  │
│   Beach         │
│ - Requirements: │
│   Romantic      │
│                 │
│ TOP MATCHES:    │
│ • Santorini     │
│   (94.2% match) │
│ • Mykonos       │
│   (87.3% match) │
│                 │
│ SEASONAL INFO:  │
│ - Best time:    │
│   April-October │
│ - Current:      │
│   Peak season   │
└─────────────────┘
```

## 🚀 Performance Optimization Flow

### Caching and Optimization Strategy

```
┌─── Request Flow ───┐
│                    │
│ User Query         │
└─────────┬──────────┘
          │
    ┌─────▼─────┐
    │ Cache     │  ◀─── 15 min TTL
    │ Check     │
    └─────┬─────┘
          │
    ┌─────▼─────┐
    │ Cache     │
    │ Hit?      │
    └──┬────┬───┘
       │    │
   YES │    │ NO
       │    │
       │    ▼
       │ ┌─────────┐
       │ │ Vector  │
       │ │ Search  │
       │ │ Execute │
       │ └─────┬───┘
       │       │
       │       ▼
       │ ┌─────────┐
       │ │ Cache   │
       │ │ Store   │
       │ └─────┬───┘
       │       │
       └───────┘
               │
         ┌─────▼─────┐
         │ Return    │
         │ Results   │
         └───────────┘
```

---

*These diagrams provide comprehensive visual documentation of the Travel Recommendation RAG system's architecture, data flows, and operational processes.*