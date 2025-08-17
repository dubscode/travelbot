<?php

namespace App\Service;

use App\Service\Trait\ArrayNormalizerTrait;
use App\Entity\Destination;
use App\Entity\User;
use App\Repository\DestinationRepository;
use App\Service\AI\Providers\ClaudeService;
use App\Service\TravelQueryAnalyzer;
use App\Service\VectorSearchService;
use App\Service\SearchResultRanker;
use App\Service\RAGContextBuilder;
use App\Service\TravelPreferenceTracker;
use Psr\Log\LoggerInterface;

class TravelRecommenderService
{
    use ArrayNormalizerTrait;
    public function __construct(
        private ClaudeService $claudeService,
        private DestinationRepository $destinationRepository,
        private TravelQueryAnalyzer $queryAnalyzer,
        private VectorSearchService $vectorSearchService,
        private SearchResultRanker $resultRanker,
        private RAGContextBuilder $contextBuilder,
        private TravelPreferenceTracker $preferenceTracker,
        private LoggerInterface $logger
    ) {}

    public function generateRecommendation(
        string $userMessage,
        ?User $user = null,
        array $previousMessages = [],
        bool $preferFastResponse = false
    ): array {
        // Analyze user query to extract structured information
        $queryAnalysis = $this->queryAnalyzer->analyzeQuery($userMessage);
        
        // Track user preferences from the query
        if ($user) {
            $this->preferenceTracker->trackUserPreferences($user, $queryAnalysis);
        }
        
        // Perform multi-stage vector search
        $searchResults = $this->performRAGSearch($queryAnalysis, $user);
        
        // Build RAG context from search results
        $ragContext = $this->contextBuilder->buildRAGContext($searchResults, $queryAnalysis, $user);
        
        // Build context-aware messages with RAG context
        $messages = $this->buildRAGMessages($userMessage, $ragContext, $queryAnalysis, $user, $previousMessages);
        
        $response = $this->claudeService->generateResponse($messages, $preferFastResponse);
        
        // Track search results for preference learning
        if ($user && !empty($searchResults)) {
            $this->preferenceTracker->trackUserPreferences($user, $queryAnalysis, $searchResults);
        }
        
        return $response;
    }

    public function streamRecommendation(
        string $userMessage,
        ?User $user = null,
        array $previousMessages = [],
        bool $preferFastResponse = false
    ): \Generator {
        // Analyze user query to extract structured information
        $queryAnalysis = $this->queryAnalyzer->analyzeQuery($userMessage);
        
        // Track user preferences from the query
        if ($user) {
            $this->preferenceTracker->trackUserPreferences($user, $queryAnalysis);
        }
        
        // Perform multi-stage vector search
        $searchResults = $this->performRAGSearch($queryAnalysis, $user);
        
        // Build RAG context from search results
        $ragContext = $this->contextBuilder->buildRAGContext($searchResults, $queryAnalysis, $user);
        
        // Build context-aware messages with RAG context
        $messages = $this->buildRAGMessages($userMessage, $ragContext, $queryAnalysis, $user, $previousMessages);
        
        foreach ($this->claudeService->streamResponse($messages, $preferFastResponse) as $chunk) {
            yield $chunk;
        }
        
        // Track search results for preference learning (after streaming)
        if ($user && !empty($searchResults)) {
            $this->preferenceTracker->trackUserPreferences($user, $queryAnalysis, $searchResults);
        }
    }

