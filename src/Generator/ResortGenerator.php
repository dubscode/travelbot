<?php

namespace App\Generator;

use App\Entity\Destination;
use App\Entity\ResortCategory;

class ResortGenerator
{
    private array $resortTypes = [
        'Beach Resort', 'Mountain Lodge', 'Urban Hotel', 'Spa Resort', 'Golf Resort',
        'Safari Lodge', 'Ski Resort', 'Desert Resort', 'Island Resort', 'Lakeside Resort',
        'Historic Hotel', 'Boutique Hotel', 'Business Hotel', 'Family Resort', 'Adults Only Resort',
        'Eco Lodge', 'Luxury Resort', 'Budget Hotel', 'Wellness Retreat', 'Casino Resort'
    ];

    private array $adjectives = [
        'Grand', 'Royal', 'Imperial', 'Majestic', 'Elegant', 'Luxurious', 'Premium', 'Exclusive',
        'Serene', 'Tranquil', 'Paradise', 'Golden', 'Crystal', 'Azure', 'Sunset', 'Sunrise',
        'Ocean', 'Mountain', 'Garden', 'Palace', 'Crown', 'Diamond', 'Platinum', 'Elite',
        'Executive', 'Prestige', 'Heritage', 'Classic', 'Modern', 'Contemporary', 'Boutique'
    ];

    private array $nameTemplates = [
        '{adjective} {type}',
        '{location} {type}',
        'The {adjective} {location}',
        '{adjective} {location} {type}',
        '{location} {adjective} Resort',
        'Hotel {adjective} {location}',
        '{adjective} Bay Resort',
        '{location} Palace Hotel',
        'The {type} at {location}',
        '{adjective} Heights Resort'
    ];

    // Star rating distribution (more realistic with fewer 5-star)
    private array $starRatingWeights = [
        1 => 0.05,  // 5%
        2 => 0.15,  // 15%
        3 => 0.40,  // 40%
        4 => 0.30,  // 30%
        5 => 0.10   // 10%
    ];

    // Room count ranges by star rating
    private array $roomCountRanges = [
        1 => [20, 50],
        2 => [30, 80],
        3 => [50, 150],
        4 => [100, 300],
        5 => [50, 200]  // Luxury resorts often smaller but more exclusive
    ];

    // Resort categories matched to destination characteristics
    private array $categoryMapping = [
        'coastal' => ['Beach Resort', 'Island Resort', 'Ocean Resort', 'Seaside Hotel'],
        'mountain' => ['Mountain Lodge', 'Ski Resort', 'Alpine Resort', 'Highland Hotel'],
        'urban' => ['Urban Hotel', 'Business Hotel', 'Boutique Hotel', 'City Resort'],
        'desert' => ['Desert Resort', 'Oasis Hotel', 'Desert Lodge'],
        'tropical' => ['Beach Resort', 'Island Resort', 'Tropical Resort', 'Paradise Hotel'],
        'spa' => ['Spa Resort', 'Wellness Retreat', 'Health Resort'],
        'luxury' => ['Luxury Resort', 'Palace Hotel', 'Premium Resort', 'Exclusive Resort'],
        'family' => ['Family Resort', 'Family Hotel', 'Kids Resort'],
        'adventure' => ['Safari Lodge', 'Eco Lodge', 'Adventure Resort'],
        'golf' => ['Golf Resort', 'Golf Club Hotel', 'Championship Resort'],
        'casino' => ['Casino Resort', 'Gaming Hotel', 'Entertainment Resort']
    ];

    private array $generated = [];

    /**
     * Generate resorts for a destination
     */
    public function generateForDestination(Destination $destination, array $categories, ?int $targetCount = null): array
    {
        $resorts = [];
        $numResorts = $targetCount ?? rand(5, 20);
        
        // Determine destination type for appropriate resort categories
        $destinationType = $this->determineDestinationType($destination);
        
        for ($i = 0; $i < $numResorts; $i++) {
            $resort = $this->generateSingleResort($destination, $categories, $destinationType);
            if ($resort) {
                $resorts[] = $resort;
            }
        }

        return $resorts;
    }

