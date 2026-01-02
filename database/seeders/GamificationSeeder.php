<?php

namespace Database\Seeders;

use App\Models\Gamification\Badge;
use App\Models\Gamification\ExperienceAction;
use App\Models\Gamification\ExperienceConfig;
use App\Models\Gamification\Reward;
use Illuminate\Database\Seeder;

/**
 * Comprehensive Gamification Seeder
 *
 * Seeds all gamification data including:
 * - Experience Configuration (levels, XP progression)
 * - Experience Actions (XP earning rules)
 * - Badges (achievements)
 * - Rewards (redeemable with points)
 *
 * Usage:
 *   # Seed for a specific marketplace (recommended)
 *   MARKETPLACE_ID=1 php artisan db:seed --class=GamificationSeeder
 *
 *   # Seed for a specific tenant
 *   TENANT_ID=1 php artisan db:seed --class=GamificationSeeder
 *
 *   # Global (no scoping)
 *   php artisan db:seed --class=GamificationSeeder
 */
class GamificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get IDs from environment variables
        $marketplaceClientId = env('MARKETPLACE_ID') ? (int) env('MARKETPLACE_ID') : null;
        $tenantId = env('TENANT_ID') ? (int) env('TENANT_ID') : null;

        $this->command->info('Seeding gamification data...');
        $this->command->info("  Marketplace ID: " . ($marketplaceClientId ?? 'null'));
        $this->command->info("  Tenant ID: " . ($tenantId ?? 'null'));

        // Seed in order of dependencies
        $this->seedExperienceConfig($tenantId, $marketplaceClientId);
        $this->seedExperienceActions($tenantId, $marketplaceClientId);
        $this->seedBadges($tenantId, $marketplaceClientId);
        $this->seedRewards($tenantId, $marketplaceClientId);

        $this->command->info('Gamification seeding complete!');
    }

    /**
     * Seed Experience Configuration (Levels & XP System)
     */
    protected function seedExperienceConfig(?int $tenantId, ?int $marketplaceClientId): void
    {
        $this->command->info('  - Seeding Experience Configuration...');

        $config = ExperienceConfig::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'marketplace_client_id' => $marketplaceClientId,
            ],
            [
                'xp_name' => [
                    'en' => 'Experience Points',
                    'ro' => 'Puncte Experiență',
                ],
                'level_name' => [
                    'en' => 'Level',
                    'ro' => 'Nivel',
                ],
                'icon' => 'star',
                'level_formula' => 'exponential',
                'base_xp_per_level' => 100,
                'level_multiplier' => 1.3,
                'max_level' => 50,
                'level_groups' => [
                    [
                        'name' => 'Newbie',
                        'name_ro' => 'Începător',
                        'min_level' => 1,
                        'max_level' => 5,
                        'color' => '#9CA3AF',
                        'emoji' => '🎵',
                        'description_en' => 'Just getting started',
                        'description_ro' => 'Abia începi aventura',
                        'xp_range' => '0 - 500',
                    ],
                    [
                        'name' => 'Music Lover',
                        'name_ro' => 'Iubitor de Muzică',
                        'min_level' => 6,
                        'max_level' => 10,
                        'color' => '#3B82F6',
                        'emoji' => '🎶',
                        'description_en' => 'Discovering the scene',
                        'description_ro' => 'Descoperi scena muzicală',
                        'xp_range' => '500 - 1,500',
                    ],
                    [
                        'name' => 'Rock Star',
                        'name_ro' => 'Stea Rock',
                        'min_level' => 11,
                        'max_level' => 15,
                        'color' => '#A51C30',
                        'emoji' => '🎸',
                        'description_en' => 'Making waves',
                        'description_ro' => 'Faci valuri în comunitate',
                        'xp_range' => '1,500 - 4,000',
                    ],
                    [
                        'name' => 'Legend',
                        'name_ro' => 'Legendă',
                        'min_level' => 16,
                        'max_level' => 20,
                        'color' => '#8B5CF6',
                        'emoji' => '👑',
                        'description_en' => 'A true veteran',
                        'description_ro' => 'Un adevărat veteran',
                        'xp_range' => '4,000 - 8,000',
                    ],
                    [
                        'name' => 'Hall of Fame',
                        'name_ro' => 'Panteonul Faimei',
                        'min_level' => 21,
                        'max_level' => 50,
                        'color' => '#F59E0B',
                        'emoji' => '🏆',
                        'description_en' => 'Elite status achieved',
                        'description_ro' => 'Ai atins statutul de elită',
                        'xp_range' => '8,000+',
                    ],
                ],
                'level_rewards' => [
                    [
                        'level' => 5,
                        'bonus_points' => 100,
                        'reward_name_en' => 'Newbie Completion Bonus',
                        'reward_name_ro' => 'Bonus Completare Începător',
                    ],
                    [
                        'level' => 10,
                        'bonus_points' => 250,
                        'reward_name_en' => '10 RON Discount Unlocked',
                        'reward_name_ro' => 'Reducere 10 lei deblocată',
                    ],
                    [
                        'level' => 15,
                        'bonus_points' => 500,
                        'reward_name_en' => 'VIP Upgrade & 25 RON Discount',
                        'reward_name_ro' => 'Upgrade VIP și reducere 25 lei',
                    ],
                    [
                        'level' => 20,
                        'bonus_points' => 1000,
                        'reward_name_en' => 'Meet & Greet Access',
                        'reward_name_ro' => 'Acces Meet & Greet',
                    ],
                    [
                        'level' => 25,
                        'bonus_points' => 2000,
                        'reward_name_en' => 'Gold Member Status',
                        'reward_name_ro' => 'Status Gold Member',
                    ],
                ],
                'is_active' => true,
            ]
        );

        $this->command->info("    Created Experience Config ID: {$config->id}");
    }

    /**
     * Seed Experience Actions (XP Earning Rules)
     */
    protected function seedExperienceActions(?int $tenantId, ?int $marketplaceClientId): void
    {
        $this->command->info('  - Seeding Experience Actions...');

        $actions = [
            // Ticket Purchase - 2 XP per RON spent
            [
                'action_type' => ExperienceAction::ACTION_TICKET_PURCHASE,
                'name' => [
                    'en' => 'Ticket Purchase',
                    'ro' => 'Cumpărare Bilet',
                ],
                'description' => [
                    'en' => 'Earn XP for every RON spent on tickets',
                    'ro' => 'Câștigă XP pentru fiecare leu cheltuit pe bilete',
                ],
                'xp_type' => 'per_currency',
                'xp_amount' => 0,
                'xp_per_currency_unit' => 2.0,
                'max_xp_per_action' => 1000,
                'max_times_per_day' => null,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // Event Check-in - 50 XP fixed
            [
                'action_type' => ExperienceAction::ACTION_EVENT_CHECKIN,
                'name' => [
                    'en' => 'Event Check-in',
                    'ro' => 'Check-in Eveniment',
                ],
                'description' => [
                    'en' => 'Earn XP when you check in at an event',
                    'ro' => 'Câștigă XP când faci check-in la eveniment',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 50,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 3,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // Review Submitted - 30 XP fixed
            [
                'action_type' => ExperienceAction::ACTION_REVIEW_SUBMITTED,
                'name' => [
                    'en' => 'Submit Review',
                    'ro' => 'Lasă o Recenzie',
                ],
                'description' => [
                    'en' => 'Earn XP for reviewing events you attended',
                    'ro' => 'Câștigă XP pentru recenzii la evenimentele la care ai participat',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 30,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 5,
                'cooldown_hours' => 24,
                'is_active' => true,
            ],
            // Referral Conversion - 100 XP fixed
            [
                'action_type' => ExperienceAction::ACTION_REFERRAL_CONVERSION,
                'name' => [
                    'en' => 'Friend Referral',
                    'ro' => 'Invită Prieteni',
                ],
                'description' => [
                    'en' => 'Earn XP when a friend you referred makes their first purchase',
                    'ro' => 'Câștigă XP când un prieten invitat face prima achiziție',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 100,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 10,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // Profile Complete - 20 XP fixed (one-time)
            [
                'action_type' => ExperienceAction::ACTION_PROFILE_COMPLETE,
                'name' => [
                    'en' => 'Complete Profile',
                    'ro' => 'Completează Profilul',
                ],
                'description' => [
                    'en' => 'One-time XP bonus for completing your profile',
                    'ro' => 'Bonus XP unic pentru completarea profilului',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 20,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 1,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // First Purchase - 50 XP bonus (one-time)
            [
                'action_type' => ExperienceAction::ACTION_FIRST_PURCHASE,
                'name' => [
                    'en' => 'First Purchase Bonus',
                    'ro' => 'Bonus Prima Achiziție',
                ],
                'description' => [
                    'en' => 'Welcome bonus for your first ticket purchase',
                    'ro' => 'Bonus de bun venit pentru prima achiziție de bilet',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 50,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 1,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // Social Share - 10 XP
            [
                'action_type' => ExperienceAction::ACTION_SOCIAL_SHARE,
                'name' => [
                    'en' => 'Social Share',
                    'ro' => 'Distribuie pe Social Media',
                ],
                'description' => [
                    'en' => 'Share an event on social media',
                    'ro' => 'Distribuie un eveniment pe rețelele sociale',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 10,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 3,
                'cooldown_hours' => 4,
                'is_active' => true,
            ],
            // Wishlist Add - 5 XP
            [
                'action_type' => ExperienceAction::ACTION_WISHLIST_ADD,
                'name' => [
                    'en' => 'Add to Wishlist',
                    'ro' => 'Adaugă la Favorite',
                ],
                'description' => [
                    'en' => 'Add an event to your wishlist',
                    'ro' => 'Adaugă un eveniment la favorite',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 5,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 10,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // Newsletter Subscribe - 15 XP (one-time)
            [
                'action_type' => 'newsletter_subscribe',
                'name' => [
                    'en' => 'Newsletter Subscribe',
                    'ro' => 'Abonare Newsletter',
                ],
                'description' => [
                    'en' => 'Subscribe to our newsletter',
                    'ro' => 'Abonează-te la newsletter',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 15,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 1,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // App Install - 25 XP (one-time)
            [
                'action_type' => 'app_install',
                'name' => [
                    'en' => 'App Install',
                    'ro' => 'Instalare Aplicație',
                ],
                'description' => [
                    'en' => 'Install our mobile app',
                    'ro' => 'Instalează aplicația mobilă',
                ],
                'xp_type' => 'fixed',
                'xp_amount' => 25,
                'xp_per_currency_unit' => null,
                'max_xp_per_action' => null,
                'max_times_per_day' => 1,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // Early Bird Purchase - Multiplier bonus
            [
                'action_type' => 'early_bird_purchase',
                'name' => [
                    'en' => 'Early Bird Purchase',
                    'ro' => 'Achiziție Early Bird',
                ],
                'description' => [
                    'en' => 'Extra XP for purchasing early bird tickets',
                    'ro' => 'XP extra pentru achiziția de bilete early bird',
                ],
                'xp_type' => 'multiplier',
                'xp_amount' => 0,
                'xp_per_currency_unit' => 1.5, // 1.5x multiplier
                'max_xp_per_action' => 500,
                'max_times_per_day' => null,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
            // VIP Purchase - Higher XP multiplier
            [
                'action_type' => 'vip_purchase',
                'name' => [
                    'en' => 'VIP Ticket Purchase',
                    'ro' => 'Achiziție Bilet VIP',
                ],
                'description' => [
                    'en' => 'Bonus XP for VIP ticket purchases',
                    'ro' => 'XP bonus pentru achiziții de bilete VIP',
                ],
                'xp_type' => 'multiplier',
                'xp_amount' => 0,
                'xp_per_currency_unit' => 2.0, // 2x multiplier
                'max_xp_per_action' => 1000,
                'max_times_per_day' => null,
                'cooldown_hours' => null,
                'is_active' => true,
            ],
        ];

        foreach ($actions as $actionData) {
            $action = ExperienceAction::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'marketplace_client_id' => $marketplaceClientId,
                    'action_type' => $actionData['action_type'],
                ],
                $actionData
            );
            $this->command->info("    Created Action: {$actionData['action_type']} (ID: {$action->id})");
        }
    }

    /**
     * Seed Badges (Achievements)
     */
    protected function seedBadges(?int $tenantId, ?int $marketplaceClientId): void
    {
        $this->command->info('  - Seeding Badges...');

        $badges = [
            // ==========================================
            // MILESTONE BADGES
            // ==========================================
            [
                'slug' => 'first-timer',
                'name' => ['en' => 'First Timer', 'ro' => 'Prima Dată'],
                'description' => ['en' => 'Your first ticket purchase', 'ro' => 'Prima ta achiziție de bilet'],
                'icon_url' => null,
                'icon_emoji' => '⭐',
                'color' => '#F59E0B',
                'category' => Badge::CATEGORY_MILESTONE,
                'xp_reward' => 50,
                'bonus_points' => 25,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'tickets_purchased',
                    'operator' => '>=',
                    'value' => 1,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 1,
                'sort_order' => 1,
            ],
            [
                'slug' => 'rock-veteran',
                'name' => ['en' => 'Rock Veteran', 'ro' => 'Veteran Rock'],
                'description' => ['en' => '10+ rock concerts attended', 'ro' => '10+ concerte rock'],
                'icon_url' => null,
                'icon_emoji' => '🎸',
                'color' => '#EF4444',
                'category' => Badge::CATEGORY_MILESTONE,
                'xp_reward' => 200,
                'bonus_points' => 100,
                'conditions' => [
                    'type' => 'compound',
                    'operator' => 'AND',
                    'rules' => [
                        ['metric' => 'events_attended', 'operator' => '>=', 'value' => 10],
                        ['metric' => 'genre_attendance', 'operator' => '>=', 'value' => 10, 'params' => ['genre_slug' => 'rock']],
                    ],
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 3,
                'sort_order' => 10,
            ],
            [
                'slug' => 'champion',
                'name' => ['en' => 'Champion', 'ro' => 'Campion'],
                'description' => ['en' => '50+ events attended', 'ro' => '50+ evenimente'],
                'icon_url' => null,
                'icon_emoji' => '🏆',
                'color' => '#F59E0B',
                'category' => Badge::CATEGORY_MILESTONE,
                'xp_reward' => 500,
                'bonus_points' => 250,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'events_attended',
                    'operator' => '>=',
                    'value' => 50,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 5,
                'sort_order' => 20,
            ],

            // ==========================================
            // ACTIVITY BADGES
            // ==========================================
            [
                'slug' => 'early-bird',
                'name' => ['en' => 'Early Bird', 'ro' => 'Early Bird'],
                'description' => ['en' => '5+ early bird tickets purchased', 'ro' => '5+ bilete early bird'],
                'icon_url' => null,
                'icon_emoji' => '🌟',
                'color' => '#8B5CF6',
                'category' => Badge::CATEGORY_ACTIVITY,
                'xp_reward' => 150,
                'bonus_points' => 75,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'early_bird_purchases',
                    'operator' => '>=',
                    'value' => 5,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 30,
            ],
            [
                'slug' => 'vip-lover',
                'name' => ['en' => 'VIP Lover', 'ro' => 'Iubitor VIP'],
                'description' => ['en' => '3+ VIP tickets purchased', 'ro' => '3+ bilete VIP'],
                'icon_url' => null,
                'icon_emoji' => '💎',
                'color' => '#10B981',
                'category' => Badge::CATEGORY_ACTIVITY,
                'xp_reward' => 300,
                'bonus_points' => 150,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'vip_purchases',
                    'operator' => '>=',
                    'value' => 3,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 3,
                'sort_order' => 35,
            ],
            [
                'slug' => 'festival-fan',
                'name' => ['en' => 'Festival Fan', 'ro' => 'Fan Festival'],
                'description' => ['en' => '3+ festivals attended', 'ro' => '3+ festivaluri'],
                'icon_url' => null,
                'icon_emoji' => '🎪',
                'color' => '#06B6D4',
                'category' => Badge::CATEGORY_ACTIVITY,
                'xp_reward' => 250,
                'bonus_points' => 125,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'category_attendance',
                    'operator' => '>=',
                    'value' => 3,
                    'params' => ['category_slug' => 'festivaluri'],
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 3,
                'sort_order' => 40,
            ],
            [
                'slug' => 'eclectic',
                'name' => ['en' => 'Eclectic', 'ro' => 'Eclectic'],
                'description' => ['en' => '5+ different genres attended', 'ro' => '5+ genuri diferite'],
                'icon_url' => null,
                'icon_emoji' => '🎭',
                'color' => '#6366F1',
                'category' => Badge::CATEGORY_ACTIVITY,
                'xp_reward' => 200,
                'bonus_points' => 100,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'unique_genres_attended',
                    'operator' => '>=',
                    'value' => 5,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 45,
            ],
            [
                'slug' => 'explorer',
                'name' => ['en' => 'Explorer', 'ro' => 'Explorator'],
                'description' => ['en' => '10+ different cities visited', 'ro' => '10+ orașe diferite'],
                'icon_url' => null,
                'icon_emoji' => '🌍',
                'color' => '#22C55E',
                'category' => Badge::CATEGORY_ACTIVITY,
                'xp_reward' => 300,
                'bonus_points' => 150,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'unique_cities_visited',
                    'operator' => '>=',
                    'value' => 10,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 4,
                'sort_order' => 50,
            ],
            [
                'slug' => 'night-owl',
                'name' => ['en' => 'Night Owl', 'ro' => 'Bufniță de Noapte'],
                'description' => ['en' => '10+ late night events attended', 'ro' => '10+ evenimente nocturne'],
                'icon_url' => null,
                'icon_emoji' => '🦉',
                'color' => '#312E81',
                'category' => Badge::CATEGORY_ACTIVITY,
                'xp_reward' => 150,
                'bonus_points' => 75,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'late_night_events',
                    'operator' => '>=',
                    'value' => 10,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 55,
            ],

            // ==========================================
            // LOYALTY BADGES
            // ==========================================
            [
                'slug' => 'loyal-fan',
                'name' => ['en' => 'Loyal Fan', 'ro' => 'Fan Loial'],
                'description' => ['en' => '1 year on the platform', 'ro' => '1 an pe platformă'],
                'icon_url' => null,
                'icon_emoji' => '❤️',
                'color' => '#EC4899',
                'category' => Badge::CATEGORY_LOYALTY,
                'xp_reward' => 500,
                'bonus_points' => 250,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'account_age_days',
                    'operator' => '>=',
                    'value' => 365,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 4,
                'sort_order' => 60,
            ],
            [
                'slug' => 'anniversary-2',
                'name' => ['en' => '2 Year Anniversary', 'ro' => 'Aniversare 2 Ani'],
                'description' => ['en' => '2 years on the platform', 'ro' => '2 ani pe platformă'],
                'icon_url' => null,
                'icon_emoji' => '🎂',
                'color' => '#F472B6',
                'category' => Badge::CATEGORY_LOYALTY,
                'xp_reward' => 750,
                'bonus_points' => 400,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'account_age_days',
                    'operator' => '>=',
                    'value' => 730,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 4,
                'sort_order' => 65,
            ],
            [
                'slug' => 'comeback-kid',
                'name' => ['en' => 'Comeback Kid', 'ro' => 'Revenire Triumfală'],
                'description' => ['en' => 'Returned after 6+ months', 'ro' => 'Ai revenit după 6+ luni'],
                'icon_url' => null,
                'icon_emoji' => '🔄',
                'color' => '#14B8A6',
                'category' => Badge::CATEGORY_LOYALTY,
                'xp_reward' => 100,
                'bonus_points' => 50,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'comeback_after_days',
                    'operator' => '>=',
                    'value' => 180,
                ],
                'is_secret' => true,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 70,
            ],

            // ==========================================
            // SOCIAL BADGES
            // ==========================================
            [
                'slug' => 'social-butterfly',
                'name' => ['en' => 'Social Butterfly', 'ro' => 'Sociabil'],
                'description' => ['en' => 'Invited 5 friends', 'ro' => 'Ai invitat 5 prieteni'],
                'icon_url' => null,
                'icon_emoji' => '👥',
                'color' => '#3B82F6',
                'category' => Badge::CATEGORY_SOCIAL,
                'xp_reward' => 200,
                'bonus_points' => 100,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'referrals_converted',
                    'operator' => '>=',
                    'value' => 5,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 3,
                'sort_order' => 75,
            ],
            [
                'slug' => 'influencer',
                'name' => ['en' => 'Influencer', 'ro' => 'Influencer'],
                'description' => ['en' => 'Invited 20 friends', 'ro' => 'Ai invitat 20 de prieteni'],
                'icon_url' => null,
                'icon_emoji' => '📢',
                'color' => '#E11D48',
                'category' => Badge::CATEGORY_SOCIAL,
                'xp_reward' => 500,
                'bonus_points' => 250,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'referrals_converted',
                    'operator' => '>=',
                    'value' => 20,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 5,
                'sort_order' => 80,
            ],
            [
                'slug' => 'reviewer',
                'name' => ['en' => 'Critic', 'ro' => 'Critic'],
                'description' => ['en' => '10+ reviews submitted', 'ro' => '10+ recenzii'],
                'icon_url' => null,
                'icon_emoji' => '📝',
                'color' => '#F97316',
                'category' => Badge::CATEGORY_SOCIAL,
                'xp_reward' => 150,
                'bonus_points' => 75,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'reviews_submitted',
                    'operator' => '>=',
                    'value' => 10,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 85,
            ],
            [
                'slug' => 'photographer',
                'name' => ['en' => 'Photographer', 'ro' => 'Fotograf'],
                'description' => ['en' => '25+ photos uploaded', 'ro' => '25+ poze încărcate'],
                'icon_url' => null,
                'icon_emoji' => '📸',
                'color' => '#D946EF',
                'category' => Badge::CATEGORY_SOCIAL,
                'xp_reward' => 200,
                'bonus_points' => 100,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'photos_uploaded',
                    'operator' => '>=',
                    'value' => 25,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 3,
                'sort_order' => 90,
            ],

            // ==========================================
            // SPECIAL BADGES
            // ==========================================
            [
                'slug' => 'beta-tester',
                'name' => ['en' => 'Beta Tester', 'ro' => 'Beta Tester'],
                'description' => ['en' => 'Early adopter of the platform', 'ro' => 'Adoptator timpuriu al platformei'],
                'icon_url' => null,
                'icon_emoji' => '🧪',
                'color' => '#7C3AED',
                'category' => Badge::CATEGORY_SPECIAL,
                'xp_reward' => 1000,
                'bonus_points' => 500,
                'conditions' => [
                    'type' => 'manual',
                    'description' => 'Awarded manually to beta testers',
                ],
                'is_secret' => true,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 5,
                'sort_order' => 95,
            ],
            [
                'slug' => 'big-spender',
                'name' => ['en' => 'Big Spender', 'ro' => 'Cheltuitor Mare'],
                'description' => ['en' => 'Spent 5,000+ RON on tickets', 'ro' => 'Ai cheltuit 5,000+ lei pe bilete'],
                'icon_url' => null,
                'icon_emoji' => '💰',
                'color' => '#059669',
                'category' => Badge::CATEGORY_SPECIAL,
                'xp_reward' => 400,
                'bonus_points' => 200,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'total_spent',
                    'operator' => '>=',
                    'value' => 5000,
                ],
                'is_secret' => true,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 4,
                'sort_order' => 100,
            ],
            [
                'slug' => 'collector',
                'name' => ['en' => 'Collector', 'ro' => 'Colecționar'],
                'description' => ['en' => 'Collected 10+ badges', 'ro' => 'Ai colectat 10+ badge-uri'],
                'icon_url' => null,
                'icon_emoji' => '🎖️',
                'color' => '#B45309',
                'category' => Badge::CATEGORY_SPECIAL,
                'xp_reward' => 300,
                'bonus_points' => 150,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'total_badges_earned',
                    'operator' => '>=',
                    'value' => 10,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => true,
                'rarity_level' => 4,
                'sort_order' => 105,
            ],

            // ==========================================
            // EVENT-SPECIFIC BADGES
            // ==========================================
            [
                'slug' => 'new-years-eve',
                'name' => ['en' => 'New Year\'s Eve', 'ro' => 'Revelion'],
                'description' => ['en' => 'Attended a New Year\'s Eve event', 'ro' => 'Ai participat la un eveniment de Revelion'],
                'icon_url' => null,
                'icon_emoji' => '🎆',
                'color' => '#FCD34D',
                'category' => Badge::CATEGORY_EVENT,
                'xp_reward' => 100,
                'bonus_points' => 50,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'event_tag_attendance',
                    'operator' => '>=',
                    'value' => 1,
                    'params' => ['tag' => 'new-years-eve'],
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 110,
            ],
            [
                'slug' => 'summer-vibes',
                'name' => ['en' => 'Summer Vibes', 'ro' => 'Vibrații de Vară'],
                'description' => ['en' => '5+ summer events attended', 'ro' => '5+ evenimente de vară'],
                'icon_url' => null,
                'icon_emoji' => '☀️',
                'color' => '#FB923C',
                'category' => Badge::CATEGORY_EVENT,
                'xp_reward' => 150,
                'bonus_points' => 75,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'season_attendance',
                    'operator' => '>=',
                    'value' => 5,
                    'params' => ['season' => 'summer'],
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 2,
                'sort_order' => 115,
            ],
            [
                'slug' => 'front-row',
                'name' => ['en' => 'Front Row', 'ro' => 'Primul Rând'],
                'description' => ['en' => '3+ front row tickets purchased', 'ro' => '3+ bilete la primul rând'],
                'icon_url' => null,
                'icon_emoji' => '🎤',
                'color' => '#DC2626',
                'category' => Badge::CATEGORY_EVENT,
                'xp_reward' => 250,
                'bonus_points' => 125,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'front_row_purchases',
                    'operator' => '>=',
                    'value' => 3,
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 3,
                'sort_order' => 120,
            ],
            [
                'slug' => 'metalhead',
                'name' => ['en' => 'Metalhead', 'ro' => 'Metalhead'],
                'description' => ['en' => '10+ metal concerts attended', 'ro' => '10+ concerte metal'],
                'icon_url' => null,
                'icon_emoji' => '🤘',
                'color' => '#18181B',
                'category' => Badge::CATEGORY_EVENT,
                'xp_reward' => 200,
                'bonus_points' => 100,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'genre_attendance',
                    'operator' => '>=',
                    'value' => 10,
                    'params' => ['genre_slug' => 'metal'],
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 3,
                'sort_order' => 125,
            ],
            [
                'slug' => 'comedy-lover',
                'name' => ['en' => 'Comedy Lover', 'ro' => 'Iubitor de Comedie'],
                'description' => ['en' => '10+ comedy shows attended', 'ro' => '10+ spectacole de comedie'],
                'icon_url' => null,
                'icon_emoji' => '😂',
                'color' => '#FACC15',
                'category' => Badge::CATEGORY_EVENT,
                'xp_reward' => 200,
                'bonus_points' => 100,
                'conditions' => [
                    'type' => 'simple',
                    'metric' => 'category_attendance',
                    'operator' => '>=',
                    'value' => 10,
                    'params' => ['category_slug' => 'stand-up'],
                ],
                'is_secret' => false,
                'is_active' => true,
                'is_featured' => false,
                'rarity_level' => 3,
                'sort_order' => 130,
            ],
        ];

        foreach ($badges as $badgeData) {
            $badge = Badge::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => $badgeData['slug'],
                ],
                $badgeData
            );
            $this->command->info("    Created Badge: {$badgeData['slug']} (ID: {$badge->id})");
        }
    }

    /**
     * Seed Rewards (Redeemable with Points)
     */
    protected function seedRewards(?int $tenantId, ?int $marketplaceClientId): void
    {
        $this->command->info('  - Seeding Rewards...');

        $rewards = [
            // ==========================================
            // DISCOUNT REWARDS
            // ==========================================
            [
                'slug' => 'discount-10',
                'name' => ['en' => '10 RON Discount', 'ro' => '10 lei reducere'],
                'description' => ['en' => 'Applies to any order of minimum 50 RON', 'ro' => 'Aplicabil la orice comandă de minim 50 lei'],
                'image_url' => null,
                'type' => Reward::TYPE_FIXED_DISCOUNT,
                'points_cost' => 500,
                'value' => 10.00,
                'currency' => 'RON',
                'voucher_prefix' => 'D10',
                'min_order_value' => 50.00,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => null,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 10,
            ],
            [
                'slug' => 'discount-25',
                'name' => ['en' => '25 RON Discount', 'ro' => '25 lei reducere'],
                'description' => ['en' => 'Applies to any order of minimum 100 RON', 'ro' => 'Aplicabil la orice comandă de minim 100 lei'],
                'image_url' => null,
                'type' => Reward::TYPE_FIXED_DISCOUNT,
                'points_cost' => 1000,
                'value' => 25.00,
                'currency' => 'RON',
                'voucher_prefix' => 'D25',
                'min_order_value' => 100.00,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => null,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 6,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 20,
            ],
            [
                'slug' => 'discount-50',
                'name' => ['en' => '50 RON Discount', 'ro' => '50 lei reducere'],
                'description' => ['en' => 'Applies to any order of minimum 200 RON', 'ro' => 'Aplicabil la orice comandă de minim 200 lei'],
                'image_url' => null,
                'type' => Reward::TYPE_FIXED_DISCOUNT,
                'points_cost' => 1800,
                'value' => 50.00,
                'currency' => 'RON',
                'voucher_prefix' => 'D50',
                'min_order_value' => 200.00,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => null,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 10,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 30,
            ],
            [
                'slug' => 'discount-10-percent',
                'name' => ['en' => '10% Discount', 'ro' => '10% reducere'],
                'description' => ['en' => 'Applies to any order (max 100 RON discount)', 'ro' => 'Aplicabil la orice comandă (max 100 lei reducere)'],
                'image_url' => null,
                'type' => Reward::TYPE_PERCENTAGE_DISCOUNT,
                'points_cost' => 1500,
                'value' => 10.00,
                'currency' => 'RON',
                'voucher_prefix' => 'P10',
                'min_order_value' => null,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => 3,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 8,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 35,
            ],

            // ==========================================
            // UPGRADE REWARDS
            // ==========================================
            [
                'slug' => 'vip-upgrade',
                'name' => ['en' => 'VIP Upgrade', 'ro' => 'Upgrade VIP'],
                'description' => ['en' => 'Transform a Standard ticket into VIP', 'ro' => 'Transformă un bilet Standard în VIP'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 2000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'UPG',
                'min_order_value' => null,
                'max_redemptions_total' => 100,
                'max_redemptions_per_customer' => 2,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 11,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 40,
            ],
            [
                'slug' => 'priority-entry',
                'name' => ['en' => 'Priority Entry', 'ro' => 'Intrare Prioritară'],
                'description' => ['en' => 'Skip the queue at any event', 'ro' => 'Sari peste coadă la orice eveniment'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 1000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'PRI',
                'min_order_value' => null,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => 5,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 5,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 45,
            ],

            // ==========================================
            // EXPERIENCE REWARDS
            // ==========================================
            [
                'slug' => 'meet-greet',
                'name' => ['en' => 'Meet & Greet', 'ro' => 'Meet & Greet'],
                'description' => ['en' => 'Access to meet & greet with artists (where available)', 'ro' => 'Acces la meet & greet cu artiștii (unde e disponibil)'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 5000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'MG',
                'min_order_value' => null,
                'max_redemptions_total' => 50,
                'max_redemptions_per_customer' => 1,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 15,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 50,
            ],
            [
                'slug' => 'backstage-access',
                'name' => ['en' => 'Backstage Access', 'ro' => 'Acces Backstage'],
                'description' => ['en' => 'Exclusive backstage tour at selected events', 'ro' => 'Tur exclusiv backstage la evenimente selectate'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 8000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'BS',
                'min_order_value' => null,
                'max_redemptions_total' => 25,
                'max_redemptions_per_customer' => 1,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 20,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 55,
            ],

            // ==========================================
            // FREE ITEM REWARDS
            // ==========================================
            [
                'slug' => 'free-ticket-standard',
                'name' => ['en' => 'Free Standard Ticket', 'ro' => 'Bilet Standard Gratuit'],
                'description' => ['en' => 'One free Standard ticket at any event', 'ro' => 'Un bilet Standard gratuit la orice eveniment'],
                'image_url' => null,
                'type' => Reward::TYPE_FREE_ITEM,
                'points_cost' => 4000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'FT',
                'min_order_value' => null,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => 2,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 12,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 60,
            ],
            [
                'slug' => 'free-ticket-vip',
                'name' => ['en' => 'Free VIP Ticket', 'ro' => 'Bilet VIP Gratuit'],
                'description' => ['en' => 'One free VIP ticket at any event', 'ro' => 'Un bilet VIP gratuit la orice eveniment'],
                'image_url' => null,
                'type' => Reward::TYPE_FREE_ITEM,
                'points_cost' => 7000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'VT',
                'min_order_value' => null,
                'max_redemptions_total' => 50,
                'max_redemptions_per_customer' => 1,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 18,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 65,
            ],
            [
                'slug' => 'free-merchandise',
                'name' => ['en' => 'Free Merchandise', 'ro' => 'Merchandise Gratuit'],
                'description' => ['en' => 'Free t-shirt or merchandise item', 'ro' => 'Tricou sau articol merchandise gratuit'],
                'image_url' => null,
                'type' => Reward::TYPE_FREE_ITEM,
                'points_cost' => 1500,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'MR',
                'min_order_value' => null,
                'max_redemptions_total' => 200,
                'max_redemptions_per_customer' => 3,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 5,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 70,
            ],

            // ==========================================
            // STATUS / MEMBERSHIP REWARDS
            // ==========================================
            [
                'slug' => 'gold-member',
                'name' => ['en' => 'Gold Member Status', 'ro' => 'Status Gold Member'],
                'description' => ['en' => 'Gold status for 1 year - priority access to all events', 'ro' => 'Status Gold pentru 1 an - acces prioritar la toate evenimentele'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 10000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'GOLD',
                'min_order_value' => null,
                'max_redemptions_total' => 100,
                'max_redemptions_per_customer' => 1,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 20,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 75,
            ],
            [
                'slug' => 'platinum-member',
                'name' => ['en' => 'Platinum Member Status', 'ro' => 'Status Platinum Member'],
                'description' => ['en' => 'Platinum status for 1 year - VIP at all events', 'ro' => 'Status Platinum pentru 1 an - VIP la toate evenimentele'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 20000,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'PLAT',
                'min_order_value' => null,
                'max_redemptions_total' => 50,
                'max_redemptions_per_customer' => 1,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 25,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 80,
            ],

            // ==========================================
            // LIMITED / SEASONAL REWARDS
            // ==========================================
            [
                'slug' => 'double-xp-day',
                'name' => ['en' => 'Double XP Day', 'ro' => 'Zi cu XP Dublu'],
                'description' => ['en' => 'Activate to earn double XP for 24 hours', 'ro' => 'Activează pentru a câștiga XP dublu timp de 24 de ore'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 500,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => '2XP',
                'min_order_value' => null,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => 10,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 3,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 85,
            ],
            [
                'slug' => 'birthday-bonus',
                'name' => ['en' => 'Birthday Bonus', 'ro' => 'Bonus de Ziua Ta'],
                'description' => ['en' => 'Special birthday discount - only redeemable on your birthday month', 'ro' => 'Reducere specială de ziua ta - valabil doar în luna nașterii'],
                'image_url' => null,
                'type' => Reward::TYPE_PERCENTAGE_DISCOUNT,
                'points_cost' => 300,
                'value' => 20.00,
                'currency' => 'RON',
                'voucher_prefix' => 'BDAY',
                'min_order_value' => null,
                'max_redemptions_total' => null,
                'max_redemptions_per_customer' => 1,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 90,
            ],
            [
                'slug' => 'surprise-box',
                'name' => ['en' => 'Surprise Box', 'ro' => 'Cutie Surpriză'],
                'description' => ['en' => 'Mystery reward - could be discount, free ticket, or merchandise!', 'ro' => 'Recompensă misterioasă - poate fi reducere, bilet gratuit sau merchandise!'],
                'image_url' => null,
                'type' => Reward::TYPE_VOUCHER_CODE,
                'points_cost' => 2500,
                'value' => null,
                'currency' => 'RON',
                'voucher_prefix' => 'BOX',
                'min_order_value' => null,
                'max_redemptions_total' => 500,
                'max_redemptions_per_customer' => 5,
                'valid_from' => null,
                'valid_until' => null,
                'required_tiers' => null,
                'min_level_required' => 8,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 95,
            ],
        ];

        foreach ($rewards as $rewardData) {
            $reward = Reward::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => $rewardData['slug'],
                ],
                $rewardData
            );
            $this->command->info("    Created Reward: {$rewardData['slug']} (ID: {$reward->id})");
        }
    }
}
