<?php

namespace Database\Seeders;

use App\Models\Microservice;
use App\Models\MicroserviceFeature;
use Illuminate\Database\Seeder;

class AirtableIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        $microservice = Microservice::updateOrCreate(
            ['slug' => 'airtable-integration'],
            [
                'name' => 'Airtable Integration',
                'description' => 'Sync your data with Airtable bases. Push orders, tickets, and customer data to Airtable tables for custom reporting, workflows, and team collaboration.',
                'category' => 'productivity',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'config_schema' => [
                    'auth_type' => [
                        'type' => 'select',
                        'label' => 'Authentication Type',
                        'required' => true,
                        'options' => ['oauth' => 'OAuth (Recommended)', 'pat' => 'Personal Access Token'],
                        'default' => 'oauth',
                    ],
                    'personal_access_token' => [
                        'type' => 'secret',
                        'label' => 'Personal Access Token',
                        'required' => false,
                        'description' => 'Required only if using PAT authentication',
                        'depends_on' => ['auth_type' => 'pat'],
                    ],
                ],
                'required_env_vars' => [
                    'AIRTABLE_CLIENT_ID',
                    'AIRTABLE_CLIENT_SECRET',
                    'AIRTABLE_REDIRECT_URI',
                ],
                'dependencies' => [],
                'documentation_url' => 'https://airtable.com/developers/web/api/introduction',
            ]
        );

        $features = [
            [
                'name' => 'OAuth Connection',
                'slug' => 'oauth-connection',
                'description' => 'Connect via secure OAuth 2.0 flow',
            ],
            [
                'name' => 'Base & Table Sync',
                'slug' => 'base-table-sync',
                'description' => 'List and select bases and tables to sync with',
            ],
            [
                'name' => 'Orders Export',
                'slug' => 'orders-export',
                'description' => 'Push order data to Airtable tables',
            ],
            [
                'name' => 'Tickets Export',
                'slug' => 'tickets-export',
                'description' => 'Push ticket and attendee data to Airtable',
            ],
            [
                'name' => 'Customers Export',
                'slug' => 'customers-export',
                'description' => 'Sync customer records to Airtable',
            ],
            [
                'name' => 'Field Mapping',
                'slug' => 'field-mapping',
                'description' => 'Configure custom field mappings between local and Airtable fields',
            ],
            [
                'name' => 'Bidirectional Sync',
                'slug' => 'bidirectional-sync',
                'description' => 'Two-way sync between platform and Airtable',
            ],
            [
                'name' => 'Auto Sync',
                'slug' => 'auto-sync',
                'description' => 'Automatic scheduled syncing of data',
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