    private function generateSingleResort(Destination $destination, array $categories, string $destinationType): ?array
    {
        $maxAttempts = 20;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $cityName = $destination->getCity() ?: $destination->getName();
            $name = $this->generateResortName($cityName, $destinationType);
            
            $key = $destination->getId() . '|' . $name;
            if (!isset($this->generated[$key])) {
                $this->generated[$key] = true;
                return $this->createResort($name, $destination, $categories, $destinationType);
            }
            $attempts++;
        }

        return null;
    }

    private function createResort(string $name, Destination $destination, array $categories, string $destinationType): array
    {
        $starRating = $this->generateStarRating();
        $category = $this->selectCategory($categories, $destinationType);
        $totalRooms = $this->generateRoomCount($starRating);
        $description = $this->generateDescription($name, $destination, $starRating, $category);

        return [
            'name' => $name,
            'starRating' => $starRating,
            'totalRooms' => $totalRooms,
            'description' => $description,
            'destination' => $destination,
            'category' => $category
        ];
    }

    private function determineDestinationType(Destination $destination): string
    {
        $tags = $destination->getTags() ?? [];
        $activities = $destination->getActivities() ?? [];
        $climate = $destination->getClimate() ?? [];
        
        // Check for coastal/beach indicators
        if (array_intersect($tags, ['coastal', 'tropical']) ||
            array_intersect($activities, ['swimming', 'surfing', 'diving', 'beach lounging'])) {
            return 'coastal';
        }
        
        // Check for mountain indicators
        if (array_intersect($tags, ['mountainous']) ||
            array_intersect($activities, ['hiking', 'mountain climbing', 'skiing'])) {
            return 'mountain';
        }
        
        // Check for urban indicators
        if (array_intersect($tags, ['urban', 'modern']) ||
            array_intersect($activities, ['museums', 'nightlife', 'shopping'])) {
            return 'urban';
        }
        
        // Check for desert indicators
        if (in_array('desert', $climate) ||
            array_intersect($tags, ['desert', 'exotic'])) {
            return 'desert';
        }
        
        // Check for luxury indicators
        if (array_intersect($tags, ['luxury', 'romantic', 'honeymoon'])) {
            return 'luxury';
        }
        
        // Check for family indicators
        if (array_intersect($tags, ['family-friendly']) ||
            array_intersect($activities, ['theme parks', 'zoos', 'family rooms'])) {
            return 'family';
        }
        
        // Check for adventure indicators
        if (array_intersect($tags, ['adventure', 'off-the-beaten-path']) ||
            array_intersect($activities, ['safari', 'eco tours', 'wildlife'])) {
            return 'adventure';
        }
        
        // Default to urban for major cities
        return 'urban';
    }

    private function generateResortName(string $cityName, string $destinationType): string
    {
        $template = $this->nameTemplates[array_rand($this->nameTemplates)];
        $adjective = $this->adjectives[array_rand($this->adjectives)];
        
        // Select appropriate resort type based on destination
        $appropriateTypes = $this->categoryMapping[$destinationType] ?? $this->resortTypes;
        $type = $appropriateTypes[array_rand($appropriateTypes)];
        
        // Generate location variations
        $locationVariations = [
            $cityName,
            $cityName . ' Bay',
            $cityName . ' Hills',
            $cityName . ' Gardens',
            $cityName . ' Heights',
            'Downtown ' . $cityName,
            $cityName . ' Center'
        ];
        $location = $locationVariations[array_rand($locationVariations)];

        $name = str_replace(
            ['{adjective}', '{type}', '{location}'],
            [$adjective, $type, $location],
            $template
        );

        return $name;
    }

    private function selectCategory(array $categories, string $destinationType): ?ResortCategory
    {
        // Filter categories based on destination type
        $appropriateCategories = array_filter($categories, function ($category) use ($destinationType) {
            $categoryName = strtolower($category->getName());
            $appropriateTypes = $this->categoryMapping[$destinationType] ?? [];
            
            foreach ($appropriateTypes as $type) {
                if (str_contains($categoryName, strtolower($type))) {
                    return true;
                }
            }
            return false;
        });

        // If no appropriate categories found, use any available
        if (empty($appropriateCategories)) {
            $appropriateCategories = $categories;
        }

        return !empty($appropriateCategories) ? 
            $appropriateCategories[array_rand($appropriateCategories)] : null;
    }

    private function generateStarRating(): int
    {
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;

        foreach ($this->starRatingWeights as $rating => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $rating;
            }
        }

        return 3; // Default
    }

    private function generateRoomCount(int $starRating): int
    {
        $range = $this->roomCountRanges[$starRating];
        return rand($range[0], $range[1]);
    }

    private function generateDescription(string $name, Destination $destination, int $starRating, ?ResortCategory $category): string
    {
        $starText = $this->getStarRatingText($starRating);
        $categoryText = $category ? strtolower($category->getName()) : 'resort';
        $cityName = $destination->getCity();
        $countryName = $destination->getCountry();

        $templates = [
            "Experience luxury at {name}, a {starText} {categoryText} in the heart of {city}, {country}. Our resort offers exceptional service and world-class amenities.",
            "{name} is a premier {starText} {categoryText} located in beautiful {city}, {country}. Enjoy unparalleled comfort and hospitality in this stunning destination.",
            "Discover the elegance of {name}, a distinguished {starText} {categoryText} in {city}, {country}. Perfect for both leisure and business travelers seeking quality accommodation.",
            "Welcome to {name}, an exceptional {starText} {categoryText} nestled in the vibrant city of {city}, {country}. Experience comfort, style, and outstanding service.",
            "{name} offers a memorable stay in {city}, {country}. This {starText} {categoryText} combines modern amenities with warm hospitality for an unforgettable experience."
        ];

        $template = $templates[array_rand($templates)];

        return str_replace(
            ['{name}', '{starText}', '{categoryText}', '{city}', '{country}'],
            [$name, $starText, $categoryText, $cityName, $countryName],
            $template
        );
    }

    private function getStarRatingText(int $starRating): string
    {
        return match ($starRating) {
            1 => 'budget-friendly',
            2 => 'comfortable',
            3 => 'quality',
            4 => 'luxury',
            5 => 'ultra-luxury',
            default => 'quality'
        };
    }

    /**
     * Generate resort categories
     */
    public function generateCategories(): array
    {
        $categories = [
            [
                'name' => 'Beach Resort',
                'description' => 'Resorts located on or near beaches, offering water sports, beach activities, and ocean views.'
            ],
            [
                'name' => 'Mountain Lodge',
                'description' => 'Mountain retreats offering hiking, skiing, and scenic mountain views in alpine settings.'
            ],
            [
                'name' => 'Urban Hotel',
                'description' => 'City-center accommodations perfect for business travelers and urban exploration.'
            ],
            [
                'name' => 'Spa Resort',
                'description' => 'Wellness-focused resorts offering spa treatments, health programs, and relaxation facilities.'
            ],
            [
                'name' => 'Golf Resort',
                'description' => 'Resorts featuring championship golf courses and golf-focused amenities and activities.'
            ],
            [
                'name' => 'Safari Lodge',
                'description' => 'Wildlife lodges offering safari experiences and close encounters with nature.'
            ],
            [
                'name' => 'Ski Resort',
                'description' => 'Winter sports resorts with ski slopes, snow activities, and mountain accommodations.'
            ],
            [
                'name' => 'Desert Resort',
                'description' => 'Unique desert accommodations offering desert adventures and stunning arid landscapes.'
            ],
            [
                'name' => 'Island Resort',
                'description' => 'Exclusive island getaways with pristine beaches and tropical paradise settings.'
            ],
            [
                'name' => 'Historic Hotel',
                'description' => 'Heritage accommodations in historic buildings with cultural significance and period charm.'
            ],
            [
                'name' => 'Boutique Hotel',
                'description' => 'Small, stylish hotels with unique character, personalized service, and distinctive design.'
            ],
            [
                'name' => 'Family Resort',
                'description' => 'Family-friendly resorts with kids clubs, family activities, and child-appropriate amenities.'
            ],
            [
                'name' => 'Adults Only Resort',
                'description' => 'Sophisticated resorts designed exclusively for adult guests seeking peaceful luxury.'
            ],
            [
                'name' => 'Eco Lodge',
                'description' => 'Environmentally sustainable accommodations focused on nature conservation and eco-tourism.'
            ],
            [
                'name' => 'Business Hotel',
                'description' => 'Corporate-focused hotels with meeting facilities, business centers, and executive amenities.'
            ]
        ];

        return $categories;
    }

    /**
     * Reset the generator state
     */
    public function reset(): void
    {
        $this->generated = [];
    }
}