<?php

namespace App\Service;

use App\Service\Trait\ArrayNormalizerTrait;

use App\Entity\User;
use App\Repository\DestinationRepository;
use App\Repository\ResortRepository;
use Psr\Log\LoggerInterface;

class RAGContextBuilder
{
    use ArrayNormalizerTrait;
    private const MAX_CONTEXT_LENGTH = 8000; // Conservative token limit for context
    private const MAX_DESTINATIONS = 8;
    private const MAX_RESORTS_PER_DESTINATION = 4;
    private const MAX_SIMILAR_DESTINATIONS = 4;

    public function __construct(
        private VectorSearchService $vectorSearchService,
        private DestinationRepository $destinationRepository,
        private ResortRepository $resortRepository,
        private LoggerInterface $logger
    ) {}

    public function buildRAGContext(array $searchResults, array $queryAnalysis, ?User $user = null): string
    {
        try {
            $context = $this->buildSystemContext();
            $context .= $this->buildUserProfileContext($user);
            $context .= $this->buildQueryContext($queryAnalysis);
            $context .= $this->buildDestinationContext($searchResults['destinations'] ?? []);
            $context .= $this->buildResortContext($searchResults['resorts'] ?? []);
            $context .= $this->buildAmenityContext($searchResults['amenities'] ?? []);
            $context .= $this->buildTemporalContext($queryAnalysis);
            $context .= $this->buildRecommendationGuidelines($queryAnalysis);
            
            // Truncate if necessary
            if (strlen($context) > self::MAX_CONTEXT_LENGTH) {
                $context = $this->truncateContext($context);
            }
            
            return $context;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build RAG context', [
                'error' => $e->getMessage(),
                'queryAnalysis' => $queryAnalysis
            ]);
            
