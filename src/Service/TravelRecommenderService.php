<?php

namespace App\Service;

use App\Entity\Destination;
use App\Entity\User;
use App\Repository\DestinationRepository;

class TravelRecommenderService
{
    public function __construct(
        private ClaudeService $claudeService,
        private DestinationRepository $destinationRepository
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
        
        yield from $this->claudeService->streamResponse($messages, $preferFastResponse);
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
        
        return $allDestinations;
    }

    public function shouldUseFastModel(string $userMessage): bool
    {
        // Use fast model (Haiku) for simple queries, Sonnet for complex travel planning
        $simplePatterns = [
            '/^(hi|hello|hey|good morning|good afternoon)/i',
            '/^(yes|no|ok|sure|thanks|thank you)/i',
            '/^\w{1,20}$/', // Very short messages
        ];

        foreach ($simplePatterns as $pattern) {
            if (preg_match($pattern, trim($userMessage))) {
                return true;
            }
        }

        return false;
    }
}
