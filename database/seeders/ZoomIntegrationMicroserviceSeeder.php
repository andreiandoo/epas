<?php

namespace Database\Seeders;

use App\Models\Microservice;
use App\Models\MicroserviceFeature;
use Illuminate\Database\Seeder;

class ZoomIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        $microservice = Microservice::updateOrCreate(
            ['slug' => 'zoom-integration'],
            [
                'name' => 'Zoom Integration',
                'description' => 'Create and manage Zoom meetings and webinars for virtual events. Automatic meeting creation, registrant sync, attendance tracking, and recording management.',
                'category' => 'video',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'config_schema' => [],
                'required_env_vars' => [
                    'ZOOM_CLIENT_ID',
                    'ZOOM_CLIENT_SECRET',
                    'ZOOM_REDIRECT_URI',
                    'ZOOM_WEBHOOK_SECRET_TOKEN',
                ],
                'dependencies' => [],
                'documentation_url' => 'https://developers.zoom.us/docs/api/',
            ]
        );

        $features = [
            [
                'name' => 'OAuth Connection',
                'slug' => 'oauth-connection',
                'description' => 'Connect Zoom account via OAuth 2.0',
            ],
            [
                'name' => 'Meeting Creation',
                'slug' => 'meeting-creation',
                'description' => 'Create scheduled and instant meetings',
            ],
            [
                'name' => 'Webinar Management',
                'slug' => 'webinar-management',
                'description' => 'Create and manage webinars with registration',
            ],
            [
                'name' => 'Event Integration',
                'slug' => 'event-integration',
                'description' => 'Auto-create Zoom meetings for virtual events',
            ],
            [
                'name' => 'Registrant Sync',
                'slug' => 'registrant-sync',
                'description' => 'Sync ticket holders as meeting registrants',
            ],
            [
                'name' => 'Attendance Tracking',
                'slug' => 'attendance-tracking',
                'description' => 'Track who joined and for how long',
            ],
            [
                'name' => 'Recording Management',
                'slug' => 'recording-management',
                'description' => 'Access and manage meeting recordings',
            ],
            [
                'name' => 'Webhook Events',
                'slug' => 'webhook-events',
                'description' => 'Real-time updates for meeting events',
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
