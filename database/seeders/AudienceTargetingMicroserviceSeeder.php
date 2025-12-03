<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class AudienceTargetingMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'audience-targeting'],
            [
                'name' => 'Audience Targeting & Campaigns',
                'description' => 'Advanced customer profiling, segmentation, event-based targeting, and multi-channel campaign management. Export audiences to Meta, Google Ads, TikTok, and Brevo for targeted marketing.',
                'category' => 'marketing',
                'icon' => 'heroicon-o-user-group',
                'is_active' => true,
                'pricing_model' => 'recurring',
                'price_cents' => 1500, // 15 EUR/month
                'billing_cycle' => 'monthly',
                'features' => [
                    'Customer profile enrichment with purchase history analysis',
                    'Dynamic segmentation with rule builder',
                    'Event-customer matching with AI scoring',
                    'Export to Meta Custom Audiences',
                    'Export to Google Ads Customer Match',
                    'Export to TikTok Ads Audiences',
                    'Brevo email list sync',
                    'Lookalike audience creation',
                    'Multi-channel campaign orchestration',
                    'Campaign analytics and ROI tracking',
                ],
                'requirements' => [
                    'Active tenant subscription',
                    'Pixel tracking integration (recommended)',
                ],
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'auto_profile_rebuild' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Automatically rebuild customer profiles daily',
                        ],
                        'auto_segment_refresh' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Automatically refresh dynamic segments',
                        ],
                        'default_segment_refresh_hours' => [
                            'type' => 'integer',
                            'default' => 24,
                            'description' => 'Default refresh interval for segments (hours)',
                        ],
                        'max_segments' => [
                            'type' => 'integer',
                            'default' => 10,
                            'description' => 'Maximum number of segments allowed',
                        ],
                        'max_exports_per_month' => [
                            'type' => 'integer',
                            'default' => 5,
                            'description' => 'Maximum exports per month',
                        ],
                    ],
                ],
                'documentation_url' => '/docs/microservices/audience-targeting',
                'sort_order' => 15,
            ]
        );

        $this->command->info('Audience Targeting microservice seeded successfully.');
    }
}
