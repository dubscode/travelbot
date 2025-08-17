<?php

namespace App\Service;

use App\Service\AI\Providers\ClaudeService;
use Psr\Log\LoggerInterface;

class TravelQueryAnalyzer
{
    public function __construct(
        private ClaudeService $claudeService,
        private LoggerInterface $logger
    ) {}

    public function analyzeQuery(string $userMessage): array
    {
        try {
            $analysisPrompt = $this->buildAnalysisPrompt($userMessage);
            
            $messages = [
                [
                    'role' => 'user',
                    'content' => [
                        ['text' => $analysisPrompt]
                    ]
                ]
            ];

            $response = $this->claudeService->generateResponse($messages, true); // Use Haiku for speed
            $content = $response['content'] ?? '';
            
            // Parse the JSON response
            $analysis = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('Failed to parse travel query analysis JSON', [
                    'content' => $content,
                    'error' => json_last_error_msg()
                ]);
                return $this->getDefaultAnalysis();
            }

            return $this->validateAndEnrichAnalysis($analysis);
            
        } catch (\Exception $e) {
            $this->logger->error('Travel query analysis failed', [
                'message' => $userMessage,
                'error' => $e->getMessage()
            ]);
            
            return $this->getDefaultAnalysis();
        }
    }

    private function buildAnalysisPrompt(string $userMessage): string
    {
        $currentDate = (new \DateTime())->format('Y-m-d');
        
        return "Analyze this travel query and extract structured information. Respond with ONLY valid JSON, no explanations.

Current date: {$currentDate}

User query: \"{$userMessage}\"

Extract the following information and respond with JSON in this exact format:
{
  \"travel_dates\": {
    \"start_date\": \"YYYY-MM-DD or null\",
    \"end_date\": \"YYYY-MM-DD or null\",
    \"flexible\": true/false,
    \"season\": \"spring/summer/fall/winter or null\",
    \"duration_days\": number or null
  },
  \"budget\": {
    \"min_per_day\": number or null,
    \"max_per_day\": number or null,
    \"total_budget\": number or null,
    \"currency\": \"USD/EUR/etc or null\",
    \"budget_level\": \"budget/mid-range/luxury or null\"
  },
  \"destination_preferences\": {
    \"destination_type\": [\"beach\", \"city\", \"mountain\", \"countryside\", \"island\", \"desert\"],
    \"climate\": [\"tropical\", \"temperate\", \"cold\", \"dry\", \"humid\"],
    \"specific_locations\": [\"location names mentioned\"],
    \"avoid_locations\": [\"locations to avoid\"]
  },
  \"traveler_info\": {
    \"group_size\": number or null,
    \"traveler_types\": [\"solo\", \"couple\", \"family\", \"friends\", \"business\"],
    \"ages\": [\"adult\", \"children\", \"elderly\"],
    \"special_needs\": [\"accessibility\", \"dietary\", \"medical\"]
  },
  \"activity_preferences\": [\"adventure\", \"relaxation\", \"culture\", \"nightlife\", \"nature\", \"wellness\", \"sports\", \"shopping\", \"food\"],
  \"amenity_requirements\": [\"spa\", \"pool\", \"gym\", \"restaurant\", \"bar\", \"wifi\", \"parking\", \"pet-friendly\", \"all-inclusive\"],
  \"accommodation_preferences\": {
    \"star_rating\": number or null,
    \"room_type\": [\"suite\", \"villa\", \"standard\", \"apartment\"],
    \"property_type\": [\"resort\", \"hotel\", \"boutique\", \"hostel\", \"vacation-rental\"]
  },
  \"urgency\": \"immediate/planning/flexible\",
  \"query_intent\": \"recommendation/information/booking/comparison\"
}

