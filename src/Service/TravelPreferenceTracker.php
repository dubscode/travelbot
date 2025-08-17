<?php

namespace App\Service;

use App\Service\Trait\ArrayNormalizerTrait;

use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TravelPreferenceTracker
{
    use ArrayNormalizerTrait;
    private const PREFERENCE_DECAY_DAYS = 180; // Preferences lose weight after 6 months
    private const MIN_CONFIDENCE_THRESHOLD = 0.3;
    private const MAX_TRACKED_DESTINATIONS = 50;
    private const MAX_TRACKED_AMENITIES = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function trackUserPreferences(User $user, array $queryAnalysis, array $searchResults = []): void
    {
        try {
            $preferences = $this->getUserPreferences($user);
            $preferences = $this->updatePreferencesFromQuery($preferences, $queryAnalysis);
            
            if (!empty($searchResults)) {
                $preferences = $this->updatePreferencesFromResults($preferences, $searchResults);
            }
            
            $this->saveUserPreferences($user, $preferences);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track user preferences', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function trackUserInteraction(User $user, string $interactionType, array $data = []): void
    {
        try {
            $preferences = $this->getUserPreferences($user);
            
            switch ($interactionType) {
                case 'destination_click':
                    $preferences = $this->trackDestinationInteraction($preferences, $data);
                    break;
                case 'resort_view':
                    $preferences = $this->trackResortInteraction($preferences, $data);
                    break;
                case 'amenity_interest':
                    $preferences = $this->trackAmenityInteraction($preferences, $data);
                    break;
                case 'booking_intent':
                    $preferences = $this->trackBookingIntent($preferences, $data);
                    break;
            }
            
            $this->saveUserPreferences($user, $preferences);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to track user interaction', [
                'userId' => $user->getId(),
                'interactionType' => $interactionType,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getPersonalizedSearchWeights(User $user): array
    {
        $preferences = $this->getUserPreferences($user);
        
        return [
            'destination_types' => $this->getDestinationTypeWeights($preferences),
            'amenities' => $this->getAmenityWeights($preferences),
            'budget_range' => $this->getBudgetPreferences($preferences),
            'climate' => $this->getClimatePreferences($preferences),
            'activities' => $this->getActivityPreferences($preferences),
            'accommodation_types' => $this->getAccommodationPreferences($preferences)
        ];
    }

    public function getRecommendedQuestions(User $user, array $currentAnalysis): array
    {
        $preferences = $this->getUserPreferences($user);
        $questions = [];
        
        // Suggest questions based on gaps in knowledge
        if (empty($currentAnalysis['budget']['budget_level']) && !isset($preferences['avg_budget_per_day'])) {
            $questions[] = "What's your typical travel budget per day?";
        }
        
        if (empty($currentAnalysis['activity_preferences']) && empty($preferences['preferred_activities'])) {
            $questions[] = "What activities do you most enjoy when traveling?";
        }
        
        if (empty($currentAnalysis['traveler_info']['traveler_types']) && !isset($preferences['typical_group_type'])) {
            $questions[] = "Do you usually travel solo, with a partner, family, or friends?";
        }
        
        // Suggest based on strong preferences
        if (!empty($preferences['strong_climate_preference'])) {
            $climate = $preferences['strong_climate_preference'];
            if (empty($currentAnalysis['destination_preferences']['climate'])) {
                $questions[] = "Are you interested in {$climate} destinations this time as well?";
            }
        }
        
        return array_slice($questions, 0, 2); // Limit to 2 questions
    }

    private function getUserPreferences(User $user): array
    {
        // For now, we'll store preferences in User entity fields
        // In a more advanced implementation, this could be a separate PreferenceProfile entity
        
        $preferences = [
            'destination_types' => $user->getClimatePreferences() ?? [],
            'interests' => $user->getInterests() ?? [],
            'budget_history' => [],
            'amenity_preferences' => [],
            'activity_preferences' => [],
            'last_updated' => new \DateTime(),
            'confidence_scores' => [],
            'interaction_history' => []
        ];
        
        // Try to load stored preferences (could be JSON field or separate entity)
        // For now, we'll use a simple approach
        return $preferences;
    }

    private function updatePreferencesFromQuery(array $preferences, array $queryAnalysis): array
    {
        $now = new \DateTime();
        $weight = 1.0; // Fresh queries have full weight
        
        // Update destination type preferences
        if (!empty($queryAnalysis['destination_preferences']['destination_type'])) {
            foreach ($queryAnalysis['destination_preferences']['destination_type'] as $type) {
                $preferences['destination_types'][$type] = ($preferences['destination_types'][$type] ?? 0) + $weight;
            }
        }
        
        // Update climate preferences
        if (!empty($queryAnalysis['destination_preferences']['climate'])) {
            foreach ($queryAnalysis['destination_preferences']['climate'] as $climate) {
                $preferences['climate_preferences'][$climate] = ($preferences['climate_preferences'][$climate] ?? 0) + $weight;
            }
        }
        
        // Update activity preferences
        if (!empty($queryAnalysis['activity_preferences'])) {
            foreach ($queryAnalysis['activity_preferences'] as $activity) {
                $preferences['activity_preferences'][$activity] = ($preferences['activity_preferences'][$activity] ?? 0) + $weight;
            }
        }
        
        // Update amenity preferences
        if (!empty($queryAnalysis['amenity_requirements'])) {
            foreach ($queryAnalysis['amenity_requirements'] as $amenity) {
                $preferences['amenity_preferences'][$amenity] = ($preferences['amenity_preferences'][$amenity] ?? 0) + $weight;
            }
        }
        
        // Track budget patterns
        if (!empty($queryAnalysis['budget']['max_per_day'])) {
            $preferences['budget_history'][] = [
                'amount' => $queryAnalysis['budget']['max_per_day'],
                'date' => $now,
                'currency' => $queryAnalysis['budget']['currency'] ?? 'USD'
            ];
            
            // Keep only recent budget history
            $preferences['budget_history'] = array_slice($preferences['budget_history'], -10);
        }
        
        // Track group patterns
        if (!empty($queryAnalysis['traveler_info']['traveler_types'])) {
            $groupType = $queryAnalysis['traveler_info']['traveler_types'][0];
            $preferences['group_patterns'][$groupType] = ($preferences['group_patterns'][$groupType] ?? 0) + $weight;
        }
        
        $preferences['last_updated'] = $now;
        
        return $preferences;
    }

    private function updatePreferencesFromResults(array $preferences, array $searchResults): array
    {
        // Weight positive interactions with search results
        $weight = 0.5; // Implicit preferences have lower weight
        
        if (!empty($searchResults['destinations'])) {
            foreach (array_slice($searchResults['destinations'], 0, 3) as $destination) {
                // Track interest in countries
                if (!empty($destination['country'])) {
                    $preferences['country_interest'][$destination['country']] = 
                        ($preferences['country_interest'][$destination['country']] ?? 0) + $weight;
                }
            }
        }
        
        return $preferences;
    }

    private function trackDestinationInteraction(array $preferences, array $data): array
    {
        $weight = 2.0; // Explicit clicks have high weight
        
        if (!empty($data['destination_id']) && !empty($data['destination_name'])) {
            $preferences['viewed_destinations'][$data['destination_id']] = [
                'name' => $data['destination_name'],
                'country' => $data['country'] ?? null,
                'weight' => ($preferences['viewed_destinations'][$data['destination_id']]['weight'] ?? 0) + $weight,
                'last_viewed' => new \DateTime()
            ];
        }
        
        return $preferences;
    }

    private function trackResortInteraction(array $preferences, array $data): array
    {
        $weight = 1.5;
        
        if (!empty($data['resort_category'])) {
            $preferences['resort_category_interest'][$data['resort_category']] = 
                ($preferences['resort_category_interest'][$data['resort_category']] ?? 0) + $weight;
        }
        
        if (!empty($data['star_rating'])) {
            $preferences['star_rating_views'][$data['star_rating']] = 
                ($preferences['star_rating_views'][$data['star_rating']] ?? 0) + $weight;
        }
        
        return $preferences;
    }

    private function trackAmenityInteraction(array $preferences, array $data): array
    {
        $weight = 1.0;
        
        if (!empty($data['amenity_name'])) {
            $preferences['amenity_interactions'][$data['amenity_name']] = 
                ($preferences['amenity_interactions'][$data['amenity_name']] ?? 0) + $weight;
        }
        
        return $preferences;
    }

    private function trackBookingIntent(array $preferences, array $data): array
    {
        $weight = 3.0; // Booking intent is strongest signal
        
        if (!empty($data['destination_type'])) {
            $preferences['booking_intent_types'][$data['destination_type']] = 
                ($preferences['booking_intent_types'][$data['destination_type']] ?? 0) + $weight;
        }
        
        if (!empty($data['budget_range'])) {
            $preferences['booking_budget_patterns'][] = [
                'range' => $data['budget_range'],
                'date' => new \DateTime(),
                'weight' => $weight
            ];
        }
        
        return $preferences;
    }

    private function getDestinationTypeWeights(array $preferences): array
    {
        return $this->normalizeWeights($preferences['destination_types'] ?? []);
    }

    private function getAmenityWeights(array $preferences): array
    {
        $amenityWeights = array_merge(
            $preferences['amenity_preferences'] ?? [],
            $preferences['amenity_interactions'] ?? []
        );
        
        return $this->normalizeWeights($amenityWeights);
    }

    private function getBudgetPreferences(array $preferences): array
    {
        if (empty($preferences['budget_history'])) {
            return ['min' => null, 'max' => null, 'preferred' => null];
        }
        
        $amounts = array_column($preferences['budget_history'], 'amount');
        
        return [
            'min' => min($amounts),
            'max' => max($amounts),
            'preferred' => $this->calculateMedian($amounts),
            'avg' => $this->safeDivide(array_sum($amounts), count($amounts))
        ];
    }

    private function getClimatePreferences(array $preferences): array
    {
        return $this->normalizeWeights($preferences['climate_preferences'] ?? []);
    }

    private function getActivityPreferences(array $preferences): array
    {
        return $this->normalizeWeights($preferences['activity_preferences'] ?? []);
    }

    private function getAccommodationPreferences(array $preferences): array
    {
        $resortWeights = $preferences['resort_category_interest'] ?? [];
        $starRatingWeights = $preferences['star_rating_views'] ?? [];
        
        return [
            'resort_categories' => $this->normalizeWeights($resortWeights),
            'star_ratings' => $this->normalizeWeights($starRatingWeights)
        ];
    }

    private function normalizeWeights(array $weights): array
    {
        if (empty($weights)) {
            return [];
        }
        
        $max = max($weights);
        $maxFloat = $this->arrayToFloat($max);
        if ($maxFloat <= 0) {
            return [];
        }
        
        $normalized = [];
        foreach ($weights as $key => $weight) {
            $normalizedWeight = $this->safeDivide($weight, $maxFloat);
            if ($normalizedWeight >= self::MIN_CONFIDENCE_THRESHOLD) {
                $normalized[$key] = $normalizedWeight;
            }
        }
        
        // Sort by weight descending
        arsort($normalized);
        
        return $normalized;
    }

    private function calculateMedian(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        
        if ($count % 2 === 0) {
            return $this->safeDivide(($numbers[$count / 2 - 1] + $numbers[$count / 2]), 2);
        } else {
            return $numbers[floor($count / 2)];
        }
    }

    private function saveUserPreferences(User $user, array $preferences): void
    {
        // For now, update basic User entity fields
        // In production, this could be a separate UserPreferences entity or JSON field
        
        if (!empty($preferences['climate_preferences'])) {
            $topClimates = array_keys(array_slice($preferences['climate_preferences'], 0, 3, true));
            $user->setClimatePreferences($topClimates);
        }
        
        if (!empty($preferences['activity_preferences'])) {
            $topActivities = array_keys(array_slice($preferences['activity_preferences'], 0, 5, true));
            $user->setInterests($topActivities);
        }
        
        if (!empty($preferences['budget_history'])) {
            $budgetPrefs = $this->getBudgetPreferences($preferences);
            if ($budgetPrefs['preferred']) {
                $user->setBudget((string) $budgetPrefs['preferred']);
            }
        }
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function analyzeConversationHistory(User $user, int $limitDays = 90): array
    {
        try {
            $since = new \DateTime("-{$limitDays} days");
            
            $conversations = $this->entityManager->getRepository(Conversation::class)
                ->createQueryBuilder('c')
                ->where('c.user = :user')
                ->andWhere('c.updatedAt >= :since')
                ->setParameter('user', $user)
                ->setParameter('since', $since)
                ->getQuery()
                ->getResult();
            
            $patterns = [
                'frequent_topics' => [],
                'budget_mentions' => [],
                'destination_mentions' => [],
                'activity_mentions' => [],
                'conversation_count' => count($conversations)
            ];
            
            foreach ($conversations as $conversation) {
                $messages = $conversation->getMessages();
                foreach ($messages as $message) {
                    if ($message->getRole() === Message::ROLE_USER) {
                        $patterns = $this->extractPatternsFromMessage($patterns, $message->getContent());
                    }
                }
            }
            
            return $patterns;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to analyze conversation history', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return ['conversation_count' => 0];
        }
    }

    private function extractPatternsFromMessage(array $patterns, string $content): array
    {
        $content = strtolower($content);
        
        // Simple keyword extraction (could be enhanced with NLP)
        $budgetKeywords = ['budget', 'cost', 'price', 'expensive', 'cheap', '$', 'dollar'];
        $destinationKeywords = ['beach', 'mountain', 'city', 'island', 'europe', 'asia', 'tropical'];
        $activityKeywords = ['relax', 'adventure', 'culture', 'nightlife', 'spa', 'food', 'hiking'];
        
        foreach ($budgetKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $patterns['budget_mentions'][] = $keyword;
            }
        }
        
        foreach ($destinationKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $patterns['destination_mentions'][] = $keyword;
            }
        }
        
        foreach ($activityKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $patterns['activity_mentions'][] = $keyword;
            }
        }
        
        return $patterns;
    }
}