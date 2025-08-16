# Symfony TravelBot AI Architecture Guide

*A comprehensive guide for Rails/Node.js developers learning Symfony through a real-time AI chatbot implementation*

## 🎯 Project Overview

This is a **Symfony 7** travel recommendation chatbot that integrates with **AWS Bedrock Claude AI** models to provide real-time, streaming responses. The application demonstrates modern Symfony patterns including real-time Server-Sent Events, Doctrine ORM, dependency injection, and AWS SDK integration.

## 🏗️ Architecture Overview

### MVC Pattern in Symfony vs Rails

| Component | Symfony | Rails Equivalent | Purpose |
|-----------|---------|------------------|---------|
| **Controllers** | `src/Controller/ChatController.php` | `app/controllers/chat_controller.rb` | Handle HTTP requests/responses |
| **Models** | `src/Entity/` (Message, User, etc.) | `app/models/` | Data models & business logic |
| **Views** | `templates/` (Twig) | `app/views/` (ERB) | HTML templates |
| **Services** | `src/Service/` | `app/services/` | Business logic & external APIs |
| **Routes** | PHP Attributes `#[Route()]` | `config/routes.rb` | URL routing |

### Key Architectural Differences from Rails

**Symfony Uses:**
- **Dependency Injection Container** (like Rails Service Container but more explicit)
- **PHP Attributes** for routing (instead of separate config files)
- **Doctrine ORM** (similar to ActiveRecord but more configuration-heavy)
- **Service definitions** in YAML (explicit wiring vs Rails' convention)

## 🔧 Core Components Deep Dive

### 1. Controllers - Request/Response Handling

**File:** `src/Controller/ChatController.php`

```php
#[Route('/chat')]
#[IsGranted('ROLE_USER')]  // Like Rails before_action for auth
class ChatController extends AbstractController
{
    public function __construct(
        private TravelRecommenderService $travelRecommender,  // DI in constructor
        private EntityManagerInterface $entityManager,       // Like Rails' ActiveRecord
        private LoggerInterface $logger
    ) {}
    
    #[Route('/send', name: 'app_chat_send', methods: ['POST'])]
    public function sendUserMessage(Request $request): Response
    {
        // Similar to Rails: params[:message]
        $messageContent = $request->request->get('message');
        
        // Doctrine equivalent of User.create!
        $userMessage = new Message();
        $userMessage->setContent($messageContent);
        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();  // Like ActiveRecord save!
    }
}
```

**Rails Comparison:**
```ruby
# Rails equivalent
class ChatController < ApplicationController
  before_action :authenticate_user!  # Similar to #[IsGranted]
  
  def send_message
    @message = current_user.messages.create!(
      content: params[:message]  # Similar to $request->request->get()
    )
  end
end
```

**Key Symfony Concepts:**
- **Dependency Injection in Constructor**: Services injected automatically
- **PHP Attributes**: `#[Route()]` replaces separate routing files
- **Request Object**: Explicit access to HTTP data vs Rails' `params`
- **EntityManager**: Manual persist/flush vs ActiveRecord's automatic save

### 2. Services - Business Logic Layer

**File:** `src/Service/TravelRecommenderService.php`

```php
class TravelRecommenderService
{
    public function __construct(
        private ClaudeService $claudeService,              // Service dependency
        private DestinationRepository $destinationRepository,  // Data access
        private LoggerInterface $logger
    ) {}
    
    // PHP Generator for streaming (similar to Ruby Enumerator)
    public function streamRecommendation(string $userMessage): \Generator 
    {
        $destinations = $this->getRelevantDestinations($userMessage);
        $messages = $this->buildMessages($userMessage, $destinations);
        
        // Yield tokens one by one (like Ruby yield)
        foreach ($this->claudeService->streamResponse($messages) as $chunk) {
            yield $chunk;  // Streams data without loading all in memory
        }
    }
}
```

**Service Configuration (services.yaml):**
```yaml
services:
    App\Service\TravelRecommenderService:
        arguments:
            $claudeService: '@App\Service\ClaudeService'
            $destinationRepository: '@App\Repository\DestinationRepository'
            $logger: '@logger'
```

**Rails/Node.js Comparison:**
```ruby
# Rails service
class TravelRecommenderService
  def initialize(claude_service: ClaudeService.new)
    @claude_service = claude_service
  end
  
  def stream_recommendation(message)
    # Ruby Enumerator for streaming
    Enumerator.new do |yielder|
      claude_service.stream_response(message) do |chunk|
        yielder << chunk
      end
    end
  end
end
```

**Key Symfony Concepts:**
- **Explicit Service Wiring**: Dependencies defined in `services.yaml`
- **PHP Generators**: Memory-efficient streaming with `yield`
- **Repository Pattern**: Separate data access from entities
- **Constructor Property Promotion**: `private ClaudeService $claudeService` creates property + assignment

### 3. Entities - Data Models

**File:** `src/Entity/Message.php`

```php
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $id = null;
    
    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;
    
    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;
    
    // Explicit getters/setters (no ActiveRecord magic)
    public function getContent(): ?string 
    {
        return $this->content;
    }
    
    public function setContent(string $content): static 
    {
        $this->content = $content;
        return $this;  // Fluent interface
    }
}
```

**Rails Comparison:**
```ruby
# Rails ActiveRecord model
class Message < ApplicationRecord
  ROLE_USER = 'user'
  ROLE_ASSISTANT = 'assistant'
  
  belongs_to :conversation
  
  # Rails magic - no explicit getters/setters needed
  # id, content, role automatically accessible
end
```

**Key Differences:**
- **Explicit Attributes**: Doctrine requires explicit column definitions
- **Manual Getters/Setters**: No ActiveRecord magic methods
- **PHP Attributes**: `#[ORM\Column]` vs ActiveRecord conventions
- **Nullable Types**: PHP 8's `?string` for optional fields

### 4. Real-Time Streaming Architecture

The most complex part is the **real-time AI streaming**, which combines:

#### A. Server-Side Streaming (PHP)

**File:** `src/Controller/ChatController.php`

```php
#[Route('/stream/{messageId}')]
public function streamAiResponse(string $messageId): Response
{
    // Disable PHP output buffering for real-time streaming
    @ob_end_clean();
    ini_set('output_buffering', 0);
    
    // Symfony's StreamedResponse for Server-Sent Events
    $response = new StreamedResponse();
    $response->headers->set('Content-Type', 'text/event-stream');
    $response->headers->set('Cache-Control', 'no-cache');
    
    $response->setCallback(function() use ($messageId) {
        foreach ($this->travelRecommender->streamRecommendation($message) as $chunk) {
            // Send SSE format
            echo "event: token\n";
            echo "data: " . json_encode(['text' => $chunk['text']]) . "\n\n";
            
            // Force immediate output (crucial for streaming)
            if (ob_get_level()) ob_flush();
            flush();
        }
    });
    
    return $response;
}
```

**Node.js/Express Comparison:**
```javascript
// Node.js equivalent
app.get('/stream/:messageId', (req, res) => {
  res.writeHead(200, {
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache'
  });
  
  for await (const chunk of travelRecommender.streamRecommendation(message)) {
    res.write(`event: token\n`);
    res.write(`data: ${JSON.stringify({text: chunk.text})}\n\n`);
    // No manual flush needed in Node.js
  }
});
```

#### B. AWS Bedrock Integration

**File:** `src/Service/ClaudeService.php`

```php
class ClaudeService 
{
    private BedrockRuntimeClient $bedrockClient;
    
    public function __construct(
        private string $region,
        private string $sonnetModel,  // Injected from environment
        private string $haikuModel,
        private LoggerInterface $logger
    ) {
        // AWS SSO authentication
        $credentialProvider = CredentialProvider::sso('anny-prod');
        
        $this->bedrockClient = new BedrockRuntimeClient([
            'region' => $this->region,
            'credentials' => $credentialProvider,
        ]);
    }
    
    public function streamResponse(array $messages): \Generator 
    {
        $result = $this->bedrockClient->converseStream([
            'modelId' => $this->sonnetModel,
            'messages' => $messages,
            'inferenceConfig' => [
                'maxTokens' => 4000,
                'temperature' => 0.7,
            ],
        ]);
        
        // Process streaming events from AWS
        foreach ($result['stream'] as $event) {
            if (isset($event['contentBlockDelta']['delta']['text'])) {
                yield [
                    'type' => 'content',
                    'text' => $event['contentBlockDelta']['delta']['text']
                ];
            }
        }
    }
}
```

**Key Streaming Concepts:**
- **PHP Output Buffering**: Must be disabled for real-time streaming
- **Server-Sent Events**: Standard for browser streaming (vs WebSockets)
- **PHP Generators**: Memory-efficient token streaming
- **AWS SDK Streaming**: Real-time token consumption from Bedrock

#### C. Frontend Real-Time Updates

**File:** `templates/chat/_messages_with_ai_trigger.html.twig`

```javascript
// EventSource API for Server-Sent Events
const eventSource = new EventSource('/chat/stream/' + messageId);

eventSource.addEventListener('token', function(event) {
    const data = JSON.parse(event.data);
    const contentElement = document.getElementById('streaming-content');
    
    // Create DOM element for each token
    const tokenSpan = document.createElement('span');
    tokenSpan.textContent = data.text;
    contentElement.appendChild(tokenSpan);
    
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
});
```

### 5. Dependency Injection Container

**Symfony's Service Container** is more explicit than Rails:

**File:** `config/services.yaml`

```yaml
services:
    _defaults:
        autowire: true      # Auto-inject based on type hints
        autoconfigure: true # Auto-register as services
    
    # Auto-register all classes in src/
    App\:
        resource: '../src/'
        exclude:
            - '../src/Entity/'  # Entities aren't services
    
    # Custom service configuration
    App\Service\ClaudeService:
        arguments:
            $region: '%env(AWS_REGION)%'           # Environment variable
            $sonnetModel: '%env(BEDROCK_CLAUDE_SONNET_MODEL)%'
            $logger: '@logger'                      # Reference to logger service
```

**Rails Comparison:**
Rails uses convention-based injection through initializers and modules, while Symfony uses explicit container configuration.

## 🎛️ AI Model Selection Logic

**Intelligent Model Switching:**

```php
public function shouldUseFastModel(string $userMessage): bool
{
    $simplePatterns = [
        '/^(hi|hello|hey)/i',           // Greetings
        '/^(yes|no|ok|thanks)/i',       // Simple responses  
        '/^\w{1,20}$/',                 // Very short messages
        '/quick|simple|fast/i',         // Explicit speed requests
    ];
    
    foreach ($simplePatterns as $pattern) {
        if (preg_match($pattern, trim($userMessage))) {
            return true;  // Use Haiku (fast, cheap)
        }
    }
    
    return false;  // Use Sonnet (detailed, expensive)
}
```

This creates a **cost-optimization strategy**:
- **Haiku**: Simple queries, greetings, quick responses
- **Sonnet**: Complex travel planning, detailed recommendations

## 🗄️ Database Schema & Relationships

**Doctrine vs ActiveRecord:**

```php
// Symfony Entity Relationships
class User 
{
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Conversation::class)]
    private Collection $conversations;
}

class Conversation
{
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'conversations')]
    private ?User $user = null;
    
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class)]
    private Collection $messages;
}

class Message
{
    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    private ?Conversation $conversation = null;
}
```

**Rails ActiveRecord Equivalent:**
```ruby
class User < ApplicationRecord
  has_many :conversations
end

class Conversation < ApplicationRecord
  belongs_to :user
  has_many :messages
end

class Message < ApplicationRecord
  belongs_to :conversation
end
```

**Key Differences:**
- **Explicit Mapping**: Doctrine requires explicit relationship configuration
- **Collections**: Use Doctrine Collections instead of arrays
- **Bidirectional Mapping**: Must define both sides of relationships

## 🔒 Security & Authentication

**Symfony Security Component:**

```php
#[Route('/chat')]
#[IsGranted('ROLE_USER')]  // Protect entire controller
class ChatController extends AbstractController 
{
    public function streamAiResponse(string $messageId): Response 
    {
        $user = $this->getUser();  // Current authenticated user
        
        // Authorization check
        if ($userMessage->getConversation()->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
    }
}
```

Similar to Rails' `before_action :authenticate_user!` but using PHP attributes.

## 🚀 Performance Optimizations

### 1. Streaming Optimizations
- **PHP Output Buffering**: Disabled for real-time streaming
- **Memory Efficiency**: PHP Generators prevent loading entire response in memory
- **Database Updates**: Periodic saves (every 50 characters) to reduce DB load

### 2. AI Cost Optimization
- **Model Selection**: Automatic Haiku/Sonnet switching based on query complexity
- **Token Counting**: Track usage for cost monitoring
- **Context Management**: Limit conversation history to last 10 messages

## 📊 Request Flow Diagram

```
1. User sends message → ChatController::sendUserMessage()
   ↓
2. Save user message to DB via EntityManager
   ↓
3. Return Twig template with EventSource JavaScript
   ↓
4. Browser opens SSE connection → ChatController::streamAiResponse()
   ↓
5. Controller calls TravelRecommenderService::streamRecommendation()
   ↓
6. Service calls ClaudeService::streamResponse()
   ↓
7. AWS Bedrock streams tokens → PHP Generator yields chunks
   ↓
8. Each token sent to browser as Server-Sent Event
   ↓
9. JavaScript receives tokens → Updates DOM in real-time
   ↓
10. Complete response saved to database
```

## 🎯 Key Takeaways for Interview

**What This Demonstrates:**

1. **Modern Symfony Patterns**: Dependency injection, attributes, services
2. **Real-Time Architecture**: Server-Sent Events, streaming responses
3. **External API Integration**: AWS SDK, authentication, error handling
4. **Database Design**: Doctrine ORM, relationships, migrations
5. **Frontend Integration**: JavaScript EventSource, DOM manipulation
6. **Performance Considerations**: Memory management, cost optimization
7. **Security**: Authentication, authorization, input validation

**Symfony vs Rails Key Differences:**

| Aspect | Symfony | Rails |
|--------|---------|-------|
| **Routing** | PHP Attributes | routes.rb file |
| **DI** | Explicit container | Convention-based |
| **ORM** | Doctrine (config-heavy) | ActiveRecord (convention) |
| **Services** | Explicit YAML config | Auto-loaded classes |
| **Controllers** | Constructor injection | Instance variables |
| **Templates** | Twig | ERB/Haml |

This project showcases a **production-ready Symfony application** with modern patterns, real-time features, and AI integration - perfect for demonstrating your ability to adapt from Rails to Symfony while building something genuinely impressive.