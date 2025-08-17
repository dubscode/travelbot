# ✈️ TravelBot

> AI-powered travel planning assistant that helps you discover destinations, plan itineraries, and get personalized travel recommendations through intelligent conversations.

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000?style=flat&logo=symfony&logoColor=white)](https://symfony.com)
[![AWS](https://img.shields.io/badge/AWS-ECS-FF9900?style=flat&logo=amazon-aws&logoColor=white)](https://aws.amazon.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat&logo=docker&logoColor=white)](https://docker.com)

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose
- PHP 8.3+ (for local development)
- Node.js & npm (for assets)

### Setup
```bash
# Clone and start with Docker
git clone <repository-url>
cd travelbot
docker-compose up -d

# Install dependencies and setup database
docker-compose exec php composer install
docker-compose exec php php bin/console doctrine:migrations:migrate
docker-compose exec php php bin/console app:seed-destinations

# Install and build frontend assets
npm install && npm run build

# Access the application
open http://localhost:8000
```

## ✨ Features

- 🤖 **AI-Powered Conversations** - Real-time streaming chat with travel expertise
- 🌍 **Destination Discovery** - Personalized destination recommendations
- 📝 **Trip Planning** - Multi-conversation travel planning sessions
- 🔐 **User Management** - Secure authentication and conversation history
- 📱 **Responsive Design** - Mobile-first interface with accessibility support
- ⚡ **Real-time Streaming** - Server-Sent Events for live AI responses

## 🧠 Intelligent Travel Recommendation Engine

**The heart of TravelBot** - A sophisticated RAG (Retrieval-Augmented Generation) system that delivers personalized travel recommendations through:

- **🔍 Semantic Vector Search** - Multi-stage search across destinations, resorts, and amenities using pgvector
- **🎯 Smart Query Analysis** - NLP-powered extraction of dates, budget, preferences, and requirements
- **📊 Multi-Criteria Ranking** - Intelligent scoring based on similarity, user preferences, popularity, and constraints
- **🔄 Progressive Information Gathering** - Dynamic follow-up questions to refine recommendations
- **👤 Personalization Engine** - Learns from interactions to improve future recommendations
- **🌐 Context-Aware Responses** - Temporal, seasonal, and conversation-stage awareness

📚 **[Complete RAG Documentation →](./docs/travel-recommendation-rag/README.md)**

## 🏗️ Architecture

**Backend:** Symfony 7.3 with PHP 8.3  
**Frontend:** Twig templates with Hotwire Turbo & Tailwind CSS  
**Database:** PostgreSQL 17 with Doctrine ORM  
**AI Integration:** Claude AI via AWS Bedrock  
**Infrastructure:** AWS ECS Fargate with CDK deployment  

📚 **[Complete Documentation →](./docs/README.md)**

## 🔧 Development

### Database Commands
```bash
# Generate migration after entity changes
php bin/console make:migration

# Check migration status
php bin/console doctrine:migrations:status

# Run pending migrations
php bin/console doctrine:migrations:migrate

# Seed destinations data
php bin/console app:seed-destinations --clear
```

### Asset Development
```bash
# Development build with watching
npm run dev

# Production build
npm run build
```

### Docker Commands
```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Execute commands in containers
docker-compose exec php php bin/console <command>
docker-compose exec php composer <command>
```

## 📖 Documentation

- **[🧠 Travel Recommendation RAG](./docs/travel-recommendation-rag/README.md)** - Core recommendation engine documentation
- **[Architecture Overview](./docs/architecture/README.md)** - System design and components
- **[Development Guide](./docs/development/README.md)** - Local setup and API documentation
- **[Infrastructure](./docs/infrastructure/README.md)** - AWS deployment with CDK
- **[Operations](./docs/operations/README.md)** - Monitoring and troubleshooting
- **[Features](./docs/features/README.md)** - Detailed feature documentation
- **[Vectors](./docs/pgvector-ai/README.md)** - Vector search & AI powered seeding

## 🔗 Live Demo

**Production:** [https://travelbot.tech](https://travelbot.tech)

---

*Built with modern web technologies for scalable, AI-powered travel assistance.*