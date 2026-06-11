<?php

namespace Database\Seeders;

use App\Models\Microservice;
use App\Models\MicroserviceFeature;
use Illuminate\Database\Seeder;

class SquareIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        $microservice = Microservice::updateOrCreate(
            ['slug' => 'square-integration'],
            [
                'name' => 'Square Integration',
                'description' => 'Connect with Square for payments, catalog sync, and order management. Accept in-person payments with Square hardware or process online payments through Square.',
                'category' => 'payments',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'config_schema' => [
                    'environment' => [
                        'type' => 'select',
                        'label' => 'Environment',
                        'required' => true,
                        'options' => ['sandbox' => 'Sandbox (Testing)', 'production' => 'Production'],
                        'default' => 'production',
                    ],
                ],
                'required_env_vars' => [
                    'SQUARE_CLIENT_ID',
                    'SQUARE_CLIENT_SECRET',
                    'SQUARE_REDIRECT_URI',
                    'SQUARE_WEBHOOK_SIGNATURE_KEY',
                ],
                'dependencies' => [],
                'documentation_url' => 'https://developer.squareup.com/docs',
            ]
        );

        $features = [
            [
                'name' => 'OAuth Connection',
                'slug' => 'oauth-connection',
                'description' => 'Connect merchant accounts via OAuth 2.0',
            ],
            [
                'name' => 'Location Management',
                'slug' => 'location-management',
                'description' => 'Sync and manage Square business locations',
            ],
            [
                'name' => 'Catalog Sync',
                'slug' => 'catalog-sync',
                'description' => 'Sync products and ticket types with Square catalog',
            ],
            [
                'name' => 'Order Creation',
                'slug' => 'order-creation',
                'description' => 'Create orders in Square for tracking and fulfillment',
            ],
            [
                'name' => 'Payment Processing',
                'slug' => 'payment-processing',
                'description' => 'Process payments through Square',
            ],
            [
                'name' => 'POS Integration',
                'slug' => 'pos-integration',
                'description' => 'Integrate with Square Point of Sale hardware',
            ],
            [
                'name' => 'Webhook Events',
                'slug' => 'webhook-events',
                'description' => 'Receive real-time payment and order updates',
            ],
            [
                'name' => 'Reporting Sync',
                'slug' => 'reporting-sync',
                'description' => 'Sync transaction data for unified reporting',
            ],
        ];

        foreach ($features as $feature) {
            MicroserviceFeature::updateOrCreate(
                ['microservice_id' => $microservice->id, 'slug' => $feature['slug']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
