<?php

namespace App\Generator;

use App\Entity\Destination;

class DestinationGenerator
{
    private array $countries = [
        'France' => ['Paris', 'Nice', 'Lyon', 'Marseille', 'Cannes', 'Bordeaux', 'Toulouse', 'Strasbourg'],
        'Japan' => ['Tokyo', 'Kyoto', 'Osaka', 'Hiroshima', 'Nara', 'Hakone', 'Takayama', 'Kanazawa'],
        'Italy' => ['Rome', 'Florence', 'Venice', 'Milan', 'Naples', 'Bologna', 'Palermo', 'Verona'],
        'Spain' => ['Madrid', 'Barcelona', 'Seville', 'Valencia', 'Granada', 'Bilbao', 'Salamanca', 'Toledo'],
        'Greece' => ['Athens', 'Santorini', 'Mykonos', 'Rhodes', 'Crete', 'Delphi', 'Meteora', 'Corfu'],
        'Thailand' => ['Bangkok', 'Chiang Mai', 'Phuket', 'Koh Samui', 'Ayutthaya', 'Krabi', 'Hua Hin', 'Pai'],
        'Mexico' => ['Mexico City', 'Cancun', 'Puerto Vallarta', 'Playa del Carmen', 'Tulum', 'Oaxaca', 'Merida', 'San Miguel de Allende'],
        'Brazil' => ['Rio de Janeiro', 'São Paulo', 'Salvador', 'Brasília', 'Florianópolis', 'Recife', 'Fortaleza', 'Manaus'],
        'India' => ['Delhi', 'Mumbai', 'Jaipur', 'Goa', 'Kerala', 'Agra', 'Varanasi', 'Udaipur'],
        'Egypt' => ['Cairo', 'Luxor', 'Aswan', 'Alexandria', 'Hurghada', 'Sharm El Sheikh', 'Dahab', 'Siwa'],
        'Turkey' => ['Istanbul', 'Cappadocia', 'Antalya', 'Bodrum', 'Pamukkale', 'Ephesus', 'Ankara', 'Izmir'],
        'Morocco' => ['Marrakech', 'Casablanca', 'Fez', 'Rabat', 'Chefchaouen', 'Essaouira', 'Meknes', 'Ouarzazate'],
        'Peru' => ['Lima', 'Cusco', 'Arequipa', 'Trujillo', 'Iquitos', 'Huacachina', 'Paracas', 'Chiclayo'],
        'Nepal' => ['Kathmandu', 'Pokhara', 'Chitwan', 'Lumbini', 'Bandipur', 'Gorkha', 'Dharan', 'Bhaktapur'],
        'Vietnam' => ['Ho Chi Minh City', 'Hanoi', 'Hoi An', 'Da Nang', 'Nha Trang', 'Sapa', 'Hue', 'Dalat'],
        'Indonesia' => ['Jakarta', 'Bali', 'Yogyakarta', 'Bandung', 'Lombok', 'Flores', 'Sumatra', 'Borneo'],
        'USA' => ['New York', 'Los Angeles', 'San Francisco', 'Las Vegas', 'Miami', 'Chicago', 'Seattle', 'Boston'],
        'Australia' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Cairns', 'Darwin'],
        'Canada' => ['Toronto', 'Vancouver', 'Montreal', 'Quebec City', 'Calgary', 'Ottawa', 'Winnipeg', 'Halifax'],
        'Argentina' => ['Buenos Aires', 'Mendoza', 'Bariloche', 'Salta', 'Córdoba', 'Ushuaia', 'Puerto Madryn', 'Rosario'],
        'Chile' => ['Santiago', 'Valparaíso', 'San Pedro de Atacama', 'Puerto Varas', 'Punta Arenas', 'La Serena', 'Viña del Mar', 'Concepción'],
        'South Africa' => ['Cape Town', 'Johannesburg', 'Durban', 'Port Elizabeth', 'Bloemfontein', 'Pretoria', 'Knysna', 'Hermanus'],
        'Kenya' => ['Nairobi', 'Mombasa', 'Nakuru', 'Eldoret', 'Kisumu', 'Malindi', 'Lamu', 'Watamu'],
        'Tanzania' => ['Dar es Salaam', 'Arusha', 'Zanzibar', 'Mwanza', 'Dodoma', 'Mbeya', 'Tanga', 'Morogoro'],
        'China' => ['Beijing', 'Shanghai', 'Xi\'an', 'Guangzhou', 'Chengdu', 'Hangzhou', 'Suzhou', 'Guilin'],
        'South Korea' => ['Seoul', 'Busan', 'Incheon', 'Daegu', 'Daejeon', 'Gwangju', 'Jeju', 'Gyeongju'],
        'New Zealand' => ['Auckland', 'Wellington', 'Christchurch', 'Queenstown', 'Rotorua', 'Dunedin', 'Tauranga', 'Hamilton'],
        'Norway' => ['Oslo', 'Bergen', 'Trondheim', 'Stavanger', 'Tromsø', 'Ålesund', 'Kristiansand', 'Bodø'],
        'Iceland' => ['Reykjavik', 'Akureyri', 'Keflavik', 'Selfoss', 'Husavik', 'Vik', 'Hofn', 'Isafjordur'],
        'Russia' => ['Moscow', 'St. Petersburg', 'Sochi', 'Kazan', 'Yekaterinburg', 'Nizhny Novgorod', 'Samara', 'Rostov-on-Don']
    ];

