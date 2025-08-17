<?php

namespace App\Generator;

class AmenityGenerator
{
    private array $amenityCategories = [
        'recreation' => [
            'Swimming Pool' => 'Outdoor swimming pool with sun deck and poolside service',
            'Indoor Pool' => 'Climate-controlled indoor swimming pool available year-round',
            'Hot Tub' => 'Relaxing hot tub and jacuzzi facilities for ultimate relaxation',
            'Spa' => 'Full-service spa offering massages, treatments, and wellness services',
            'Fitness Center' => 'Modern fitness center with cardio and weight training equipment',
            'Tennis Court' => 'Professional tennis court with equipment rental available',
            'Golf Course' => 'Championship golf course with pro shop and lessons',
            'Water Sports' => 'Kayaking, paddleboarding, and other water sport equipment',
            'Beach Access' => 'Direct access to private or public beach areas',
            'Marina' => 'Private marina with boat rentals and water excursions',
            'Hiking Trails' => 'Guided hiking trails and nature walks',
            'Bike Rental' => 'Bicycle rental service for exploring the local area',
            'Game Room' => 'Entertainment room with pool tables, games, and arcade',
            'Library' => 'Quiet reading room with books, magazines, and comfortable seating',
            'Sauna' => 'Traditional sauna facilities for relaxation and wellness'
        ],
        'dining' => [
            'Fine Dining Restaurant' => 'Upscale restaurant with gourmet cuisine and wine pairings',
            'Casual Dining' => 'Relaxed restaurant serving comfort food and local specialties',
            'Breakfast Buffet' => 'Extensive breakfast buffet with international and local options',
            'Room Service' => '24-hour room service with full menu availability',
            'Bar & Lounge' => 'Stylish bar serving cocktails, wines, and light appetizers',
            'Poolside Bar' => 'Outdoor bar service at the pool area with tropical drinks',
            'Coffee Shop' => 'Casual coffee shop with pastries, sandwiches, and beverages',
            'Rooftop Restaurant' => 'Elevated dining experience with panoramic city or ocean views',
            'Beachside Grill' => 'Outdoor grill serving fresh seafood and barbecue',
            'Wine Cellar' => 'Extensive wine collection with tasting experiences',
            'Sushi Bar' => 'Fresh sushi and Japanese cuisine prepared by expert chefs',
            'Steakhouse' => 'Premium steakhouse with aged beef and classic preparations',
            'Terrace Dining' => 'Outdoor terrace dining with scenic views',
            'All-Inclusive Dining' => 'Multiple dining options included in stay package',
            'Local Cuisine' => 'Authentic local and regional cuisine experiences'
        ],
        'business' => [
            'Business Center' => 'Full-service business center with computers and printing',
            'Meeting Rooms' => 'Professional meeting and conference room facilities',
            'High-Speed WiFi' => 'Complimentary high-speed wireless internet throughout property',
            'Executive Lounge' => 'Exclusive lounge for business travelers with refreshments',
            'Conference Center' => 'Large conference and event hosting facilities',
            'Secretarial Services' => 'Professional secretarial and administrative support',
            'Translation Services' => 'Multilingual translation and interpretation services',
            'Video Conferencing' => 'Modern video conferencing equipment and support',
            'Coworking Space' => 'Shared workspace with desks and business amenities',
            'Private Offices' => 'Rentable private office space for extended stays',
            'Printing Services' => 'Professional printing, copying, and binding services',
            'Fax Services' => 'Fax transmission and reception services',
            'Courier Services' => 'Package delivery and courier coordination'
        ],
        'entertainment' => [
            'Casino' => 'Full-service casino with slots, table games, and poker room',
            'Nightclub' => 'Vibrant nightclub with DJ entertainment and dancing',
            'Live Music' => 'Regular live music performances and entertainment shows',
            'Theater' => 'On-site theater hosting shows, concerts, and performances',
            'Comedy Club' => 'Comedy shows and stand-up entertainment venue',
            'Karaoke' => 'Karaoke rooms and entertainment for guests',
            'Dance Classes' => 'Dance lessons and classes for various skill levels',
            'Movie Theater' => 'Private cinema showing current films and classics',
            'Bowling Alley' => 'Professional bowling lanes with equipment rental',
            'Shopping Arcade' => 'Retail shops and boutiques for shopping convenience',
            'Art Gallery' => 'Rotating art exhibitions and cultural displays',
            'Piano Bar' => 'Intimate piano bar with live music and cocktails',
            'Sports Bar' => 'Sports viewing area with large screens and game day atmosphere'
        ],
        'family' => [
            'Kids Club' => 'Supervised children\'s activities and entertainment programs',
            'Playground' => 'Safe outdoor playground equipment for children',
            'Babysitting' => 'Professional babysitting and childcare services',
            'Family Pool' => 'Shallow pool area designed specifically for families with children',
            'Game Area' => 'Family-friendly games and activities for all ages',
            'Teen Center' => 'Dedicated space for teenage guests with age-appropriate activities',
            'Family Suites' => 'Spacious accommodations designed for families',
            'Children\'s Menu' => 'Kid-friendly dining options and children\'s meal plans',
            'Cribs Available' => 'Baby cribs and child safety equipment provided',
            'High Chairs' => 'Restaurant high chairs and child seating available',
            'Children\'s Activities' => 'Organized activities and entertainment for children',
            'Water Playground' => 'Interactive water features and splash areas for kids',
            'Mini Golf' => 'Family-friendly miniature golf course'
        ],
        'luxury' => [
            'Concierge Service' => 'Personal concierge assistance for reservations and planning',
            'Butler Service' => 'Dedicated butler service for premium guest experiences',
            'Private Beach' => 'Exclusive private beach access for resort guests only',
            'Yacht Charter' => 'Luxury yacht rental and charter services',
            'Helicopter Service' => 'Helicopter transfers and scenic tour arrangements',
            'Private Dining' => 'Exclusive private dining experiences and chef services',
            'Personal Trainer' => 'Individual fitness training and wellness coaching',
            'Luxury Spa' => 'Premium spa treatments with exclusive products and services',
            'Champagne Service' => 'Premium champagne and wine service in suites',
            'Limousine Service' => 'Luxury ground transportation and airport transfers',
            'Private Pool' => 'Exclusive private pool access for premium accommodations',
            'Gourmet Kitchen' => 'Fully equipped gourmet kitchen in luxury suites',
            'Personal Chef' => 'Private chef services for in-room or private dining',
            'VIP Check-in' => 'Expedited VIP check-in and check-out services',
            'Premium Linens' => 'Luxury bedding and premium quality linens'
        ],
        'wellness' => [
            'Yoga Classes' => 'Daily yoga sessions and meditation classes',
            'Meditation Garden' => 'Peaceful garden space designed for meditation and reflection',
            'Wellness Center' => 'Comprehensive wellness facility with health programs',
            'Massage Therapy' => 'Professional massage therapy and bodywork treatments',
            'Aromatherapy' => 'Aromatherapy treatments and essential oil therapies',
            'Detox Programs' => 'Health and detoxification programs with expert guidance',
            'Nutrition Counseling' => 'Professional nutrition advice and meal planning',
            'Pilates Studio' => 'Pilates classes and equipment for strength and flexibility',
            'Steam Room' => 'Steam room facilities for relaxation and wellness',
            'Salt Cave' => 'Therapeutic salt cave for respiratory health and relaxation',
            'Oxygen Bar' => 'Oxygen therapy treatments for wellness and rejuvenation',
            'Acupuncture' => 'Traditional acupuncture treatments by licensed practitioners',
            'Reflexology' => 'Foot reflexology and pressure point therapy services'
        ],
        'technology' => [
            'Smart Room Controls' => 'Automated room controls for lighting, temperature, and entertainment',
            'Streaming Services' => 'Netflix, Hulu, and other streaming services in rooms',
            'USB Charging Ports' => 'Convenient USB charging stations throughout the property',
            'Mobile Check-in' => 'Digital check-in and check-out via mobile app',
            'Digital Concierge' => 'AI-powered concierge services and recommendations',
            'Keyless Entry' => 'Smartphone-based room access and keyless entry',
            'Virtual Reality' => 'VR entertainment experiences and virtual tours',
            'Gaming Consoles' => 'Latest gaming consoles available in entertainment areas',
            'Wireless Speakers' => 'Bluetooth speakers for personal music streaming',
            'Tablet Services' => 'In-room tablets for services, ordering, and information'
        ],
        'transportation' => [
            'Airport Shuttle' => 'Complimentary shuttle service to and from the airport',
            'Valet Parking' => 'Professional valet parking service for guest vehicles',
            'Car Rental' => 'On-site car rental services and coordination',
            'Electric Vehicle Charging' => 'EV charging stations for electric vehicles',
            'Taxi Service' => 'Taxi coordination and transportation arrangements',
            'Local Tours' => 'Organized tours and excursions to local attractions',
            'Shuttle Service' => 'Regular shuttle to nearby shopping and dining areas',
            'Parking Garage' => 'Secure covered parking facilities',
            'Bike Sharing' => 'Bike sharing program for local transportation',
            'Public Transit Access' => 'Convenient access to public transportation systems'
        ]
    ];

