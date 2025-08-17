<?php

namespace App\Service;

use App\Service\Trait\ArrayNormalizerTrait;
use App\Entity\User;
use Psr\Log\LoggerInterface;

class SearchResultRanker
{
    use ArrayNormalizerTrait;
    private const DEFAULT_WEIGHTS = [
        'semantic_similarity' => 0.40,  // Vector similarity score
        'user_preferences' => 0.25,     // Historical user preferences  
        'popularity' => 0.15,           // General popularity/ratings
        'budget_match' => 0.10,         // Budget compatibility
        'temporal_relevance' => 0.05,   // Seasonal/timing factors
        'availability' => 0.05          // Current availability
    ];

    public function __construct(
        private TravelPreferenceTracker $preferenceTracker,
        private LoggerInterface $logger
    ) {}

    public function rankSearchResults(
        array $searchResults, 
        array $queryAnalysis, 
        ?User $user = null,
        array $customWeights = []
    ): array {
        try {
            $weights = array_merge(self::DEFAULT_WEIGHTS, $customWeights);
            $userPreferences = $user ? $this->preferenceTracker->getPersonalizedSearchWeights($user) : [];
            
            $rankedResults = [
                'destinations' => $this->rankDestinations(
                    $searchResults['destinations'] ?? [], 
                    $queryAnalysis, 
                    $userPreferences, 
                    $weights
                ),
                'resorts' => $this->rankResorts(
                    $searchResults['resorts'] ?? [], 
                    $queryAnalysis, 
                    $userPreferences, 
                    $weights
                ),
                'amenities' => $this->rankAmenities(
                    $searchResults['amenities'] ?? [], 
                    $queryAnalysis, 
                    $userPreferences, 
                    $weights
                )
            ];
            
            // Add composite scores and metadata
            $rankedResults['meta'] = [
                'ranking_weights' => $weights,
                'user_preference_influence' => !empty($userPreferences) ? 'high' : 'none',
                'total_results' => [
                    'destinations' => count($rankedResults['destinations']),
                    'resorts' => count($rankedResults['resorts']),
                    'amenities' => count($rankedResults['amenities'])
                ]
            ];
            
            return $rankedResults;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to rank search results', [
                'error' => $e->getMessage(),
                'queryAnalysis' => $queryAnalysis
            ]);
            
