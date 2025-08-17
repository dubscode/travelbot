# TravelBot Development Guide

## Quick Start

### Prerequisites
- Docker and Docker Compose
- Git
- Node.js 22+ (for asset building)
- PHP 8.3+ (for local development)

### Local Development Setup

1. **Clone and Setup**
```bash
git clone https://github.com/dubscode/travelbot.git
cd travelbot
cp .env.example .env
```

2. **Environment Configuration**
```bash
# Edit .env file with your settings
DATABASE_URL="postgresql://app:password@localhost:5432/travelbot"
OPENAI_API_KEY="your-openai-api-key"
APP_ENV=dev
APP_DEBUG=1
```

3. **Start Development Environment**
```bash
# Start all services
docker-compose up -d

# Install dependencies
composer install
npm install

# Setup database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Build assets
npm run dev
```

4. **Access Application**
- Application: http://localhost:8000
- Database: localhost:5432
- Logs: `docker-compose logs -f`

## Database Schema

### Entity Relationships

```mermaid
erDiagram
    User ||--o{ Conversation : has
    Conversation ||--o{ Message : contains
    User {
        int id PK
        string email UK
        string password
        string firstName
        string lastName
        datetime createdAt
        datetime updatedAt
    }
    Conversation {
        int id PK
        int userId FK
        string title
        datetime createdAt
        datetime updatedAt
    }
    Message {
        int id PK
        int conversationId FK
        string role
        text content
        datetime createdAt
    }
    Destination {
        int id PK
        string name
        string country
        string description
        json metadata
        datetime createdAt
    }
```

### Key Entities

#### User Entity
```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Conversation::class)]
    private Collection $conversations;
}
```

#### Conversation Entity
```php
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
class Conversation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'conversations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class)]
    private Collection $messages;
}
```

#### Message Entity
```php
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null; // 'user' or 'assistant'

    #[ORM\Column(type: 'text')]
    private ?string $content = null;
}
```

## API Documentation

### Authentication Endpoints

#### POST /auth/login
Login user with email and password.

**Request:**
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "user": {
        "id": 1,
        "email": "user@example.com",
        "firstName": "John",
        "lastName": "Doe"
    }
}
```

#### POST /auth/register
Register new user account.

**Request:**
```json
{
    "email": "user@example.com",
    "password": "password123",
    "firstName": "John",
    "lastName": "Doe"
}
```

#### POST /auth/logout
Logout current user session.

### Chat Endpoints

#### GET /chat
Display main chat interface.

#### POST /chat/conversation
Create new conversation.

**Request:**
```json
{
    "title": "Trip to Japan"
}
```

**Response:**
```json
{
    "id": 1,
    "title": "Trip to Japan",
    "createdAt": "2024-01-01T12:00:00Z"
}
```

#### GET /chat/conversation/{id}
Get conversation with messages.

**Response:**
```json
{
    "id": 1,
    "title": "Trip to Japan",
    "messages": [
        {
            "id": 1,
            "role": "user",
            "content": "I want to plan a trip to Japan",
            "createdAt": "2024-01-01T12:00:00Z"
        },
        {
            "id": 2,
            "role": "assistant",
            "content": "I'd be happy to help you plan your trip to Japan!",
            "createdAt": "2024-01-01T12:00:01Z"
        }
    ]
}
```

#### POST /chat/message
Send message and get AI response.

**Request:**
```json
{
    "conversationId": 1,
    "content": "What are the best places to visit in Tokyo?"
}
```

**Response:** Server-Sent Events stream
```
data: {"type": "start"}

data: {"type": "content", "content": "Tokyo offers many amazing"}

data: {"type": "content", "content": " attractions. Here are some"}

