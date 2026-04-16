<?php

namespace Database\Seeders;

use App\Models\MarketplaceEmailTemplate;
use Illuminate\Database\Seeder;

class BulkPasswordResetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = 1; // AmBilet

        // Customer template
        MarketplaceEmailTemplate::updateOrCreate(
            ['marketplace_client_id' => $clientId, 'slug' => 'bulk_password_reset_customer'],
            [
                'name' => 'Resetare parolă — Migrare cont (Clienți)',
                'subject' => 'AmBilet.ro s-a reinventat — Activează-ți contul',
                'body_html' => file_get_contents(__DIR__ . '/../../resources/marketplaces/ambilet/emails/bulk-reset-customer.html'),
                'body_text' => '',
                'variables' => json_encode(['first_name', 'email', 'reset_link', 'site_name', 'expire_days']),
                'category' => 'transactional',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        // Organizer template
        MarketplaceEmailTemplate::updateOrCreate(
            ['marketplace_client_id' => $clientId, 'slug' => 'bulk_password_reset_organizer'],
            [
                'name' => 'Resetare parolă — Migrare cont (Organizatori)',
                'subject' => 'AmBilet.ro s-a reinventat — Activează contul de organizator',
                'body_html' => file_get_contents(__DIR__ . '/../../resources/marketplaces/ambilet/emails/bulk-reset-organizer.html'),
                'body_text' => '',
                'variables' => json_encode(['first_name', 'email', 'reset_link', 'site_name', 'expire_days']),
                'category' => 'transactional',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        $this->command->info('Bulk password reset templates created/updated.');
    }
}