            return $this->getFallbackRanking($searchResults);
        }
    }

    private function rankDestinations(
        array $destinations, 
        array $queryAnalysis, 
        array $userPreferences, 
        array $weights
    ): array {
        foreach ($destinations as &$destination) {
            $scores = [
                'semantic_similarity' => $destination['similarity'] ?? 0.5,
                'user_preferences' => $this->calculateDestinationPreferenceScore($destination, $userPreferences),
                'popularity' => $this->calculatePopularityScore($destination['popularity_score'] ?? 0),
                'budget_match' => $this->calculateBudgetMatchScore($destination, $queryAnalysis),
                'temporal_relevance' => $this->calculateTemporalRelevance($destination, $queryAnalysis),
                'availability' => 0.8 // Default availability score
            ];
            
            $destination['ranking_scores'] = $scores;
            $destination['composite_score'] = $this->calculateCompositeScore($scores, $weights);
        }
        
        // Sort by composite score descending
        usort($destinations, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);
        
        return $destinations;
    }

    private function rankResorts(
        array $resorts, 
        array $queryAnalysis, 
        array $userPreferences, 
        array $weights
    ): array {
        foreach ($resorts as &$resort) {
            $scores = [
                'semantic_similarity' => $resort['similarity'] ?? 0.5,
                'user_preferences' => $this->calculateResortPreferenceScore($resort, $userPreferences),
                'popularity' => $this->calculateResortPopularityScore($resort),
                'budget_match' => $this->calculateResortBudgetMatch($resort, $queryAnalysis),
                'temporal_relevance' => $this->calculateResortTemporalRelevance($resort, $queryAnalysis),
                'availability' => $this->calculateResortAvailability($resort, $queryAnalysis)
            ];
            
            $resort['ranking_scores'] = $scores;
            $resort['composite_score'] = $this->calculateCompositeScore($scores, $weights);
        }
        
        usort($resorts, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);
        
        return $resorts;
    }

    private function rankAmenities(
        array $amenities, 
        array $queryAnalysis, 
        array $userPreferences, 
        array $weights
    ): array {
        foreach ($amenities as &$amenity) {
            $scores = [
                'semantic_similarity' => $amenity['similarity'] ?? 0.5,
                'user_preferences' => $this->calculateAmenityPreferenceScore($amenity, $userPreferences),
                'popularity' => 0.7, // Default popularity for amenities
                'budget_match' => $this->calculateAmenityBudgetRelevance($amenity, $queryAnalysis),
                'temporal_relevance' => $this->calculateAmenityTemporalRelevance($amenity, $queryAnalysis),
                'availability' => 0.9 // Amenities generally available
            ];
            
            $amenity['ranking_scores'] = $scores;
            $amenity['composite_score'] = $this->calculateCompositeScore($scores, $weights);
        }
        
        usort($amenities, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);
        
        return $amenities;
    }

    private function calculateDestinationPreferenceScore(array $destination, array $userPreferences): float
    {
        if (empty($userPreferences)) {
            return 0.5; // Neutral score when no preferences
        }
        
        $score = 0.0;
        $factors = 0;
        
        // Check destination type preferences
        if (!empty($userPreferences['destination_types'])) {
            $destinationScore = 0.0;
            // Would need destination tags/types to match against preferences
            // For now, use a default boost
            $destinationScore = 0.6;
            $score += $destinationScore;
            $factors++;
        }
        
        // Check climate preferences
        if (!empty($userPreferences['climate'])) {
            $climateScore = 0.6; // Default climate match
            $score += $climateScore;
            $factors++;
        }
        
        // Check country/region preferences from user history
        $countryScore = 0.5; // Default
        $score += $countryScore;
        $factors++;
        
        return $this->safeDivide($score, $factors, 0.5);
    }

    private function calculateResortPreferenceScore(array $resort, array $userPreferences): float
    {
        if (empty($userPreferences)) {
            return 0.5;
        }
        
        $score = 0.0;
        $factors = 0;
        
        // Star rating preferences
        if (!empty($userPreferences['accommodation']['star_ratings']) && !empty($resort['star_rating'])) {
            $starRating = $this->arrayToInt($resort['star_rating']);
            $preferenceScore = $userPreferences['accommodation']['star_ratings'][$starRating] ?? 0.3;
            $score += $preferenceScore;
            $factors++;
        }
        
        // Resort category preferences
        if (!empty($userPreferences['accommodation']['resort_categories']) && !empty($resort['category_name'])) {
            $category = strtolower($resort['category_name']);
            $categoryScore = 0.5; // Default if not found in preferences
            foreach ($userPreferences['accommodation']['resort_categories'] as $prefCategory => $weight) {
                if (strpos($category, strtolower($prefCategory)) !== false) {
                    $categoryScore = $weight;
                    break;
                }
            }
            $score += $categoryScore;
            $factors++;
        }
        
        return $this->safeDivide($score, $factors, 0.5);
    }

    private function calculateAmenityPreferenceScore(array $amenity, array $userPreferences): float
    {
        if (empty($userPreferences['amenities'])) {
            return 0.5;
        }
        
        $amenityName = strtolower($amenity['name'] ?? '');
        $amenityType = strtolower($amenity['type'] ?? '');
        
        // Direct amenity name match
        foreach ($userPreferences['amenities'] as $prefAmenity => $weight) {
            $prefAmenityLower = strtolower($prefAmenity);
            if (strpos($amenityName, $prefAmenityLower) !== false || 
                strpos($amenityType, $prefAmenityLower) !== false) {
                return $weight;
            }
        }
        
        return 0.4; // Lower default for unmatched amenities
    }

    private function calculatePopularityScore(int $popularityScore): float
    {
        // Normalize popularity score to 0-1 range
        // Assuming popularity scores range from 0-100
        return min($popularityScore / 100.0, 1.0);
    }

    private function calculateResortPopularityScore(array $resort): float
    {
        $score = 0.5; // Default
        
        // Star rating contributes to popularity
        if (!empty($resort['star_rating'])) {
            $score += ($this->arrayToInt($resort['star_rating']) / 5.0) * 0.3;
        }
        
        // Room count indicates resort size/popularity
        if (!empty($resort['total_rooms'])) {
            $roomScore = min($resort['total_rooms'] / 500.0, 1.0); // Normalize to max 500 rooms
            $score += $roomScore * 0.2;
        }
        
        return min($score, 1.0);
    }

    private function calculateBudgetMatchScore(array $destination, array $queryAnalysis): float
    {
        if (empty($queryAnalysis['budget']['max_per_day'])) {
            return 0.7; // Neutral score when no budget specified
        }
        
        $userMaxBudget = $queryAnalysis['budget']['max_per_day'];
        $destinationCost = $destination['average_cost_per_day'] ?? null;
        
        if (!$destinationCost) {
            return 0.6; // Slightly lower when cost unknown
        }
        
        $costRatio = $this->safeDivide($destinationCost, $userMaxBudget, 1.0);
        
        if ($costRatio <= 0.8) {
            return 1.0; // Well within budget
        } elseif ($costRatio <= 1.0) {
            return 0.8; // At budget limit
        } elseif ($costRatio <= 1.2) {
            return 0.4; // Slightly over budget
        } else {
            return 0.1; // Way over budget
        }
    }

    private function calculateResortBudgetMatch(array $resort, array $queryAnalysis): float
    {
        if (empty($queryAnalysis['budget']['budget_level'])) {
            return 0.7;
        }
        
        $budgetLevel = $this->arrayToString($queryAnalysis['budget']['budget_level']);
        $starRating = $this->arrayToInt($resort['star_rating'] ?? 3);
        
        // Match star rating to budget level
        $budgetStarMap = [
            'budget' => [1, 2, 3],
            'mid-range' => [3, 4],
            'luxury' => [4, 5]
        ];
        
        if (isset($budgetStarMap[$budgetLevel]) && in_array($starRating, $budgetStarMap[$budgetLevel])) {
            return 0.9;
        }
        
        return 0.4; // Mismatch penalty
    }

    private function calculateAmenityBudgetRelevance(array $amenity, array $queryAnalysis): float
    {
        // Some amenities are more relevant to certain budget levels
        $amenityType = strtolower($amenity['type'] ?? '');
        $budgetLevel = $queryAnalysis['budget']['budget_level'] ?? 'mid-range';
        
        $budgetAmenityRelevance = [
            'budget' => ['wifi', 'parking', 'breakfast'],
            'mid-range' => ['pool', 'gym', 'restaurant', 'bar'],
            'luxury' => ['spa', 'concierge', 'butler', 'private']
        ];
        
        $relevantAmenities = $budgetAmenityRelevance[$budgetLevel] ?? $budgetAmenityRelevance['mid-range'];
        
        foreach ($relevantAmenities as $relevant) {
            if (strpos($amenityType, $relevant) !== false) {
                return 0.8;
            }
        }
        
        return 0.6; // Default relevance
    }

    private function calculateTemporalRelevance(array $destination, array $queryAnalysis): float
    {
        // Check seasonal relevance
        $travelDate = $queryAnalysis['travel_dates']['start_date'] ?? null;
        $season = $queryAnalysis['travel_dates']['season'] ?? null;
        
        if (!$travelDate && !$season) {
            return 0.7; // Default when no temporal info
        }
        
        // Simple seasonal logic - could be enhanced with destination-specific data
        $currentMonth = $travelDate ? (new \DateTime($travelDate))->format('n') : date('n');
        $destinationCountry = strtolower($destination['country'] ?? '');
        
        // Basic seasonal preferences by region
        if (in_array($currentMonth, [6, 7, 8])) { // Summer
            if (strpos($destinationCountry, 'thailand') !== false || 
                strpos($destinationCountry, 'greece') !== false) {
                return 0.9; // High season
            }
        }
        
        return 0.7; // Default seasonal score
    }

    private function calculateResortTemporalRelevance(array $resort, array $queryAnalysis): float
    {
        // Resorts inherit temporal relevance from their destinations
        return $this->calculateTemporalRelevance($resort, $queryAnalysis);
    }

    private function calculateAmenityTemporalRelevance(array $amenity, array $queryAnalysis): float
    {
        $amenityName = strtolower($amenity['name'] ?? '');
        $season = $queryAnalysis['travel_dates']['season'] ?? 
                  $this->getCurrentSeason();
        
        // Seasonal amenity relevance
        $seasonalAmenities = [
            'summer' => ['pool', 'beach', 'water sport', 'outdoor'],
            'winter' => ['spa', 'indoor', 'fireplace', 'heated'],
            'spring' => ['garden', 'outdoor', 'terrace'],
            'fall' => ['spa', 'indoor', 'wellness']
        ];
        
        $relevantTerms = $seasonalAmenities[$season] ?? [];
        
        foreach ($relevantTerms as $term) {
            if (strpos($amenityName, $term) !== false) {
                return 0.8;
            }
        }
        
        return 0.6; // Default temporal relevance
    }

    private function calculateResortAvailability(array $resort, array $queryAnalysis): float
    {
        // Simplified availability logic
        $urgency = $this->arrayToString($queryAnalysis['urgency'] ?? 'flexible');
        
        switch ($urgency) {
            case 'immediate':
                return 0.6; // Lower availability for immediate bookings
            case 'planning':
                return 0.9; // High availability for planned trips
            default:
                return 0.8; // Good availability for flexible trips
        }
    }

    private function calculateCompositeScore(array $scores, array $weights): float
    {
        $compositeScore = 0.0;
        
        foreach ($scores as $criterion => $score) {
            $weight = $weights[$criterion] ?? 0.0;
            $compositeScore += $score * $weight;
        }
        
        return round($compositeScore, 3);
    }

    private function getCurrentSeason(): string
    {
        $month = (int) date('n');
        
        return match (true) {
            in_array($month, [12, 1, 2]) => 'winter',
            in_array($month, [3, 4, 5]) => 'spring',
            in_array($month, [6, 7, 8]) => 'summer',
            in_array($month, [9, 10, 11]) => 'fall',
            default => 'spring'
        };
    }

    private function getFallbackRanking(array $searchResults): array
    {
        // Simple fallback ranking by similarity scores only
        foreach (['destinations', 'resorts', 'amenities'] as $type) {
            if (!empty($searchResults[$type])) {
                usort($searchResults[$type], function($a, $b) {
                    return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
                });
            }
        }
        
        $searchResults['meta'] = [
            'ranking_method' => 'fallback_similarity_only',
            'note' => 'Using simplified ranking due to processing error'
        ];
        
        return $searchResults;
    }

    public function explainRanking(array $rankedResult): array
    {
        if (!isset($rankedResult['ranking_scores'])) {
            return ['explanation' => 'No ranking data available'];
        }
        
        $scores = $rankedResult['ranking_scores'];
        $explanation = [];
        
        foreach ($scores as $criterion => $score) {
            $explanation[$criterion] = [
                'score' => $score,
                'description' => $this->getCriterionDescription($criterion),
                'impact' => $this->getScoreImpactLevel($score)
            ];
        }
        
        $explanation['composite_score'] = $rankedResult['composite_score'] ?? 0;
        
        return $explanation;
    }

    private function getCriterionDescription(string $criterion): string
    {
        return match ($criterion) {
            'semantic_similarity' => 'How well this matches your search query',
            'user_preferences' => 'Alignment with your personal travel preferences',
            'popularity' => 'General popularity and ratings',
            'budget_match' => 'How well this fits your budget',
            'temporal_relevance' => 'Appropriateness for your travel dates/season',
            'availability' => 'Current availability and booking likelihood',
            default => 'Unknown criterion'
        };
    }

    private function getScoreImpactLevel(float $score): string
    {
        return match (true) {
            $score >= 0.8 => 'Very Positive',
            $score >= 0.6 => 'Positive',
            $score >= 0.4 => 'Neutral',
            $score >= 0.2 => 'Negative',
            default => 'Very Negative'
        };
    }
}