data: {"type": "end"}
```

#### GET /chat/stream/{conversationId}
Stream AI responses for conversation.

**Headers:**
```
Accept: text/event-stream
Cache-Control: no-cache
```

### Destination Endpoints

#### GET /destinations
List travel destinations.

**Query Parameters:**
- `search`: Search term
- `country`: Filter by country
- `limit`: Results per page (default: 20)

**Response:**
```json
{
    "destinations": [
        {
            "id": 1,
            "name": "Tokyo",
            "country": "Japan",
            "description": "Japan's bustling capital city"
        }
    ],
    "total": 1
}
```

#### GET /destinations/{id}
Get destination details.

## Frontend Architecture

### Twig Templates

**Base Layout** (`templates/base.html.twig`):
```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{% block title %}TravelBot{% endblock %}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {% block stylesheets %}
        {{ encore_entry_link_tags('app') }}
    {% endblock %}
</head>
<body data-turbo-permanent>
    {% block header %}
        <nav class="bg-blue-600 text-white p-4">
            <!-- Navigation -->
        </nav>
    {% endblock %}
    
    <main>
        {% block body %}{% endblock %}
    </main>
    
    {% block javascripts %}
        {{ encore_entry_script_tags('app') }}
    {% endblock %}
</body>
</html>
```

**Chat Interface** (`templates/chat/index.html.twig`):
```twig
{% extends 'base.html.twig' %}

{% block body %}
<div class="flex h-screen">
    <!-- Sidebar -->
    <div class="w-1/4 bg-gray-100">
        <div id="conversations">
            {% for conversation in conversations %}
                {{ include('chat/_conversation_item.html.twig') }}
            {% endfor %}
        </div>
    </div>
    
    <!-- Chat Area -->
    <div class="flex-1 flex flex-col">
        <div id="messages" class="flex-1 overflow-y-auto p-4">
            {% if currentConversation %}
                {% for message in currentConversation.messages %}
                    {{ include('chat/_message.html.twig') }}
                {% endfor %}
            {% endif %}
        </div>
        
        <!-- Input Form -->
        {{ include('chat/_message_form.html.twig') }}
    </div>
</div>
{% endblock %}
```

### JavaScript Components

**Chat Streaming** (`assets/js/chat.js`):
```javascript
class ChatStreaming {
    constructor(conversationId) {
        this.conversationId = conversationId;
        this.eventSource = null;
        this.messageContainer = document.getElementById('messages');
    }

    async sendMessage(content) {
        // Add user message to UI
        this.addMessage('user', content);
        
        // Send to server
        const response = await fetch('/chat/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                conversationId: this.conversationId,
                content: content
            })
        });

        if (response.ok) {
            this.startStreaming();
        }
    }

    startStreaming() {
        this.eventSource = new EventSource(`/chat/stream/${this.conversationId}`);
        let assistantMessage = this.addMessage('assistant', '');
        
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'content':
                    assistantMessage.textContent += data.content;
                    break;
                case 'end':
                    this.eventSource.close();
                    break;
            }
        };
    }

    addMessage(role, content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${role} mb-4`;
        messageDiv.textContent = content;
        this.messageContainer.appendChild(messageDiv);
        this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
        return messageDiv;
    }
}
```

### Asset Building

**Webpack Encore Configuration** (`webpack.config.js`):
```javascript
const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.js')
    .enableStimulusBundle()
    .enableSassLoader()
    .enablePostCssLoader()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    });

module.exports = Encore.getWebpackConfig();
```

## Testing

### Test Infrastructure
PHPUnit is configured via `phpunit.dist.xml` but no test files have been created yet. The `tests/` directory exists with only a bootstrap file.

**To implement testing:**
1. Create test files in the `tests/` directory
2. Follow Symfony testing conventions  
3. Use WebTestCase for controller tests
4. Use KernelTestCase for service tests

### Running Tests (once implemented)
```bash
# Run all tests
./vendor/bin/phpunit

# Test in Docker
docker-compose exec php ./vendor/bin/phpunit
```

## Development Workflow

1. **Feature Development**
   - Create feature branch from `main`
   - Implement changes
   - Run quality checks locally
   - Submit pull request

2. **Code Review Process**
   - Manual code review
   - Build validation
   - Merge to main

3. **Deployment Pipeline**
   - Build and push Docker image
   - Deploy to staging
   - Production deployment approval

This guide provides comprehensive information for developers working on the TravelBot application, covering everything from initial setup to advanced development patterns.