    private array $climateTypes = [
        'tropical' => ['hot', 'humid', 'rainy season', 'monsoon'],
        'temperate' => ['mild', 'four seasons', 'moderate rainfall'],
        'mediterranean' => ['warm summers', 'mild winters', 'dry summers'],
        'continental' => ['hot summers', 'cold winters', 'varied precipitation'],
        'desert' => ['hot days', 'cool nights', 'minimal rainfall'],
        'alpine' => ['cool summers', 'snowy winters', 'mountain climate'],
        'coastal' => ['ocean breeze', 'moderate temperatures', 'sea influence'],
        'subtropical' => ['warm', 'humid summers', 'mild winters']
    ];

    private array $activities = [
        'adventure' => ['hiking', 'mountain climbing', 'white water rafting', 'zip lining', 'bungee jumping', 'paragliding'],
        'cultural' => ['museums', 'historical sites', 'art galleries', 'architecture tours', 'local festivals', 'traditional crafts'],
        'relaxation' => ['spa treatments', 'beach lounging', 'meditation retreats', 'yoga classes', 'wellness centers'],
        'water' => ['swimming', 'snorkeling', 'diving', 'surfing', 'sailing', 'fishing', 'water skiing'],
        'wildlife' => ['safari', 'bird watching', 'marine life', 'national parks', 'nature reserves', 'eco tours'],
        'food' => ['cooking classes', 'wine tasting', 'food tours', 'local markets', 'street food', 'fine dining'],
        'nightlife' => ['bars', 'clubs', 'live music', 'theater', 'casinos', 'rooftop venues'],
        'shopping' => ['local markets', 'boutiques', 'malls', 'artisan shops', 'souvenirs', 'luxury goods'],
        'sports' => ['golf', 'tennis', 'skiing', 'cycling', 'running', 'water sports'],
        'family' => ['theme parks', 'zoos', 'aquariums', 'kid-friendly beaches', 'interactive museums']
    ];

    private array $tags = [
        'romantic', 'adventure', 'luxury', 'budget-friendly', 'family-friendly', 'solo-friendly',
        'honeymoon', 'backpacking', 'cultural', 'historical', 'modern', 'traditional',
        'urban', 'rural', 'coastal', 'mountainous', 'tropical', 'exotic',
        'peaceful', 'vibrant', 'authentic', 'touristy', 'off-the-beaten-path',
        'foodie', 'party', 'wellness', 'spiritual', 'educational', 'photogenic'
    ];

    private array $bestMonths = [
        'spring' => ['March', 'April', 'May'],
        'summer' => ['June', 'July', 'August'],
        'autumn' => ['September', 'October', 'November'],
        'winter' => ['December', 'January', 'February']
    ];

    private array $popularityWeights = [
        1 => 0.05,  // 5% very low
        2 => 0.10,  // 10% low
        3 => 0.25,  // 25% moderate
        4 => 0.35,  // 35% high
        5 => 0.25   // 25% very high
    ];

    private array $generated = [];

    /**
     * Generate diverse destination data
     */
    public function generate(int $count = 100): array
    {
        $destinations = [];
        $this->generated = [];

        for ($i = 0; $i < $count; $i++) {
            $destination = $this->generateSingleDestination();
            if ($destination) {
                $destinations[] = $destination;
            }
        }

        return $destinations;
    }

    private function generateSingleDestination(): ?array
    {
        $maxAttempts = 50;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $country = array_rand($this->countries);
            $city = $this->countries[$country][array_rand($this->countries[$country])];
            
            $key = $country . '|' . $city;
            if (!isset($this->generated[$key])) {
                $this->generated[$key] = true;
                return $this->createDestination($country, $city);
            }
            $attempts++;
        }

