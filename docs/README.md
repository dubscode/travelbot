# TravelBot Documentation Hub

Welcome to the comprehensive documentation for TravelBot - an AI-powered travel assistant built with modern web technologies and deployed on AWS infrastructure.

## 📚 Documentation Overview

This documentation provides detailed guidance for developers, DevOps engineers, and stakeholders working with the TravelBot application. Each section covers different aspects of the system, from high-level architecture to operational procedures.

## 🧠 Travel Recommendation Engine

### [🧠 Travel Recommendation RAG System](./travel-recommendation-rag/README.md)
**The heart of TravelBot** - Comprehensive documentation of the intelligent recommendation engine.

**What you'll find:**
- Complete RAG system architecture and design patterns
- Multi-stage query processing and semantic vector search
- Multi-criteria ranking algorithm with personalization
- Progressive information gathering and conversation intelligence
- Visual diagrams and flow charts of all processes
- Complete API reference with examples

**Key Topics:**
- TravelQueryAnalyzer for NLP-powered query understanding
- VectorSearchService with pgvector semantic similarity
- SearchResultRanker with configurable multi-criteria scoring
- RAGContextBuilder for intelligent context aggregation
- TravelPreferenceTracker for personalization and learning
- Conversation flow state management and smart suggestions

## 🏗️ Architecture & Design

### [Architecture Overview](./architecture/README.md)
Comprehensive system architecture, technology stack, and design patterns.

**What you'll find:**
- System architecture diagrams and component relationships
- Technology stack breakdown (Symfony 7, AI integration, AWS)
- Data flow architecture and communication patterns
- Security architecture and scalability design
- Development and deployment architecture

**Key Topics:**
- Entity-Service-Controller pattern
- Real-time streaming with Server-Sent Events
- Database schema and relationships
- AWS infrastructure components
- Docker containerization strategy

## 💻 Development

### [Development Guide](./development/README.md)
Everything developers need to know to work with TravelBot.

**What you'll find:**
- Local development setup and prerequisites
- Database schema and entity relationships
- Complete API documentation with examples
- Frontend architecture (Twig, Turbo, Tailwind)
- Code quality practices and development workflow

**Key Topics:**
- Quick start guide with Docker Compose
- Entity definitions and relationships
- RESTful API endpoints and streaming endpoints
- JavaScript components and asset building
- Code quality and development best practices

## ☁️ Infrastructure

### [Infrastructure Documentation](./infrastructure/README.md)
Detailed AWS infrastructure setup using CDK and modern DevOps practices.

**What you'll find:**
- AWS CDK stack structure and configuration
- ECS Fargate deployment architecture
- Security groups, IAM roles, and permissions
- Logging setup and system monitoring
- SSL/TLS configuration and DNS management

**Key Topics:**
- CDK TypeScript infrastructure as code
- ECS service auto-scaling and health checks
- RDS PostgreSQL configuration and backups
- Application Load Balancer and Route53 setup
- GitHub OIDC authentication for CI/CD

## ✨ Features

### [Feature Documentation](./features/README.md)
Comprehensive overview of all TravelBot features and capabilities.

**What you'll find:**
- AI-powered chat interface with real-time streaming
- Travel recommendation engine and destination discovery
- User management and authentication system
- Conversation and message management
- Real-time features and responsive design

**Key Topics:**
- Server-Sent Events implementation
- Travel-specific AI knowledge and context management
- Multi-conversation support and history
- Accessibility features and responsive design
- Security features and data protection

### [Vector Search & AI-Powered Seeding](./pgvector-ai/README.md)
Deep dive into the semantic vector search system and intelligent data generation.

**What you'll find:**
- Complete pgvector + AWS Bedrock integration architecture
- AI-powered seed system generating realistic travel data
- Vector similarity search across destinations, resorts, and amenities
- Async embedding generation with Symfony Messenger
- Performance optimization with HNSW indexes

**Key Topics:**
- 1024-dimensional Titan V2 embeddings and cosine distance mathematics
- Database seeding with intelligent entity relationships
- PostgreSQL pgvector extension and custom Doctrine middleware
- Semantic search examples and query optimization
- Production monitoring and health checks

## 🔧 Operations

### [Operations Guide](./operations/README.md)
Production operations, monitoring, troubleshooting, and maintenance procedures.

**What you'll find:**
- Deployment procedures and rollback strategies
- Log management and system monitoring
- Database operations and backup procedures
- Security operations and incident response
- Performance optimization and cost management

**Key Topics:**
- Automated CI/CD deployment via GitHub Actions
- CloudWatch logging and monitoring
- Database backup and recovery procedures
- Emergency access and troubleshooting guides
- Maintenance windows and cost optimization

## 🚀 Quick Navigation

### For New Developers
1. Start with [🧠 Travel Recommendation RAG](./travel-recommendation-rag/README.md) to understand the core system
2. Follow [Architecture Overview](./architecture/README.md) to understand the overall system
3. Review [Development Guide](./development/README.md) for local setup
4. Check [Feature Documentation](./features/README.md) to understand capabilities

### For DevOps Engineers
1. Review [Infrastructure Documentation](./infrastructure/README.md) for AWS setup
2. Study [Operations Guide](./operations/README.md) for production procedures
3. Check [Development Guide](./development/README.md) for CI/CD integration
4. Understand [Travel Recommendation RAG](./travel-recommendation-rag/README.md) for system optimization