Rules:
- Use null for unknown/unmentioned values
- Use empty arrays [] for categories with no matches
- Be conservative - only extract explicitly mentioned information
- For dates, if relative terms like \"next month\" are used, calculate the actual date
- For budget, convert to USD daily rates when possible
- Extract destination types from context (e.g., \"beach vacation\" = [\"beach\"])";
    }

    private function validateAndEnrichAnalysis(array $analysis): array
    {
        // Ensure all required keys exist with proper defaults
        $defaultStructure = $this->getDefaultAnalysis();
        
        // Replace defaults with actual values to ensure structure (avoid nested arrays)
        $analysis = array_replace_recursive($defaultStructure, $analysis);
        
        // Clean up and validate date formats
        if (isset($analysis['travel_dates'])) {
            $analysis['travel_dates'] = $this->validateDates($analysis['travel_dates']);
        }
        
        // Validate budget values
        if (isset($analysis['budget'])) {
            $analysis['budget'] = $this->validateBudget($analysis['budget']);
        }
        
        // Ensure arrays are properly formatted
        $arrayFields = [
            'destination_preferences.destination_type',
            'destination_preferences.climate', 
            'destination_preferences.specific_locations',
            'destination_preferences.avoid_locations',
            'traveler_info.traveler_types',
            'traveler_info.ages',
            'traveler_info.special_needs',
            'activity_preferences',
            'amenity_requirements',
            'accommodation_preferences.room_type',
            'accommodation_preferences.property_type'
        ];
        
        foreach ($arrayFields as $field) {
            $this->ensureArrayField($analysis, $field);
        }
        
        return $analysis;
    }

    private function validateDates(array $dates): array
    {
        // Validate and clean date formats
        foreach (['start_date', 'end_date'] as $dateField) {
            if (isset($dates[$dateField]) && $dates[$dateField] !== null) {
                // Handle Claude AI returning arrays instead of strings
                if (is_array($dates[$dateField])) {
                    // If empty array, set to null
                    if (empty($dates[$dateField])) {
                        $dates[$dateField] = null;
                        continue;
                    }
                    // If non-empty array, take first element
                    $dates[$dateField] = $dates[$dateField][0];
                }
                
                // Now validate the string date
                if (is_string($dates[$dateField])) {
                    $date = \DateTime::createFromFormat('Y-m-d', $dates[$dateField]);
                    if (!$date) {
                        $dates[$dateField] = null;
                    }
                } else {
                    // If not string after array handling, set to null
                    $dates[$dateField] = null;
                }
            }
        }
        
        // Calculate duration if dates are available
        if ($dates['start_date'] && $dates['end_date']) {
            $start = new \DateTime($dates['start_date']);
            $end = new \DateTime($dates['end_date']);
            $dates['duration_days'] = $start->diff($end)->days;
        }
        
        return $dates;
    }

    private function validateBudget(array $budget): array
    {
        // Ensure numeric values
        foreach (['min_per_day', 'max_per_day', 'total_budget'] as $field) {
            if (isset($budget[$field]) && $budget[$field] !== null) {
                $budget[$field] = (float) $budget[$field];
                if ($budget[$field] <= 0) {
                    $budget[$field] = null;
                }
            }
        }
        
        // Set default currency
        if (empty($budget['currency'])) {
            $budget['currency'] = 'USD';
        }
        
        return $budget;
    }

    private function ensureArrayField(array &$data, string $dotPath): void
    {
        $keys = explode('.', $dotPath);
        $current = &$data;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
                return;
            }
            $current = &$current[$key];
        }
        
        if (!is_array($current)) {
            $current = [];
        }
    }

    private function getDefaultAnalysis(): array
    {
        return [
            'travel_dates' => [
                'start_date' => null,
                'end_date' => null,
                'flexible' => true,
                'season' => null,
                'duration_days' => null
            ],
            'budget' => [
                'min_per_day' => null,
                'max_per_day' => null,
                'total_budget' => null,
                'currency' => 'USD',
                'budget_level' => null
            ],
            'destination_preferences' => [
                'destination_type' => [],
                'climate' => [],
                'specific_locations' => [],
                'avoid_locations' => []
            ],
            'traveler_info' => [
                'group_size' => null,
                'traveler_types' => [],
                'ages' => [],
                'special_needs' => []
            ],
            'activity_preferences' => [],
            'amenity_requirements' => [],
            'accommodation_preferences' => [
                'star_rating' => null,
                'room_type' => [],
                'property_type' => []
            ],
            'urgency' => 'flexible',
            'query_intent' => 'recommendation'
        ];
    }

    public function extractSearchTerms(array $analysis): array
    {
        $searchTerms = [];
        
        // Build destination search terms
        if (!empty($analysis['destination_preferences']['destination_type'])) {
            $searchTerms['destination'] = implode(' ', $analysis['destination_preferences']['destination_type']);
        }
        
        if (!empty($analysis['destination_preferences']['climate'])) {
            $searchTerms['destination'] = ($searchTerms['destination'] ?? '') . ' ' . implode(' ', $analysis['destination_preferences']['climate']);
        }
        
        if (!empty($analysis['destination_preferences']['specific_locations'])) {
            $searchTerms['destination'] = ($searchTerms['destination'] ?? '') . ' ' . implode(' ', $analysis['destination_preferences']['specific_locations']);
        }
        
        // Build amenity search terms
        if (!empty($analysis['amenity_requirements'])) {
            $searchTerms['amenities'] = implode(' ', $analysis['amenity_requirements']);
        }
        
        // Build activity search terms
        if (!empty($analysis['activity_preferences'])) {
            $searchTerms['activities'] = implode(' ', $analysis['activity_preferences']);
        }
        
        // Build accommodation search terms
        $accommodationTerms = [];
        if (!empty($analysis['accommodation_preferences']['property_type'])) {
            $accommodationTerms = array_merge($accommodationTerms, $analysis['accommodation_preferences']['property_type']);
        }
        if (!empty($analysis['accommodation_preferences']['room_type'])) {
            $accommodationTerms = array_merge($accommodationTerms, $analysis['accommodation_preferences']['room_type']);
        }
        if (!empty($accommodationTerms)) {
            $searchTerms['accommodation'] = implode(' ', $accommodationTerms);
        }
        
        // Clean up search terms
        foreach ($searchTerms as $key => $term) {
            $searchTerms[$key] = trim($term);
            if (empty($searchTerms[$key])) {
                unset($searchTerms[$key]);
            }
        }
        
        return $searchTerms;
    }

    public function shouldAskFollowUpQuestions(array $analysis): bool
    {
        // Determine if we need more information for better recommendations
        $missingInfo = [];
        
        if (empty($analysis['travel_dates']['start_date']) && $analysis['urgency'] !== 'flexible') {
            $missingInfo[] = 'dates';
        }
        
        if (empty($analysis['budget']['budget_level']) && 
            !$analysis['budget']['max_per_day'] && 
            !$analysis['budget']['total_budget']) {
            $missingInfo[] = 'budget';
        }
        
        if (empty($analysis['destination_preferences']['destination_type']) && 
            empty($analysis['destination_preferences']['specific_locations'])) {
            $missingInfo[] = 'destination_type';
        }
        
        if (empty($analysis['traveler_info']['group_size']) && 
            empty($analysis['traveler_info']['traveler_types'])) {
            $missingInfo[] = 'group_info';
        }
        
        // Ask follow-up if missing 2 or more key pieces of info
        return count($missingInfo) >= 2;
    }

    public function generateFollowUpQuestions(array $analysis): array
    {
        $questions = [];
        
        if (empty($analysis['travel_dates']['start_date']) && $analysis['urgency'] !== 'flexible') {
            $questions[] = "When are you planning to travel?";
        }
        
        if (empty($analysis['budget']['budget_level']) && 
            !$analysis['budget']['max_per_day'] && 
            !$analysis['budget']['total_budget']) {
            $questions[] = "What's your approximate budget per day or total budget?";
        }
        
        if (empty($analysis['destination_preferences']['destination_type']) && 
            empty($analysis['destination_preferences']['specific_locations'])) {
            $questions[] = "What type of destination interests you most - beach, city, mountains, or somewhere else?";
        }
        
        if (empty($analysis['traveler_info']['group_size'])) {
            $questions[] = "How many people will be traveling?";
        }
        
        if (empty($analysis['activity_preferences'])) {
            $questions[] = "What activities interest you most - relaxation, adventure, culture, or nightlife?";
        }
        
        // Return up to 2 most important questions
        return array_slice($questions, 0, 2);
    }
}