    private function buildRAGMessages(
        string $userMessage,
        string $ragContext,
        array $queryAnalysis,
        ?User $user = null,
        array $previousMessages = []
    ): array {
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['text' => $ragContext]
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

        // Add current user message with query analysis context
        $enhancedUserMessage = $this->enhanceUserMessage($userMessage, $queryAnalysis);
        $messages[] = [
            'role' => 'user',
            'content' => [
                ['text' => $enhancedUserMessage]
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

    private function performRAGSearch(array $queryAnalysis, ?User $user = null): array
    {
        try {
            // Extract search terms from query analysis
            $searchTerms = $this->queryAnalyzer->extractSearchTerms($queryAnalysis);
            
            // Perform parallel vector searches
            $searchResults = [
                'destinations' => [],
                'resorts' => [],
                'amenities' => []
            ];
            
            // Search destinations
            if (!empty($searchTerms['destination'])) {
                $searchResults['destinations'] = $this->vectorSearchService->searchSimilarDestinations(
                    $searchTerms['destination'], 
                    12, // Get more for better ranking
                    0.6  // Lower threshold for broader results
                );
            }
            
            // Search resorts
            if (!empty($searchTerms['accommodation'])) {
                $searchResults['resorts'] = $this->vectorSearchService->searchSimilarResorts(
                    $searchTerms['accommodation'],
                    20,
                    0.6
                );
            }
            
            // Search amenities
            if (!empty($searchTerms['amenities'])) {
                $searchResults['amenities'] = $this->vectorSearchService->searchSimilarAmenities(
                    $searchTerms['amenities'],
                    15,
                    0.6
                );
            }
            
            // If no specific search terms, do a broader search
            if (empty($searchResults['destinations']) && empty($searchResults['resorts'])) {
                $searchResults = $this->performBroadSearch($queryAnalysis);
            }
            
            // Rank the results using multi-criteria ranking
            $rankedResults = $this->resultRanker->rankSearchResults(
                $searchResults,
                $queryAnalysis,
                $user
            );
            
            return $rankedResults;
            
        } catch (\Exception $e) {
            $this->logger->error('RAG search failed, falling back to basic search', [
                'error' => $e->getMessage(),
                'queryAnalysis' => $queryAnalysis
            ]);
            
            return $this->getFallbackResults();
        }
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

    private function enhanceUserMessage(string $userMessage, array $queryAnalysis): string
    {
        $enhancedMessage = $userMessage;
        
        // Add follow-up questions if needed
        if ($this->queryAnalyzer->shouldAskFollowUpQuestions($queryAnalysis)) {
            $followUpQuestions = $this->queryAnalyzer->generateFollowUpQuestions($queryAnalysis);
            if (!empty($followUpQuestions)) {
                $enhancedMessage .= "\n\nTo provide better recommendations, could you help with these details:\n";
                foreach ($followUpQuestions as $question) {
                    $enhancedMessage .= "- " . $question . "\n";
                }
            }
        }
        
        return $enhancedMessage;
    }

    private function performBroadSearch(array $queryAnalysis): array
    {
        // Fallback search when no specific terms are extracted
        $broadSearchTerm = "travel destination";
        
        // Enhance search term based on available analysis
        if (!empty($queryAnalysis['destination_preferences']['destination_type'])) {
            $broadSearchTerm = implode(' ', $queryAnalysis['destination_preferences']['destination_type']);
        } elseif (!empty($queryAnalysis['activity_preferences'])) {
            $broadSearchTerm = implode(' ', $queryAnalysis['activity_preferences']) . " destination";
        }
        
        return [
            'destinations' => $this->vectorSearchService->searchSimilarDestinations($broadSearchTerm, 10, 0.5),
            'resorts' => $this->vectorSearchService->searchSimilarResorts($broadSearchTerm, 15, 0.5),
            'amenities' => $this->vectorSearchService->searchSimilarAmenities($broadSearchTerm, 10, 0.5)
        ];
    }
    
    private function getFallbackResults(): array
    {
        // Simple fallback to popular destinations when vector search fails
        try {
            $popularDestinations = $this->destinationRepository->findBy(
                [], 
                ['popularityScore' => 'DESC'], 
                8
            );
            
            $fallbackResults = [
                'destinations' => [],
                'resorts' => [],
                'amenities' => []
            ];
            
            foreach ($popularDestinations as $destination) {
                $fallbackResults['destinations'][] = [
                    'id' => $destination->getId(),
                    'name' => $destination->getName(),
                    'country' => $destination->getCountry(),
                    'city' => $destination->getCity(),
                    'description' => $destination->getDescription(),
                    'popularity_score' => $destination->getPopularityScore(),
                    'similarity' => 0.5 // Default similarity for fallback
                ];
            }
            
            return $fallbackResults;
            
        } catch (\Exception $e) {
            $this->logger->error('Fallback results also failed', ['error' => $e->getMessage()]);
            return ['destinations' => [], 'resorts' => [], 'amenities' => []];
        }
    }
    
    public function getQueryAnalysis(string $userMessage): array
    {
        return $this->queryAnalyzer->analyzeQuery($userMessage);
    }
    
    public function getPersonalizedRecommendations(User $user, int $limit = 5): array
    {
        try {
            $userPreferences = $this->preferenceTracker->getPersonalizedSearchWeights($user);
            
            // Build a search based on user's historical preferences
            $searchTerms = [];
            
            if (!empty($userPreferences['destination_types'])) {
                $topDestinationTypes = array_keys(array_slice($userPreferences['destination_types'], 0, 2, true));
                $searchTerms[] = implode(' ', $topDestinationTypes);
            }
            
            if (!empty($userPreferences['activities'])) {
                $topActivities = array_keys(array_slice($userPreferences['activities'], 0, 2, true));
                $searchTerms[] = implode(' ', $topActivities);
            }
            
            if (empty($searchTerms)) {
                return $this->getFallbackResults();
            }
            
            $searchTerm = implode(' ', $searchTerms);
            $destinations = $this->vectorSearchService->searchSimilarDestinations($searchTerm, $limit, 0.6);
            
            return [
                'destinations' => $destinations,
                'based_on' => 'user_preferences',
                'search_term' => $searchTerm
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get personalized recommendations', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->getFallbackResults();
        }
    }
    
    public function trackUserInteraction(User $user, string $interactionType, array $data = []): void
    {
        $this->preferenceTracker->trackUserInteraction($user, $interactionType, $data);
    }

    public function explainRecommendation(array $searchResults, array $queryAnalysis): array
    {
        $explanation = [
            'query_understanding' => [
                'detected_preferences' => $this->extractDetectedPreferences($queryAnalysis),
                'search_strategy' => $this->getSearchStrategy($queryAnalysis)
            ],
            'search_process' => [
                'destinations_found' => count($searchResults['destinations'] ?? []),
                'resorts_found' => count($searchResults['resorts'] ?? []),
                'amenities_considered' => count($searchResults['amenities'] ?? [])
            ],
            'ranking_factors' => [
                'semantic_similarity' => 'How well options match your query',
                'user_preferences' => 'Based on your travel history',
                'popularity' => 'General ratings and reviews',
                'budget_alignment' => 'Fits within your budget constraints'
            ]
        ];
        
        // Add specific explanations for top results
        if (!empty($searchResults['destinations'])) {
            $topDestination = $searchResults['destinations'][0];
            if (isset($topDestination['ranking_scores'])) {
                $explanation['top_recommendation'] = $this->resultRanker->explainRanking($topDestination);
            }
        }
        
        return $explanation;
    }
    
    private function extractDetectedPreferences(array $queryAnalysis): array
    {
        $detected = [];
        
        if (!empty($queryAnalysis['destination_preferences']['destination_type'])) {
            $detected['destination_type'] = $queryAnalysis['destination_preferences']['destination_type'];
        }
        
        if (!empty($queryAnalysis['budget']['budget_level'])) {
            $detected['budget_level'] = $this->arrayToString($queryAnalysis['budget']['budget_level']);
        }
        
        if (!empty($queryAnalysis['activity_preferences'])) {
            $detected['activities'] = $queryAnalysis['activity_preferences'];
        }
        
        if (!empty($queryAnalysis['traveler_info']['traveler_types'])) {
            $detected['traveler_type'] = $queryAnalysis['traveler_info']['traveler_types'];
        }
        
        return $detected;
    }
    
    private function getSearchStrategy(array $queryAnalysis): string
    {
        if (!empty($queryAnalysis['destination_preferences']['specific_locations'])) {
            return 'specific_location_search';
        }
        
        if (!empty($queryAnalysis['activity_preferences'])) {
            return 'activity_based_search';
        }
        
        if (!empty($queryAnalysis['destination_preferences']['destination_type'])) {
            return 'destination_type_search';
        }
        
        return 'broad_semantic_search';
    }

    // Keep legacy method for backward compatibility during transition
    private function getRelevantDestinations(string $userMessage): array
    {
        $this->logger->warning('Using deprecated getRelevantDestinations method');
        return $this->getFallbackResults()['destinations'];
    }
}