### For Stakeholders
1. Start with [🧠 Travel Recommendation RAG](./travel-recommendation-rag/README.md) for core innovation overview
2. Review [Feature Documentation](./features/README.md) for capabilities overview
3. Check [Architecture Overview](./architecture/README.md) for technical foundation
4. Study [Operations Guide](./operations/README.md) for operational maturity

### For AI/ML Engineers
1. Deep dive into [🧠 Travel Recommendation RAG](./travel-recommendation-rag/README.md) for the complete system
2. Review [Vector Search & AI Documentation](./pgvector-ai/README.md) for implementation details
3. Check [Architecture Overview](./architecture/README.md) for integration patterns

## 🏷️ Technology Stack Summary

| Layer | Technologies |
|-------|-------------|
| **Frontend** | Twig Templates, Hotwire Turbo, Tailwind CSS 4, Vanilla JavaScript |
| **Backend** | Symfony 7.3, PHP 8.3, Doctrine ORM, Server-Sent Events |
| **AI Integration** | Claude AI (Anthropic), AWS Bedrock, Custom Travel Prompting, Real-time Streaming |
| **Vector Search** | PostgreSQL pgvector, HNSW Indexes, 1024-dim Embeddings, Cosine Distance |
| **Database** | PostgreSQL 17, Doctrine Migrations, Connection Pooling, Custom Platform Middleware |
| **Async Processing** | Symfony Messenger, Database Transport, Retry Strategies, Queue Management |
| **Infrastructure** | AWS ECS Fargate, ALB, Route53, ECR, RDS, Secrets Manager |
| **DevOps** | GitHub Actions, AWS CDK, Docker, Rolling Deployment |
| **Monitoring** | CloudWatch, Application Logging, Health Checks |
| **Security** | HTTPS, IAM Roles, VPC, Security Groups, OIDC |

## 📋 System Capabilities

### Core Features
- ✅ **🧠 Intelligent RAG Recommendation Engine**: Multi-stage retrieval-augmented generation system
- ✅ **🔍 Semantic Vector Search**: Multi-entity AI-powered similarity search across destinations, resorts, and amenities
- ✅ **🎯 Smart Query Analysis**: NLP-powered extraction of travel parameters and intent understanding
- ✅ **📊 Multi-Criteria Ranking**: Intelligent scoring with personalization and preference learning
- ✅ **💬 Conversation Intelligence**: Progressive information gathering with context-aware responses
- ✅ **👤 Personalization Engine**: Behavioral learning and preference tracking across conversations
- ✅ **⚡ Real-time AI Chat**: Streaming conversations with travel expertise and context
- ✅ **🌍 Travel Recommendations**: Sophisticated destination and itinerary suggestions
- ✅ **🤖 Intelligent Data Seeding**: AI-generated realistic travel datasets with relationships
- ✅ **🔐 User Management**: Secure authentication and conversation history
- ✅ **📱 Responsive Design**: Mobile-first interface with accessibility support

### Technical Features
- ✅ **Microservices Architecture**: Modular, scalable design
- ✅ **Vector Database Integration**: PostgreSQL pgvector with HNSW indexes
- ✅ **Async Processing**: Symfony Messenger with database queuing
- ✅ **AI/ML Integration**: AWS Bedrock Titan embeddings with caching
- ✅ **Infrastructure as Code**: Full AWS CDK implementation
- ✅ **CI/CD Pipeline**: Automated building, deployment, and releases
- ✅ **Security First**: Comprehensive security at all layers
- ✅ **Monitoring & Observability**: Full application and infrastructure monitoring

### Operational Features
- ✅ **Rolling Deployment**: Zero-downtime deployments
- ✅ **Auto Scaling**: Responsive to demand changes
- ✅ **Backup & Recovery**: Automated backup with point-in-time recovery
- ✅ **Cost Optimization**: Resource right-sizing and cost monitoring

## 🔗 External Resources

### Related Repositories
- **Main Application**: [dubscode/travelbot](https://github.com/dubscode/travelbot)
- **Infrastructure**: CDK code in `/cdk` directory
- **CI/CD**: GitHub Actions workflows in `/.github/workflows`

### AWS Resources
- **Production URL**: [https://travelbot.tech](https://travelbot.tech)
- **AWS Console**: ECS Services, RDS Instances, CloudWatch Logs
- **ECR Repository**: Container images

### Development Tools
- **Local Development**: Docker Compose for full stack
- **Code Quality**: Manual review and best practices
- **Testing**: PHPUnit configured (no tests yet), manual testing, security scanning

## 📞 Support and Contribution

### Getting Help
- **Technical Issues**: Check [Operations Guide](./operations/README.md) troubleshooting section
- **Development Questions**: Review [Development Guide](./development/README.md)
- **Infrastructure Questions**: See [Infrastructure Documentation](./infrastructure/README.md)

### Contributing
- **Code Contributions**: Follow development workflow in [Development Guide](./development/README.md)
- **Documentation Updates**: Submit PRs with clear descriptions
- **Bug Reports**: Include environment details and reproduction steps

### Contacts
- **Development Team**: Primary contact for application features and bugs
- **DevOps Team**: Infrastructure and deployment related questions
- **Product Team**: Feature requests and business requirements

---

This documentation is actively maintained and updated with each release. For the most current information, always refer to the version in the main branch of the repository.

*Last updated: System architecture and comprehensive documentation created for production deployment.*