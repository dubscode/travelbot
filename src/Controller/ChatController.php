<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Service\TravelRecommenderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private TravelRecommenderService $travelRecommender,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'app_chat')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Get or create active conversation
        $conversation = $this->getOrCreateActiveConversation($user);
        
        // Get messages using repository for consistent ordering
        $messages = $this->entityManager->getRepository(Message::class)
            ->findByConversationOrderedByDate($conversation);
        
        // Get all user conversations for sidebar
        $conversations = $this->entityManager->getRepository(Conversation::class)
            ->findByUserOrderedByRecent($user);
        
        return $this->render('chat/index.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'conversations' => $conversations,
            'user' => $user,
        ]);
    }

    #[Route('/send', name: 'app_chat_send', methods: ['POST'])]
    public function sendUserMessage(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $messageContent = $request->request->get('message');
        
        if (empty(trim($messageContent))) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        // Get or create conversation
        $conversation = $this->getOrCreateActiveConversation($user);
        
        // Create and save user message immediately
        $userMessage = new Message();
        $userMessage->setContent($messageContent);
        $userMessage->setRole(Message::ROLE_USER);
        $userMessage->setConversation($conversation);
        
        // Add message to conversation's collection for bidirectional relationship
        $conversation->addMessage($userMessage);
        
        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();
        
        // Update conversation title if it's still the default
        $this->updateConversationTitle($conversation, $messageContent);
        
        // Clear entity manager to ensure fresh data
        $this->entityManager->clear();
        
        // Re-fetch conversation with all messages
        $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversation->getId());

        // Get fresh messages from database using repository
        $messages = $this->entityManager->getRepository(Message::class)
            ->findByConversationOrderedByDate($conversation);

        // Return updated messages with user message immediately
        return $this->render('chat/_messages_with_ai_trigger.html.twig', [
            'messages' => $messages,
            'conversation' => $conversation,
            'user' => $user,
            'userMessageId' => $userMessage->getId(),
        ]);
    }

    #[Route('/stream/{messageId}', name: 'app_chat_stream', methods: ['GET'])]
    public function streamAiResponse(string $messageId): Response
    {
        // Disable PHP output buffering for real-time streaming
        @ob_end_clean();
        ini_set('output_buffering', 0);
        
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Find the user message
        $userMessage = $this->entityManager->getRepository(Message::class)->find($messageId);
        if (!$userMessage || $userMessage->getConversation()->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        $conversation = $userMessage->getConversation();
        
        // Build the streaming response using Symfony's StreamedResponse
        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Cache-Control');
        $response->headers->set('X-Accel-Buffering', 'no'); // For nginx compatibility
        
        $response->setCallback(function() use ($userMessage, $conversation, $user) {
            // Get conversation history for AI context
            $messageHistory = $this->buildMessageHistory($conversation);
            
            // Determine if we should use fast model
            $useFastModel = $this->travelRecommender->shouldUseFastModel($userMessage->getContent());
            
            $fullContent = '';
            $aiMessage = null;
            
            try {
                // Send immediate ready signal
                echo "event: ready\n";
                echo "data: {\"type\":\"ready\"}\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                
                // Start streaming
                echo "event: start\n";
                echo "data: {\"type\":\"start\"}\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                
                foreach ($this->travelRecommender->streamRecommendation(
                    $userMessage->getContent(),
                    $user,
                    $messageHistory,
                    $useFastModel
                ) as $chunk) {
                    if ($chunk['type'] === 'content') {
                        $fullContent .= $chunk['text'];
                        
                        // Send the token to the client
                        echo "event: token\n";
                        echo "data: " . json_encode([
                            'type' => 'token',
                            'text' => $chunk['text']
                        ]) . "\n\n";
                        if (ob_get_level()) ob_flush();
                        flush();
                        
                        // Create AI message on first token if it doesn't exist
                        if (!$aiMessage) {
                            $aiMessage = new Message();
                            $aiMessage->setConversation($conversation);
                            $aiMessage->setRole(Message::ROLE_ASSISTANT);
                            $aiMessage->setModelUsed($chunk['model']);
                            // Don't persist until we have actual content
                        }
                        
                        // Persist message on first content, then update periodically
                        if ($aiMessage && !$this->entityManager->contains($aiMessage)) {
                            // First time persisting - message now has content
                            $aiMessage->setContent($fullContent);
                            $this->entityManager->persist($aiMessage);
                            $this->entityManager->flush();
                        } elseif (strlen($fullContent) % 50 === 0) {
                            // Update AI message content periodically (every ~50 chars to avoid too many DB writes)
                            $aiMessage->setContent($fullContent);
                            $this->entityManager->flush();
                        }
                        
                    } elseif ($chunk['type'] === 'metadata') {
                        if ($aiMessage && isset($chunk['usage']['outputTokens'])) {
                            $aiMessage->setTokenCount($chunk['usage']['outputTokens']);
                        }
                    } elseif ($chunk['type'] === 'stop') {
                        // Finalize the AI message
                        if ($aiMessage && $fullContent) {
                            $aiMessage->setContent($fullContent);
                            // Persist if not already persisted
                            if (!$this->entityManager->contains($aiMessage)) {
                                $this->entityManager->persist($aiMessage);
                            }
                            $this->entityManager->flush();
                        }
                        
                        echo "event: complete\n";
                        echo "data: " . json_encode([
                            'type' => 'complete',
                            'stopReason' => $chunk['stopReason']
                        ]) . "\n\n";
                        if (ob_get_level()) ob_flush();
                        flush();
                        break;
                        
                    } elseif ($chunk['type'] === 'error') {
                        // Create error message
                        $errorMessage = new Message();
                        $errorMessage->setContent('Sorry, I encountered an error processing your request. Please try again.');
                        $errorMessage->setRole(Message::ROLE_ASSISTANT);
                        $errorMessage->setConversation($conversation);
                        $errorMessage->setMetadata(['error' => $chunk['message']]);
                        
                        $this->entityManager->persist($errorMessage);
                        $this->entityManager->flush();
                        
                        echo "event: error\n";
                        echo "data: " . json_encode([
                            'type' => 'error',
                            'message' => $chunk['message']
                        ]) . "\n\n";
                        if (ob_get_level()) ob_flush();
                        flush();
                        break;
                    }
                }
                
            } catch (\Exception $e) {
                // Create error message if streaming fails
                $errorMessage = new Message();
                $errorMessage->setContent('Sorry, I encountered an error processing your request. Please try again.');
                $errorMessage->setRole(Message::ROLE_ASSISTANT);
                $errorMessage->setConversation($conversation);
                $errorMessage->setMetadata(['error' => $e->getMessage()]);
                
                $this->entityManager->persist($errorMessage);
                $this->entityManager->flush();
                
                echo "event: error\n";
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => 'Streaming failed: ' . $e->getMessage()
                ]) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            }
        });
        
        return $response;
    }

    #[Route('/ai-response/{messageId}', name: 'app_chat_ai_response', methods: ['GET'])]
    public function getAiResponse(string $messageId): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Find the user message
        $userMessage = $this->entityManager->getRepository(Message::class)->find($messageId);
        if (!$userMessage || $userMessage->getConversation()->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        $conversation = $userMessage->getConversation();
        
        try {
            // Get conversation history for AI context
            $messageHistory = $this->buildMessageHistory($conversation);
            
            // Determine if we should use fast model
            $useFastModel = $this->travelRecommender->shouldUseFastModel($userMessage->getContent());
            
            // Generate AI response
            $aiResponse = $this->travelRecommender->generateRecommendation(
                $userMessage->getContent(),
                $user,
                $messageHistory,
                $useFastModel
            );
            
            // Create AI message
            $aiMessage = new Message();
            $aiMessage->setContent($aiResponse['content']);
            $aiMessage->setRole(Message::ROLE_ASSISTANT);
            $aiMessage->setConversation($conversation);
            $aiMessage->setModelUsed($aiResponse['model']);
            
            if (isset($aiResponse['usage']['outputTokens'])) {
                $aiMessage->setTokenCount($aiResponse['usage']['outputTokens']);
            }
            
            $this->entityManager->persist($aiMessage);
            $this->entityManager->flush();
            
            // Refresh conversation to get updated messages collection
            $this->entityManager->refresh($conversation);
            
            // Return updated messages for Turbo to update the UI
            return $this->render('chat/_messages.html.twig', [
                'messages' => $conversation->getMessages(),
                'conversation' => $conversation,
                'user' => $user,
            ]);
            
        } catch (\Exception $e) {
            // Create error message
            $errorMessage = new Message();
            $errorMessage->setContent('Sorry, I encountered an error processing your request. Please try again.');
            $errorMessage->setRole(Message::ROLE_ASSISTANT);
            $errorMessage->setConversation($conversation);
            $errorMessage->setMetadata(['error' => $e->getMessage()]);
            
            $this->entityManager->persist($errorMessage);
            $this->entityManager->flush();
            
            // Refresh conversation to get updated messages collection
            $this->entityManager->refresh($conversation);
            
            return $this->render('chat/_messages.html.twig', [
                'messages' => $conversation->getMessages(),
                'conversation' => $conversation,
                'user' => $user,
            ]);
        }
    }

    #[Route('/send-old', name: 'app_chat_send_old', methods: ['POST'])]
    public function sendMessage(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $messageContent = $request->request->get('message');
        
        if (empty(trim($messageContent))) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        // Get or create conversation
        $conversation = $this->getOrCreateActiveConversation($user);
        
        // Create user message
        $userMessage = new Message();
        $userMessage->setContent($messageContent);
        $userMessage->setRole(Message::ROLE_USER);
        $userMessage->setConversation($conversation);
        
        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();

        // Get conversation history for AI context
        $messageHistory = $this->buildMessageHistory($conversation);
        
        // Determine if we should use fast model
        $useFastModel = $this->travelRecommender->shouldUseFastModel($messageContent);
        
        try {
            // Generate AI response
            $aiResponse = $this->travelRecommender->generateRecommendation(
                $messageContent,
                $user,
                $messageHistory,
                $useFastModel
            );
            
            // Create AI message
            $aiMessage = new Message();
            $aiMessage->setContent($aiResponse['content']);
            $aiMessage->setRole(Message::ROLE_ASSISTANT);
            $aiMessage->setConversation($conversation);
            $aiMessage->setModelUsed($aiResponse['model']);
            
            if (isset($aiResponse['usage']['outputTokens'])) {
                $aiMessage->setTokenCount($aiResponse['usage']['outputTokens']);
            }
            
            $this->entityManager->persist($aiMessage);
            $this->entityManager->flush();
            
            // Refresh conversation to get updated messages collection
            $this->entityManager->refresh($conversation);
            
            // Return updated messages for Turbo to update the UI
            return $this->render('chat/_messages.html.twig', [
                'messages' => $conversation->getMessages(),
                'conversation' => $conversation,
                'user' => $user,
            ]);
            
        } catch (\Exception $e) {
            // Create error message
            $errorMessage = new Message();
            $errorMessage->setContent('Sorry, I encountered an error processing your request. Please try again.');
            $errorMessage->setRole(Message::ROLE_ASSISTANT);
            $errorMessage->setConversation($conversation);
            $errorMessage->setMetadata(['error' => $e->getMessage()]);
            
            $this->entityManager->persist($errorMessage);
            $this->entityManager->flush();
            
            // Refresh conversation to get updated messages collection
            $this->entityManager->refresh($conversation);
            
            return $this->render('chat/_messages.html.twig', [
                'messages' => $conversation->getMessages(),
                'conversation' => $conversation,
                'user' => $user,
            ]);
        }
    }

    #[Route('/conversation/{id}', name: 'app_chat_conversation')]
    public function viewConversation(Conversation $conversation): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Ensure user owns this conversation
        if ($conversation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        // Get messages using repository for consistent ordering
        $messages = $this->entityManager->getRepository(Message::class)
            ->findByConversationOrderedByDate($conversation);
        
        // Get all user conversations for sidebar
        $conversations = $this->entityManager->getRepository(Conversation::class)
            ->findByUserOrderedByRecent($user);
        
        return $this->render('chat/index.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'conversations' => $conversations,
            'user' => $user,
        ]);
    }

    #[Route('/new', name: 'app_chat_new', methods: ['POST'])]
    public function newConversation(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Mark current conversations as inactive
        $activeConversations = $this->entityManager->getRepository(Conversation::class)
            ->findBy(['user' => $user, 'isActive' => true]);
        
        foreach ($activeConversations as $conv) {
            $conv->setActive(false);
        }
        
        // Create new conversation
        $conversation = new Conversation();
        $conversation->setUser($user);
        $conversation->setTitle('New Chat');
        $conversation->setActive(true);
        
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();
        
        return $this->redirectToRoute('app_chat');
    }

    #[Route('/sidebar', name: 'app_chat_sidebar', methods: ['GET'])]
    public function sidebar(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Get all user conversations for sidebar
        $conversations = $this->entityManager->getRepository(Conversation::class)
            ->findByUserOrderedByRecent($user);
        
        // Find current conversation if ID provided
        $currentConversation = null;
        $currentConversationId = $request->query->get('currentConversationId');
        if ($currentConversationId) {
            $currentConversation = $this->entityManager->getRepository(Conversation::class)
                ->find($currentConversationId);
        }
        
        return $this->render('chat/_sidebar_conversations.html.twig', [
            'conversations' => $conversations,
            'current_conversation' => $currentConversation,
            'user' => $user,
        ]);
    }

    private function getOrCreateActiveConversation($user): Conversation
    {
        // Try to find an active conversation
        $conversation = $this->entityManager->getRepository(Conversation::class)
            ->findOneBy(['user' => $user, 'isActive' => true]);
        
        if (!$conversation) {
            // Create new conversation
            $conversation = new Conversation();
            $conversation->setUser($user);
            $conversation->setTitle('Travel Chat');
            $conversation->setActive(true);
            
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();
        }
        
        return $conversation;
    }

    private function buildMessageHistory(Conversation $conversation): array
    {
        $messages = $conversation->getMessages()->toArray();
        $history = [];
        
        // Filter out messages with empty or null content
        $validMessages = array_filter($messages, function(Message $message) {
            $content = $message->getContent();
            return $content !== null && trim($content) !== '';
        });
        
        // Get last 10 valid messages for context (to avoid token limits)
        $recentMessages = array_slice($validMessages, -10);
        
        foreach ($recentMessages as $message) {
            $history[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }
        
        return $history;
    }

    private function updateConversationTitle(Conversation $conversation, string $messageContent): void
    {
        // Only update if the title is still the default or empty
        if (!$conversation->getTitle() || 
            in_array($conversation->getTitle(), ['Travel Chat', 'New Chat', ''])) {
            
            // Generate AI-powered title using the Haiku model for speed
            $title = $this->travelRecommender->generateConversationTitle($messageContent);
            
            if ($title) {
                $conversation->setTitle($title);
                $conversation->setUpdatedAt(new \DateTime());
                
                $this->entityManager->persist($conversation);
                $this->entityManager->flush();
            }
        }
    }

    #[Route('/query-analysis', name: 'app_chat_query_analysis', methods: ['POST'])]
    public function analyzeQuery(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $message = $request->request->get('message');
        
        if (empty(trim($message))) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }
        
        try {
            $queryAnalysis = $this->travelRecommender->getQueryAnalysis($message);
            
            return new JsonResponse([
                'success' => true,
                'analysis' => $queryAnalysis,
                'suggestions' => $this->generateQuickSuggestions($queryAnalysis)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Query analysis failed', [
                'message' => $message,
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'error' => 'Failed to analyze query'
            ], 500);
        }
    }

    #[Route('/personalized-recommendations', name: 'app_chat_personalized_recommendations', methods: ['GET'])]
    public function getPersonalizedRecommendations(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $limit = min($request->query->getInt('limit', 5), 10); // Max 10 recommendations
        
        try {
            $recommendations = $this->travelRecommender->getPersonalizedRecommendations($user, $limit);
            
            return new JsonResponse([
                'success' => true,
                'recommendations' => $recommendations,
                'user_preferences_available' => !empty($user->getInterests()) || !empty($user->getClimatePreferences())
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get personalized recommendations', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'error' => 'Failed to get personalized recommendations'
            ], 500);
        }
    }

    #[Route('/track-interaction', name: 'app_chat_track_interaction', methods: ['POST'])]
    public function trackInteraction(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $interactionType = $request->request->get('type');
        $data = $request->request->all('data');
        
        if (empty($interactionType)) {
            return new JsonResponse(['error' => 'Interaction type is required'], 400);
        }
        
        try {
            $this->travelRecommender->trackUserInteraction($user, $interactionType, $data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Interaction tracked successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track user interaction', [
                'userId' => $user->getId(),
                'interactionType' => $interactionType,
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'error' => 'Failed to track interaction'
            ], 500);
        }
    }

    #[Route('/conversation-context/{conversationId}', name: 'app_chat_conversation_context', methods: ['GET'])]
    public function getConversationContext(string $conversationId): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversationId);
        
        if (!$conversation || $conversation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        try {
            // Get message history
            $messageHistory = $this->buildMessageHistory($conversation);
            
            // Extract conversation patterns and preferences
            $conversationPatterns = $this->extractConversationPatterns($messageHistory);
            
            // Get suggested follow-up questions based on conversation
            $followUpSuggestions = $this->generateConversationFollowUps($messageHistory, $user);
            
            return new JsonResponse([
                'success' => true,
                'context' => [
                    'message_count' => count($messageHistory),
                    'patterns' => $conversationPatterns,
                    'follow_up_suggestions' => $followUpSuggestions,
                    'conversation_stage' => $this->determineConversationStage($messageHistory)
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get conversation context', [
                'conversationId' => $conversationId,
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'error' => 'Failed to get conversation context'
            ], 500);
        }
    }

    #[Route('/smart-suggestions/{messageId}', name: 'app_chat_smart_suggestions', methods: ['GET'])]
    public function getSmartSuggestions(string $messageId): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $message = $this->entityManager->getRepository(Message::class)->find($messageId);
        
        if (!$message || $message->getConversation()->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        try {
            // Analyze the message to understand user intent
            $queryAnalysis = $this->travelRecommender->getQueryAnalysis($message->getContent());
            
            // Generate contextual suggestions
            $suggestions = $this->generateContextualSuggestions($queryAnalysis, $user);
            
            return new JsonResponse([
                'success' => true,
                'suggestions' => $suggestions,
                'query_analysis' => $queryAnalysis
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get smart suggestions', [
                'messageId' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return new JsonResponse([
                'error' => 'Failed to get smart suggestions'
            ], 500);
        }
    }

    private function generateQuickSuggestions(array $queryAnalysis): array
    {
        $suggestions = [];
        
        // Suggest budget clarification
        if (empty($queryAnalysis['budget']['budget_level']) && 
            empty($queryAnalysis['budget']['max_per_day'])) {
            $suggestions[] = [
                'type' => 'budget',
                'text' => 'What\'s your travel budget?',
                'action' => 'clarify_budget'
            ];
        }
        
        // Suggest travel dates
        if (empty($queryAnalysis['travel_dates']['start_date'])) {
            $suggestions[] = [
                'type' => 'dates',
                'text' => 'When are you planning to travel?',
                'action' => 'clarify_dates'
            ];
        }
        
        // Suggest destination type
        if (empty($queryAnalysis['destination_preferences']['destination_type'])) {
            $suggestions[] = [
                'type' => 'destination_type',
                'text' => 'Beach, city, or mountain destination?',
                'action' => 'clarify_destination_type'
            ];
        }
        
        // Suggest group size
        if (empty($queryAnalysis['traveler_info']['group_size'])) {
            $suggestions[] = [
                'type' => 'group_size',
                'text' => 'How many people will be traveling?',
                'action' => 'clarify_group_size'
            ];
        }
        
        return array_slice($suggestions, 0, 3); // Limit to 3 suggestions
    }

    private function extractConversationPatterns(array $messageHistory): array
    {
        $patterns = [
            'mentioned_destinations' => [],
            'mentioned_activities' => [],
            'budget_references' => [],
            'preference_indicators' => []
        ];
        
        foreach ($messageHistory as $message) {
            if ($message['role'] === 'user') {
                $content = strtolower($message['content']);
                
                // Extract destination mentions
                $destinations = ['paris', 'tokyo', 'bali', 'thailand', 'greece', 'italy', 'spain', 'japan'];
                foreach ($destinations as $dest) {
                    if (strpos($content, $dest) !== false) {
                        $patterns['mentioned_destinations'][] = $dest;
                    }
                }
                
                // Extract activity mentions
                $activities = ['beach', 'hiking', 'culture', 'nightlife', 'spa', 'adventure', 'food', 'relax'];
                foreach ($activities as $activity) {
                    if (strpos($content, $activity) !== false) {
                        $patterns['mentioned_activities'][] = $activity;
                    }
                }
                
                // Extract budget indicators
                if (preg_match('/\$\\d+|\\d+\\s*dollar|budget|cheap|expensive|luxury/', $content)) {
                    $patterns['budget_references'][] = $content;
                }
            }
        }
        
        // Remove duplicates
        foreach ($patterns as $key => $values) {
            $patterns[$key] = array_unique($values);
        }
        
        return $patterns;
    }

    private function generateConversationFollowUps(array $messageHistory, $user): array
    {
        $followUps = [];
        
        // Check conversation length to determine appropriate follow-ups
        $userMessageCount = count(array_filter($messageHistory, fn($msg) => $msg['role'] === 'user'));
        
        if ($userMessageCount < 3) {
            // Early conversation - gather basic info
            $followUps[] = "Would you like to share your preferred travel style?";
            $followUps[] = "What's most important to you in a destination?";
        } elseif ($userMessageCount < 6) {
            // Mid conversation - refine preferences
            $followUps[] = "Are there any specific amenities you're looking for?";
            $followUps[] = "Would you like recommendations for similar destinations?";
        } else {
            // Later conversation - action-oriented
            $followUps[] = "Would you like help with planning your itinerary?";
            $followUps[] = "Shall I provide booking recommendations?";
        }
        
        return array_slice($followUps, 0, 2);
    }

    private function determineConversationStage(array $messageHistory): string
    {
        $userMessageCount = count(array_filter($messageHistory, fn($msg) => $msg['role'] === 'user'));
        
        return match (true) {
            $userMessageCount <= 1 => 'initial_inquiry',
            $userMessageCount <= 3 => 'preference_gathering',
            $userMessageCount <= 6 => 'recommendation_refinement',
            default => 'action_planning'
        };
    }

    private function generateContextualSuggestions(array $queryAnalysis, $user): array
    {
        $suggestions = [];
        
        // Generate suggestions based on query analysis
        if (!empty($queryAnalysis['destination_preferences']['destination_type'])) {
            $destType = $queryAnalysis['destination_preferences']['destination_type'][0];
            $suggestions[] = [
                'text' => "Show me more {$destType} destinations",
                'action' => 'search_similar_destinations',
                'params' => ['type' => $destType]
            ];
        }
        
        if (!empty($queryAnalysis['activity_preferences'])) {
            $activity = $queryAnalysis['activity_preferences'][0];
            $suggestions[] = [
                'text' => "Find destinations great for {$activity}",
                'action' => 'search_by_activity',
                'params' => ['activity' => $activity]
            ];
        }
        
        // Add personalized suggestions based on user history
        if ($user->getInterests()) {
            $interest = $user->getInterests()[0];
            $suggestions[] = [
                'text' => "Recommendations based on your interest in {$interest}",
                'action' => 'personalized_recommendations',
                'params' => ['interest' => $interest]
            ];
        }
        
        return array_slice($suggestions, 0, 4);
    }
}
