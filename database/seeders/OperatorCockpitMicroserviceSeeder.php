<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

/**
 * Microserviciul `operator-cockpit`: bara de atenție a operatorilor din panoul
 * marketplace. Activarea e per marketplace (pivot marketplace_client_microservices).
 *
 *   php artisan db:seed --class=Database\Seeders\OperatorCockpitMicroserviceSeeder --force
 */
class OperatorCockpitMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'operator-cockpit'],
            [
                'name' => ['en' => 'Operator Cockpit', 'ro' => 'Cockpit operatori'],
                'description' => [
                    'en' => 'A vertical bar on the right side of the admin panel that gathers everything waiting on an operator: customer chats, support tickets, to-dos, artist accounts and events to review. Items disappear when resolved or claimed by someone else.',
                    'ro' => 'O bară verticală în dreapta panoului de administrare care adună tot ce așteaptă un operator: chat-uri cu clienții, tichete de suport, ToDo-uri, conturi de artist și evenimente de revizuit. Elementele dispar când sunt rezolvate sau preluate de altcineva.',
                ],
                'short_description' => [
                    'en' => 'Everything waiting on your operators, on every screen',
                    'ro' => 'Tot ce așteaptă operatorii, pe orice ecran',
                ],
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Attention bar on every admin screen',
                        'Colour-coded categories with live counts',
                        'Hover drawer with details and quick actions',
                        'Claim items in one click',
                        'Support department routing',
                        'Urgency thresholds with pulsing alerts',
                        'Super-admin overview of everyone\'s work',
                    ],
                    'ro' => [
                        'Bară de atenție pe orice ecran din panou',
                        'Categorii pe culori, cu numărători live',
                        'Sertar la hover, cu detalii și acțiuni rapide',
                        'Preluare dintr-un click',
                        'Rutare pe departamente de suport',
                        'Praguri de urgență cu pătrate care pulsează',
                        'Super-adminul vede ce lucrează toată echipa',
                    ],
                ],
                'category' => 'communication',
                'is_active' => true,
                'metadata' => [
                    'config_key' => 'operator-cockpit',
                ],
            ]
        );

        $this->command?->info('Operator Cockpit microservice seeded');
    }
}