            return $this->buildFallbackContext($user);
        }
    }

    private function buildSystemContext(): string
    {
        return "You are TravelBot, an expert AI travel advisor with access to a comprehensive database of destinations, resorts, and amenities. Your recommendations are powered by semantic search and should be personalized, practical, and engaging.\n\n";
    }

    private function buildUserProfileContext(?User $user): string
    {
        if (!$user) {
            return "";
        }

        $context = "USER PROFILE:\n";
        $context .= "- Name: " . $user->getName() . "\n";
        
        if ($user->getBudget()) {
            $context .= "- Preferred Budget: $" . $user->getBudget() . " per day\n";
        }
        
        if ($user->getInterests()) {
            $context .= "- Interests: " . implode(', ', $user->getInterests()) . "\n";
        }
        
        if ($user->getClimatePreferences()) {
            $context .= "- Climate Preferences: " . implode(', ', $user->getClimatePreferences()) . "\n";
        }
        
        return $context . "\n";
    }

    private function buildQueryContext(array $queryAnalysis): string
    {
        $context = "CURRENT QUERY ANALYSIS:\n";
        
        // Travel dates and duration
        if (!empty($queryAnalysis['travel_dates']['start_date'])) {
            $context .= "- Travel Dates: " . $queryAnalysis['travel_dates']['start_date'];
            if (!empty($queryAnalysis['travel_dates']['end_date'])) {
                $context .= " to " . $queryAnalysis['travel_dates']['end_date'];
            }
            if (!empty($queryAnalysis['travel_dates']['duration_days'])) {
                $context .= " (" . $queryAnalysis['travel_dates']['duration_days'] . " days)";
            }
            $context .= "\n";
        } elseif (!empty($queryAnalysis['travel_dates']['season'])) {
            $context .= "- Preferred Season: " . ucfirst($this->arrayToString($queryAnalysis['travel_dates']['season'])) . "\n";
        }
        
        // Budget information
        if (!empty($queryAnalysis['budget']['budget_level'])) {
            $context .= "- Budget Level: " . ucfirst($this->arrayToString($queryAnalysis['budget']['budget_level'])) . "\n";
        }
        if (!empty($queryAnalysis['budget']['max_per_day'])) {
            $context .= "- Max Daily Budget: $" . $queryAnalysis['budget']['max_per_day'] . "\n";
        }
        
        // Group information
        if (!empty($queryAnalysis['traveler_info']['group_size'])) {
            $context .= "- Group Size: " . $this->arrayToString($queryAnalysis['traveler_info']['group_size']) . " people\n";
        }
        if (!empty($queryAnalysis['traveler_info']['traveler_types'])) {
            $context .= "- Traveler Type: " . implode(', ', $this->safeGetArray($queryAnalysis['traveler_info']['traveler_types'])) . "\n";
        }
        
        // Preferences
        if (!empty($queryAnalysis['destination_preferences']['destination_type'])) {
            $context .= "- Destination Type: " . implode(', ', $this->safeGetArray($queryAnalysis['destination_preferences']['destination_type'])) . "\n";
        }
        if (!empty($queryAnalysis['activity_preferences'])) {
            $context .= "- Activity Preferences: " . implode(', ', $this->safeGetArray($queryAnalysis['activity_preferences'])) . "\n";
        }
        if (!empty($queryAnalysis['amenity_requirements'])) {
            $context .= "- Required Amenities: " . implode(', ', $this->safeGetArray($queryAnalysis['amenity_requirements'])) . "\n";
        }
        
        return $context . "\n";
    }

    private function buildDestinationContext(array $destinations): string
    {
        if (empty($destinations)) {
            return "";
        }

        $context = "RELEVANT DESTINATIONS (ranked by semantic similarity):\n";
        $count = 0;
        
        foreach ($destinations as $destination) {
            if ($count >= self::MAX_DESTINATIONS) break;
            
            $similarity = isset($destination['similarity']) ? round($destination['similarity'] * 100, 1) : 'N/A';
            
            $context .= "• {$destination['name']}";
            if (!empty($destination['city']) && $destination['city'] !== $destination['name']) {
                $context .= " ({$destination['city']})";
            }
            $context .= ", {$destination['country']} [Match: {$similarity}%]\n";
            
            if (!empty($destination['description'])) {
                $context .= "  Description: " . $this->truncateText($destination['description'], 200) . "\n";
            }
            
            // Add resorts for this destination
            $context .= $this->buildDestinationResortsContext($destination['id'] ?? null);
            
            $count++;
        }
        
        return $context . "\n";
    }

    private function buildDestinationResortsContext(?string $destinationId): string
    {
        if (!$destinationId) {
            return "";
        }

        try {
            // Get top resorts for this destination
            $resorts = $this->resortRepository->findBy(
                ['destination' => $destinationId], 
                ['starRating' => 'DESC'], 
                self::MAX_RESORTS_PER_DESTINATION
            );
            
            if (empty($resorts)) {
                return "";
            }
            
            $context = "  Featured Resorts:\n";
            foreach ($resorts as $resort) {
                $context .= "    - {$resort->getName()}";
                if ($resort->getStarRating()) {
                    $context .= " ({$resort->getStarRating()}★)";
                }
                if ($resort->getTotalRooms()) {
                    $context .= " - {$resort->getTotalRooms()} rooms";
                }
                
                // Add resort category
                if ($resort->getCategory()) {
                    $context .= " - " . $resort->getCategory()->getName();
                }
                
                $context .= "\n";
                
                // Add key amenities
                $amenities = $resort->getAmenities()->slice(0, 3);
                if (!empty($amenities)) {
                    $amenityNames = array_map(fn($amenity) => $amenity->getName(), $amenities);
                    $context .= "      Amenities: " . implode(', ', $amenityNames) . "\n";
                }
            }
            
            return $context;
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to build destination resorts context', [
                'destinationId' => $destinationId,
                'error' => $e->getMessage()
            ]);
            return "";
        }
    }

    private function buildResortContext(array $resorts): string
    {
        if (empty($resorts)) {
            return "";
        }

        $context = "RELEVANT RESORTS (semantic matches):\n";
        $count = 0;
        
        foreach ($resorts as $resort) {
            if ($count >= 6) break;
            
            $similarity = isset($resort['similarity']) ? round($resort['similarity'] * 100, 1) : 'N/A';
            
            $context .= "• {$resort['name']}";
            if (!empty($resort['destination_name'])) {
                $context .= " - {$resort['destination_name']}, {$resort['destination_country']}";
            }
            $context .= " [Match: {$similarity}%]\n";
            
            if (!empty($resort['star_rating'])) {
                $context .= "  Rating: " . $this->arrayToString($resort['star_rating']) . "★";
            }
            if (!empty($resort['total_rooms'])) {
                $context .= "  Rooms: {$resort['total_rooms']}";
            }
            if (!empty($resort['category_name'])) {
                $context .= "  Type: {$resort['category_name']}";
            }
            $context .= "\n";
            
            if (!empty($resort['description'])) {
                $context .= "  " . $this->truncateText($resort['description'], 150) . "\n";
            }
            
            $context .= "\n";
            $count++;
        }
        
        return $context;
    }

    private function buildAmenityContext(array $amenities): string
    {
        if (empty($amenities)) {
            return "";
        }

        $context = "RELEVANT AMENITIES (by preference match):\n";
        
        // Group amenities by type
        $groupedAmenities = [];
        foreach ($amenities as $amenity) {
            $type = $amenity['type'] ?? 'General';
            if (!isset($groupedAmenities[$type])) {
                $groupedAmenities[$type] = [];
            }
            $groupedAmenities[$type][] = $amenity;
        }
        
        foreach ($groupedAmenities as $type => $typeAmenities) {
            $context .= "• {$type}: ";
            $amenityNames = array_slice(array_map(fn($a) => $a['name'], $typeAmenities), 0, 4);
            $context .= implode(', ', $amenityNames);
            if (count($typeAmenities) > 4) {
                $context .= " and " . (count($typeAmenities) - 4) . " more";
            }
            $context .= "\n";
        }
        
        return $context . "\n";
    }

    private function buildTemporalContext(array $queryAnalysis): string
    {
        $context = "SEASONAL CONSIDERATIONS:\n";
        $currentMonth = (new \DateTime())->format('n');
        $currentSeason = $this->getCurrentSeason($currentMonth);
        
        $context .= "- Current Season: {$currentSeason}\n";
        
        if (!empty($queryAnalysis['travel_dates']['season'])) {
            $requestedSeason = $queryAnalysis['travel_dates']['season'];
            if ($requestedSeason !== $currentSeason) {
                $context .= "- Requested Season: {$requestedSeason}\n";
                $context .= "- Note: Consider seasonal variations in weather, pricing, and activities\n";
            }
        }
        
        if (!empty($queryAnalysis['travel_dates']['start_date'])) {
            $travelDate = new \DateTime($queryAnalysis['travel_dates']['start_date']);
            $monthsAway = $this->getMonthsUntilTravel($travelDate);
            
            if ($monthsAway > 6) {
                $context .= "- Planning Timeline: Far in advance - more options available\n";
            } elseif ($monthsAway > 2) {
                $context .= "- Planning Timeline: Good advance planning window\n";
            } elseif ($monthsAway > 0) {
                $context .= "- Planning Timeline: Short notice - limited availability\n";
            } else {
                $context .= "- Planning Timeline: Immediate travel - very limited options\n";
            }
        }
        
        return $context . "\n";
    }

    private function buildRecommendationGuidelines(array $queryAnalysis): string
    {
        $context = "RECOMMENDATION GUIDELINES:\n";
        $context .= "- Prioritize destinations/resorts with highest semantic similarity scores\n";
        $context .= "- Consider budget constraints and seasonal factors\n";
        $context .= "- Match amenities to stated requirements\n";
        $context .= "- Provide specific, actionable recommendations\n";
        $context .= "- Include practical details (costs, booking advice, best times)\n";
        
        if (!empty($queryAnalysis['query_intent'])) {
            $intent = $this->arrayToString($queryAnalysis['query_intent']);
            switch ($intent) {
                case 'immediate':
                    $context .= "- Focus on immediate availability and booking urgency\n";
                    break;
                case 'comparison':
                    $context .= "- Provide detailed comparisons between options\n";
                    break;
                case 'information':
                    $context .= "- Focus on educational content and destination details\n";
                    break;
                default:
                    $context .= "- Provide balanced recommendations with options\n";
            }
        }
        
        if (isset($queryAnalysis['traveler_info']['special_needs']) && !empty($queryAnalysis['traveler_info']['special_needs'])) {
            $context .= "- IMPORTANT: Address special needs and accessibility requirements\n";
        }
        
        return $context . "\n";
    }

    private function truncateContext(string $context): string
    {
        // Simple truncation for now - could be made smarter
        $truncated = substr($context, 0, self::MAX_CONTEXT_LENGTH - 100);
        $lastNewline = strrpos($truncated, "\n");
        
        if ($lastNewline !== false) {
            $truncated = substr($truncated, 0, $lastNewline);
        }
        
        return $truncated . "\n\n[Context truncated to fit limits]\n";
    }

    private function truncateText(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        $truncated = substr($text, 0, $maxLength - 3);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }

    private function getCurrentSeason(int $month): string
    {
        return match (true) {
            in_array($month, [12, 1, 2]) => 'Winter',
            in_array($month, [3, 4, 5]) => 'Spring',
            in_array($month, [6, 7, 8]) => 'Summer',
            in_array($month, [9, 10, 11]) => 'Fall',
            default => 'Unknown'
        };
    }

    private function getMonthsUntilTravel(\DateTime $travelDate): int
    {
        $now = new \DateTime();
        $diff = $now->diff($travelDate);
        return $diff->y * 12 + $diff->m;
    }

    private function buildFallbackContext(?User $user): string
    {
        $context = $this->buildSystemContext();
        $context .= $this->buildUserProfileContext($user);
        $context .= "FALLBACK MODE: Using basic recommendation approach due to search issues.\n\n";
        
        return $context;
    }

    public function buildSimilarDestinationsContext(array $primaryDestinations): string
    {
        if (empty($primaryDestinations)) {
            return "";
        }

        $context = "SIMILAR DESTINATIONS TO CONSIDER:\n";
        $processedCount = 0;
        
        foreach ($primaryDestinations as $destination) {
            if ($processedCount >= 2) break; // Limit to avoid context bloat
            
            try {
                $destinationEntity = $this->destinationRepository->find($destination['id']);
                if (!$destinationEntity) continue;
                
                $similar = $this->vectorSearchService->findSimilarDestinations(
                    $destinationEntity, 
                    self::MAX_SIMILAR_DESTINATIONS, 
                    0.75
                );
                
                if (!empty($similar)) {
                    $context .= "Similar to {$destination['name']}:\n";
                    foreach ($similar as $sim) {
                        $similarity = round($sim['similarity'] * 100, 1);
                        $context .= "  • {$sim['name']}, {$sim['country']} (similarity: {$similarity}%)\n";
                    }
                    $context .= "\n";
                }
                
                $processedCount++;
                
            } catch (\Exception $e) {
                $this->logger->warning('Failed to build similar destinations context', [
                    'destinationId' => $destination['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $context;
    }

    /**
     * Safely get array for implode operations, handling nested arrays from Claude AI
     */
    private function safeGetArray($value): array
    {
        if (!is_array($value)) {
            return [$value];
        }
        
        // Flatten any nested arrays and remove nulls/empties
        $result = [];
        array_walk_recursive($value, function($item) use (&$result) {
            if ($item !== null && $item !== '') {
                $result[] = $item;
            }
        });
        
        return array_unique(array_filter($result));
    }
}