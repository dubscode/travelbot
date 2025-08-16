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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private TravelRecommenderService $travelRecommender,
        private EntityManagerInterface $entityManager
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
            
            // Return updated messages for Turbo to update the UI
            return $this->render('chat/_messages.html.twig', [
                'messages' => $conversation->getMessages(),
                'conversation' => $conversation,
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
            
            return $this->render('chat/_messages.html.twig', [
                'messages' => $conversation->getMessages(),
                'conversation' => $conversation,
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