    /**
     * Generate all amenities
     */
    public function generateAmenities(): array
    {
        $amenities = [];
        
        foreach ($this->amenityCategories as $type => $amenitiesInCategory) {
            foreach ($amenitiesInCategory as $name => $description) {
                $amenities[] = [
                    'name' => $name,
                    'type' => $type,
                    'description' => $description
                ];
            }
        }

        return $amenities;
    }



    public function getAmenityCountForRating(int $starRating): int
    {
        return match ($starRating) {
            1 => rand(5, 10),   // Budget hotels
            2 => rand(8, 15),   // Mid-range hotels
            3 => rand(12, 20),  // Good hotels
            4 => rand(18, 25),  // Luxury hotels
            5 => rand(25, 35),  // Ultra-luxury resorts
            default => rand(10, 15)
        };
    }


    /**
     * Get amenity categories for reference
     */
    public function getAmenityCategories(): array
    {
        return array_keys($this->amenityCategories);
    }

    /**
     * Get total count of amenities
     */
    public function getTotalAmenityCount(): int
    {
        $count = 0;
        foreach ($this->amenityCategories as $amenities) {
            $count += count($amenities);
        }
        return $count;
    }

    /**
     * Get amenities by type
     */
    public function getAmenitiesByType(string $type): array
    {
        if (!isset($this->amenityCategories[$type])) {
            return [];
        }

        $amenities = [];
        foreach ($this->amenityCategories[$type] as $name => $description) {
            $amenities[] = [
                'name' => $name,
                'type' => $type,
                'description' => $description
            ];
        }

        return $amenities;
    }
}