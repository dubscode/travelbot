# TravelBot Documentation Hub

Welcome to the comprehensive documentation for TravelBot - an AI-powered travel assistant built with modern web technologies and deployed on AWS infrastructure.

## 📚 Documentation Overview

This documentation provides detailed guidance for developers, DevOps engineers, and stakeholders working with the TravelBot application. Each section covers different aspects of the system, from high-level architecture to operational procedures.

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
- Monitoring, logging, and alerting setup
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

## 🔧 Operations

### [Operations Guide](./operations/README.md)
Production operations, monitoring, troubleshooting, and maintenance procedures.

**What you'll find:**
- Deployment procedures and rollback strategies
- Monitoring, alerting, and log management
- Database operations and backup procedures
- Security operations and incident response
- Performance optimization and cost management

**Key Topics:**
- Automated CI/CD deployment via GitHub Actions
- CloudWatch monitoring and alerting
- Database backup and recovery procedures
- Emergency access and troubleshooting guides
- Maintenance windows and cost optimization

## 🚀 Quick Navigation

### For New Developers
1. Start with [Architecture Overview](./architecture/README.md) to understand the system
2. Follow [Development Guide](./development/README.md) for local setup
3. Review [Feature Documentation](./features/README.md) to understand capabilities

### For DevOps Engineers
1. Review [Infrastructure Documentation](./infrastructure/README.md) for AWS setup
2. Study [Operations Guide](./operations/README.md) for production procedures
3. Check [Development Guide](./development/README.md) for CI/CD integration

### For Stakeholders
1. Start with [Feature Documentation](./features/README.md) for capabilities overview
2. Review [Architecture Overview](./architecture/README.md) for technical foundation
3. Check [Operations Guide](./operations/README.md) for operational maturity

## 🏷️ Technology Stack Summary

| Layer | Technologies |
|-------|-------------|
| **Frontend** | Twig Templates, Hotwire Turbo, Tailwind CSS 4, Vanilla JavaScript |
| **Backend** | Symfony 7.3, PHP 8.3, Doctrine ORM, Server-Sent Events |
| **AI Integration** | OpenAI GPT, Custom Travel Prompting, Real-time Streaming |
| **Database** | PostgreSQL 17, Doctrine Migrations, Connection Pooling |
| **Infrastructure** | AWS ECS Fargate, ALB, Route53, ECR, RDS, Secrets Manager |
| **DevOps** | GitHub Actions, AWS CDK, Docker, Blue/Green Deployment |
| **Monitoring** | CloudWatch, Application Logging, Health Checks |
| **Security** | HTTPS, IAM Roles, VPC, Security Groups, OIDC |

## 📋 System Capabilities

### Core Features
- ✅ **Real-time AI Chat**: Streaming conversations with travel expertise
- ✅ **Travel Recommendations**: Personalized destination and itinerary suggestions
- ✅ **User Management**: Secure authentication and session management
- ✅ **Multi-Conversations**: Organized travel planning across multiple trips
- ✅ **Responsive Design**: Mobile-first interface with accessibility support

### Technical Features
- ✅ **Microservices Architecture**: Modular, scalable design
- ✅ **Infrastructure as Code**: Full AWS CDK implementation
- ✅ **CI/CD Pipeline**: Automated building and deployment
- ✅ **Security First**: Comprehensive security at all layers
- ✅ **Monitoring & Observability**: Full application and infrastructure monitoring

### Operational Features
- ✅ **Blue/Green Deployment**: Zero-downtime deployments
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
- **AWS Console**: ECS Services, RDS Instances, CloudWatch Dashboards
- **ECR Repository**: Container images and vulnerability scans

### Development Tools
- **Local Development**: Docker Compose for full stack
- **Code Quality**: PHP-CS-Fixer, PHPStan, ESLint
- **Testing**: PHPUnit configured, manual testing, security scanning

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