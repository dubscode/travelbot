<?php

namespace App\Service;

use App\Entity\Destination;
use App\Entity\User;
use App\Repository\DestinationRepository;
use Psr\Log\LoggerInterface;

class TravelRecommenderService
{
    public function __construct(
        private ClaudeService $claudeService,
        private DestinationRepository $destinationRepository,
        private LoggerInterface $logger
    ) {}

    public function generateRecommendation(
        string $userMessage,
        ?User $user = null,
        array $previousMessages = [],
        bool $preferFastResponse = false
    ): array {
        // Get relevant destinations from database
        $destinations = $this->getRelevantDestinations($userMessage);
        
        // Build context-aware messages
        $messages = $this->buildMessages($userMessage, $destinations, $user, $previousMessages);
        
        return $this->claudeService->generateResponse($messages, $preferFastResponse);
    }

    public function streamRecommendation(
        string $userMessage,
        ?User $user = null,
        array $previousMessages = [],
        bool $preferFastResponse = false
    ): \Generator {
        // Get relevant destinations from database
        $destinations = $this->getRelevantDestinations($userMessage);
        
        // Build context-aware messages
        $messages = $this->buildMessages($userMessage, $destinations, $user, $previousMessages);
        
        foreach ($this->claudeService->streamResponse($messages, $preferFastResponse) as $chunk) {
            yield $chunk;
        }
    }

    private function buildMessages(
        string $userMessage,
        array $destinations,
        ?User $user = null,
        array $previousMessages = []
    ): array {
        $systemPrompt = $this->buildSystemPrompt($destinations, $user);
        
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['text' => $systemPrompt]
                ]
            ]
        ];

        // Add previous conversation context
        foreach ($previousMessages as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => [
                    ['text' => $msg['content']]
                ]
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => [
                ['text' => $userMessage]
            ]
        ];

        return $messages;
    }

    private function buildSystemPrompt(array $destinations, ?User $user = null): string
    {
        $prompt = "You are TravelBot, an expert travel advisor AI assistant specialized in personalized destination recommendations. ";
        $prompt .= "You have access to a curated database of destinations and should provide helpful, detailed, and engaging travel advice.\n\n";

        // Add user context if available
        if ($user) {
            $prompt .= "USER PROFILE:\n";
            $prompt .= "- Name: " . $user->getName() . "\n";
            
            if ($user->getBudget()) {
                $prompt .= "- Budget: $" . $user->getBudget() . " per day\n";
            }
            
            if ($user->getInterests()) {
                $prompt .= "- Interests: " . implode(', ', $user->getInterests()) . "\n";
            }
            
            if ($user->getClimatePreferences()) {
                $prompt .= "- Climate Preferences: " . implode(', ', $user->getClimatePreferences()) . "\n";
            }
            
            $prompt .= "\n";
        }

        // Add destination database context
        if (!empty($destinations)) {
            $prompt .= "AVAILABLE DESTINATIONS:\n";
            foreach ($destinations as $destination) {
                $prompt .= $this->formatDestinationForPrompt($destination);
            }
            $prompt .= "\n";
        }

        $prompt .= "GUIDELINES:\n";
        $prompt .= "- Be conversational, friendly, and enthusiastic about travel\n";
        $prompt .= "- Ask follow-up questions to better understand preferences when needed\n";
        $prompt .= "- Provide specific recommendations from the destination database when relevant\n";
        $prompt .= "- Include practical information like costs, best times to visit, and activities\n";
        $prompt .= "- Consider the user's budget and preferences when making suggestions\n";
        $prompt .= "- If asked about destinations not in your database, use your general travel knowledge\n";
        $prompt .= "- Keep responses engaging but not overly long\n";
        $prompt .= "- Always prioritize user safety and provide responsible travel advice\n\n";

        return $prompt;
    }

    private function formatDestinationForPrompt(Destination $destination): string
    {
        $text = "• {$destination->getName()}";
        
        if ($destination->getCity() && $destination->getCity() !== $destination->getName()) {
            $text .= " ({$destination->getCity()})";
        }
        
        $text .= ", {$destination->getCountry()}\n";
        $text .= "  Description: {$destination->getDescription()}\n";
        
        if ($destination->getAverageCostPerDay()) {
            $text .= "  Average cost: $" . $destination->getAverageCostPerDay() . "/day\n";
        }
        
        if ($destination->getTags()) {
            $text .= "  Tags: " . implode(', ', $destination->getTags()) . "\n";
        }
        
        if ($destination->getClimate()) {
            $text .= "  Climate: " . implode(', ', $destination->getClimate()) . "\n";
        }
        
        if ($destination->getBestMonthsToVisit()) {
            $text .= "  Best months: " . implode(', ', $destination->getBestMonthsToVisit()) . "\n";
        }
        
        if ($destination->getActivities()) {
            $text .= "  Activities: " . implode(', ', $destination->getActivities()) . "\n";
        }
        
        $text .= "\n";
        
        return $text;
    }

    private function getRelevantDestinations(string $userMessage): array
    {
        // For now, get all destinations. In a more advanced version, we could:
        // - Use vector search for semantic similarity
        // - Filter by keywords or mentioned locations
        // - Use user preferences to pre-filter
        
        $allDestinations = $this->destinationRepository->findBy([], ['popularityScore' => 'DESC'], 10);
        
        // TODO: Implement smarter filtering based on message content
        // This could include keyword matching, budget filtering, etc.
        // The $userMessage parameter is currently unused but kept for future implementation
        
        return $allDestinations;
    }

    public function shouldUseFastModel(string $userMessage): bool
    {
        // Use Sonnet by default, but use Haiku for simple/quick responses
        $simplePatterns = [
            '/^(hi|hello|hey|good morning|good afternoon)/i',
            '/^(yes|no|ok|sure|thanks|thank you)/i',
            '/^\w{1,20}$/', // Very short messages
            '/^(what|how much|when|where) /i', // Simple questions
            '/quick/i',
            '/simple/i',
            '/fast/i',
        ];

        foreach ($simplePatterns as $pattern) {
            if (preg_match($pattern, trim($userMessage))) {
                return true; // Use Haiku for simple queries
            }
        }

        return false; // Use Sonnet by default for detailed travel planning
    }

    public function generateConversationTitle(string $userMessage): ?string
    {
        try {
            $messages = [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'text' => "Generate a short, descriptive title (3-6 words max) for a travel conversation that starts with this message:\n\n\"$userMessage\"\n\nRespond with ONLY the title, no quotes, no explanation."
                        ]
                    ]
                ]
            ];

            $response = $this->claudeService->generateResponse($messages, true); // Use Haiku for speed
            $title = trim($response['content']);
            
            // Clean up any quotes or extra characters
            $title = trim($title, '"\'');
            
            // Ensure title is reasonable length (max 60 chars)
            if (strlen($title) > 60) {
                $title = substr($title, 0, 57) . '...';
            }
            
            return $title ?: null;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate conversation title: ' . $e->getMessage(), [
                'userMessage' => $userMessage,
            ]);
            
            // Fallback to simple truncation
            return strlen($userMessage) > 50 
                ? substr($userMessage, 0, 47) . '...'
                : $userMessage;
        }
    }
}