        return null; // Could not generate unique destination
    }

    private function createDestination(string $country, string $city): array
    {
        $climateType = array_rand($this->climateTypes);
        $climate = $this->climateTypes[$climateType];
        
        // Generate activities based on destination type
        $selectedActivities = $this->selectActivities($country, $city, $climateType);
        
        // Generate tags based on country and activities
        $selectedTags = $this->selectTags($country, $selectedActivities, $climateType);
        
        // Generate coordinates based on real geographic data
        $coordinates = $this->generateCoordinates($country);
        
        // Generate cost based on country economic level
        $cost = $this->generateCost($country);
        
        // Generate best months based on climate
        $bestMonths = $this->selectBestMonths($climateType, $coordinates['latitude']);
        
        // Generate popularity score
        $popularity = $this->generatePopularityScore($country, $city);

        return [
            'name' => $city,
            'country' => $country,
            'city' => $city,
            'description' => $this->generateDescription($city, $country, $selectedActivities, $climateType),
            'tags' => $selectedTags,
            'climate' => $climate,
            'averageCostPerDay' => $cost,
            'activities' => $selectedActivities,
            'bestMonthsToVisit' => $bestMonths,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'imageUrl' => $this->generateImageUrl($city, $country),
            'popularityScore' => $popularity
        ];
    }

    private function selectActivities(string $country, string $city, string $climateType): array
    {
        $activities = [];
        $numActivities = rand(3, 8);
        
        // Always include cultural activities
        $activities = array_merge($activities, array_slice($this->activities['cultural'], 0, 2));
        
        // Add climate-appropriate activities
        if (in_array($climateType, ['tropical', 'coastal', 'mediterranean'])) {
            $activities = array_merge($activities, array_slice($this->activities['water'], 0, 2));
        }
        
        if (in_array($climateType, ['alpine', 'continental'])) {
            $activities = array_merge($activities, array_slice($this->activities['adventure'], 0, 2));
        }
        
        // Add random activities from different categories
        $remainingCount = $numActivities - count($activities);
        $allActivities = array_merge(...array_values($this->activities));
        $randomActivities = array_rand(array_flip($allActivities), min($remainingCount, count($allActivities)));
        
        if (is_array($randomActivities)) {
            $activities = array_merge($activities, $randomActivities);
        } else {
            $activities[] = $randomActivities;
        }
        
        return array_unique($activities);
    }

    private function selectTags(string $country, array $activities, string $climateType): array
    {
        $tags = [];
        $numTags = rand(3, 6);
        
        // Add climate-based tags
        if (in_array($climateType, ['tropical', 'coastal'])) {
            $tags[] = 'tropical';
        }
        
        // Add activity-based tags
        if (array_intersect($activities, $this->activities['adventure'])) {
            $tags[] = 'adventure';
        }
        
        if (array_intersect($activities, $this->activities['cultural'])) {
            $tags[] = 'cultural';
        }
        
        // Add random tags
        $remainingCount = $numTags - count($tags);
        $availableTags = array_diff($this->tags, $tags);
        $randomTags = array_rand(array_flip($availableTags), min($remainingCount, count($availableTags)));
        
        if (is_array($randomTags)) {
            $tags = array_merge($tags, $randomTags);
        } elseif ($randomTags !== null) {
            $tags[] = $randomTags;
        }
        
        return array_unique($tags);
    }

    private function generateCoordinates(string $country): array
    {
        // Approximate coordinate ranges for countries
        $coordinateRanges = [
            'France' => ['lat' => [42.0, 51.0], 'lng' => [-5.0, 8.0]],
            'Japan' => ['lat' => [24.0, 46.0], 'lng' => [123.0, 146.0]],
            'Italy' => ['lat' => [36.0, 47.0], 'lng' => [6.0, 19.0]],
            'Spain' => ['lat' => [36.0, 44.0], 'lng' => [-9.0, 3.0]],
            'Greece' => ['lat' => [35.0, 42.0], 'lng' => [19.0, 30.0]],
            'Thailand' => ['lat' => [5.0, 21.0], 'lng' => [97.0, 106.0]],
            'Mexico' => ['lat' => [14.0, 33.0], 'lng' => [-118.0, -86.0]],
            'Brazil' => ['lat' => [-34.0, 5.0], 'lng' => [-74.0, -34.0]],
            'USA' => ['lat' => [24.0, 50.0], 'lng' => [-125.0, -66.0]],
            'Australia' => ['lat' => [-44.0, -10.0], 'lng' => [113.0, 154.0]]
        ];
        
        $range = $coordinateRanges[$country] ?? ['lat' => [-60.0, 70.0], 'lng' => [-180.0, 180.0]];
        
        return [
            'latitude' => round(
                $range['lat'][0] + mt_rand() / mt_getrandmax() * ($range['lat'][1] - $range['lat'][0]),
                6
            ),
            'longitude' => round(
                $range['lng'][0] + mt_rand() / mt_getrandmax() * ($range['lng'][1] - $range['lng'][0]),
                6
            )
        ];
    }

    private function generateCost(string $country): string
    {
        // Cost ranges based on country economic level (USD per day)
        $costRanges = [
            'high' => [150, 300],      // Expensive countries
            'medium-high' => [80, 150], // Moderately expensive
            'medium' => [40, 80],      // Moderate cost
            'low' => [20, 40]          // Budget destinations
        ];
        
        $highCostCountries = ['Norway', 'Iceland', 'Switzerland', 'Australia', 'USA', 'Canada'];
        $mediumHighCountries = ['France', 'Italy', 'Spain', 'Japan', 'South Korea', 'New Zealand'];
        $mediumCountries = ['Greece', 'Turkey', 'Mexico', 'Brazil', 'Argentina', 'Chile', 'China'];
        
        if (in_array($country, $highCostCountries)) {
            $range = $costRanges['high'];
        } elseif (in_array($country, $mediumHighCountries)) {
            $range = $costRanges['medium-high'];
        } elseif (in_array($country, $mediumCountries)) {
            $range = $costRanges['medium'];
        } else {
            $range = $costRanges['low'];
        }
        
        return (string) rand($range[0], $range[1]);
    }

    private function selectBestMonths(string $climateType, float $latitude): array
    {
        // Select seasons based on climate and hemisphere
        $isNorthernHemisphere = $latitude > 0;
        
        $seasonalMonths = match ($climateType) {
            'tropical' => ['January', 'February', 'March', 'November', 'December'],
            'desert' => $isNorthernHemisphere ? 
                ['October', 'November', 'December', 'January', 'February', 'March'] :
                ['April', 'May', 'June', 'July', 'August', 'September'],
            'alpine' => $isNorthernHemisphere ?
                ['June', 'July', 'August', 'September'] :
                ['December', 'January', 'February', 'March'],
            'mediterranean' => ['April', 'May', 'June', 'September', 'October'],
            default => ['April', 'May', 'June', 'September', 'October', 'November']
        };
        
        return array_slice($seasonalMonths, 0, rand(3, min(6, count($seasonalMonths))));
    }

    private function generatePopularityScore(string $country, string $city): int
    {
        // Major tourist destinations get higher scores
        $majorDestinations = [
            'Paris', 'London', 'New York', 'Tokyo', 'Rome', 'Barcelona', 'Amsterdam',
            'Bangkok', 'Singapore', 'Dubai', 'Istanbul', 'Beijing', 'Mumbai'
        ];
        
        if (in_array($city, $majorDestinations)) {
            return rand(8, 10);
        }
        
        // Use weighted random for other destinations
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        foreach ($this->popularityWeights as $score => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $score * 2; // Scale to 1-10
            }
        }
        
        return 5; // Default
    }

    private function generateDescription(string $city, string $country, array $activities, string $climateType): string
    {
        $templates = [
            "Discover the enchanting {city} in {country}, where {activity1} and {activity2} create unforgettable experiences in a {climate} setting.",
            "Experience the magic of {city}, {country}'s gem offering {activity1}, {activity2}, and {activity3} in a stunning {climate} environment.",
            "{city} in {country} captivates visitors with its {activity1} opportunities and {climate} atmosphere, perfect for {activity2} enthusiasts.",
            "Immerse yourself in {city}, {country}, where {climate} weather complements amazing {activity1} and {activity2} experiences.",
        ];
        
        $template = $templates[array_rand($templates)];
        $shuffledActivities = $activities;
        shuffle($shuffledActivities);
        
        return str_replace(
            ['{city}', '{country}', '{activity1}', '{activity2}', '{activity3}', '{climate}'],
            [
                $city,
                $country,
                $shuffledActivities[0] ?? 'sightseeing',
                $shuffledActivities[1] ?? 'dining',
                $shuffledActivities[2] ?? 'shopping',
                $climateType
            ],
            $template
        );
    }

    private function generateImageUrl(string $city, string $country): string
    {
        // Generate a placeholder URL (in production, you might use actual image services)
        $slug = strtolower(str_replace(' ', '-', $city));
        return "https://images.unsplash.com/photo-1234567890/destination-{$slug}?w=800&h=600&fit=crop";
    }
}