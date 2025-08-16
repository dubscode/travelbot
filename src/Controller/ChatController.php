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
        
        return $this->render('chat/index.html.twig', [
            'conversation' => $conversation,
            'messages' => $conversation->getMessages(),
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
        
        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();
        
        // Refresh conversation to get updated messages collection
        $this->entityManager->refresh($conversation);

        // Return updated messages with user message immediately
        return $this->render('chat/_messages_with_ai_trigger.html.twig', [
            'messages' => $conversation->getMessages(),
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
                            $aiMessage->setContent(''); // Start with empty content
                            $this->entityManager->persist($aiMessage);
                            $this->entityManager->flush();
                        }
                        
                        // Update AI message content periodically (every ~10 tokens to avoid too many DB writes)
                        if (strlen($fullContent) % 50 === 0) {
                            $aiMessage->setContent($fullContent);
                            $this->entityManager->flush();
                        }
                        
                    } elseif ($chunk['type'] === 'metadata') {
                        if ($aiMessage && isset($chunk['usage']['outputTokens'])) {
                            $aiMessage->setTokenCount($chunk['usage']['outputTokens']);
                        }
                    } elseif ($chunk['type'] === 'stop') {
                        // Finalize the AI message
                        if ($aiMessage) {
                            $aiMessage->setContent($fullContent);
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
        // Ensure user owns this conversation
        if ($conversation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        return $this->render('chat/index.html.twig', [
            'conversation' => $conversation,
            'messages' => $conversation->getMessages(),
            'user' => $this->getUser(),
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
        
        // Get last 10 messages for context (to avoid token limits)
        $recentMessages = array_slice($messages, -10);
        
        foreach ($recentMessages as $message) {
            $history[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }
        
        return $history;
    }
}
