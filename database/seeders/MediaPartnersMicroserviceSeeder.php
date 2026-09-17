<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

/**
 * Defines the "Media partners" microservice in the global catalogue.
 *
 * Does not activate it for any marketplace client: a super-admin does that at
 * /admin/marketplace-clients/{id}/edit → "Microservices" tab. Partners are then
 * created at /marketplace/media-partners.
 */
class MediaPartnersMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'media-partners'],
            [
                'name' => [
                    'en' => 'Media Partners',
                    'ro' => 'Parteneri media',
                ],
                'description' => [
                    'en' => 'API access for media partners such as music magazines: they read the marketplace events (title, date, venue, ticket link, price, availability) and artists with their own scoped key, and link events to their own artist pages.',
                    'ro' => 'Acces API pentru parteneri media, de exemplu reviste muzicale: citesc evenimentele marketplace-ului (titlu, dată, locație, link de bilete, preț, disponibilitate) și artiștii cu o cheie proprie, cu drepturi limitate, și leagă evenimentele de paginile lor de artist.',
                ],
                'short_description' => [
                    'en' => 'Event and artist feed for media partners, with a separate scoped API key.',
                    'ro' => 'Feed de evenimente și artiști pentru parteneri media, cu cheie API separată.',
                ],
                'icon' => 'heroicon-o-newspaper',
                'category' => 'marketing',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => false,
                'pricing_model' => 'one_time',
                'price' => 0,
                'currency' => 'RON',
                'features' => [
                    'en' => [
                        'Event feed with incremental sync (updated_since)',
                        'Deleted and cancelled events',
                        'Artist search for linking',
                        'Per-partner key, scopes, IP allowlist and rate limit',
                        'UTM parameters on ticket links',
                    ],
                    'ro' => [
                        'Feed de evenimente cu sincronizare incrementală (updated_since)',
                        'Evenimente șterse și anulate',
                        'Căutare de artiști pentru asociere',
                        'Cheie, drepturi, IP-uri permise și limită de cereri per partener',
                        'Parametri UTM pe linkurile de bilete',
                    ],
                ],
            ]
        );
    }
}
