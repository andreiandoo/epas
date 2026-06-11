<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the "Activities Module" microservice metadata.
 *
 * Microservice slug: `activities-module`.
 *
 * Activates the Activities section in marketplace admin (separate from
 * Events): time-slot-based bookable experiences (escape rooms, museums,
 * adventure parks, workshops, tours, etc.) that run on weekly schedules
 * rather than calendar-fixed event dates.
 *
 * Activation is per marketplace via the `marketplace_client_microservices`
 * pivot — adding a row here only registers the module catalog entry.
 *
 * Idempotent: `updateOrInsert` keyed on slug; safe to re-run.
 */
class ActivitiesModuleMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'activities-module'],
            [
                'name' => json_encode([
                    'en' => 'Activities Module',
                    'ro' => 'Modul Activități',
                ], JSON_UNESCAPED_UNICODE),
                'description' => json_encode([
                    'en' => 'Sell bookable activities and experiences with recurring weekly schedules: escape rooms, museums, adventure parks, workshops, tours, cave visits and more. Unlike Events (date-fixed, fiscal-declared), Activities run on operating hours and customers pick a date + time slot when booking.',
                    'ro' => 'Vinde activități și experiențe rezervabile cu program săptămânal recurent: escape rooms, muzee, parcuri de aventură, ateliere, tururi, vizite la peșteri și altele. Spre deosebire de Evenimente (date fixe, declarate fiscal), Activitățile rulează pe baza orelor de funcționare, iar clientul alege data + intervalul orar la rezervare.',
                ], JSON_UNESCAPED_UNICODE),
                'short_description' => json_encode([
                    'en' => 'Bookable activities with weekly schedules and time slots',
                    'ro' => 'Activități rezervabile cu program săptămânal și sloturi orare',
                ], JSON_UNESCAPED_UNICODE),
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Weekly recurring schedule with per-day open/close hours',
                        'Schedule exceptions for holidays and closures',
                        'Multiple pricing variants per activity (Adult, Child, Group, etc.)',
                        'Auto-generated time slots from schedule + slot duration',
                        'Buffer time between slots for cleanup/reset',
                        'Per-slot capacity with live availability',
                        'Booking lead time + max-advance window',
                        'Min/max participants per booking',
                        'QR-based check-in with same staff scan app',
                        'Calendar view of upcoming bookings',
                        'Reuses existing Order + Stripe + invoice pipeline',
                        'SEO-ready landing pages with Schema.org Product + Offer',
                        'Indoor/outdoor + kid-friendly + accessible + weather-sensitive flags',
                        'Cancellation policy + cancellation window',
                        'Duration, difficulty level, age range, languages',
                        'Included/not-included items + requirements list',
                        'Meeting point + meeting instructions',
                    ],
                    'ro' => [
                        'Program săptămânal recurent cu ore deschis/închis per zi',
                        'Excepții program pentru sărbători și închideri',
                        'Variante multiple de preț per activitate (Adult, Copil, Grup, etc.)',
                        'Sloturi orare auto-generate din program + durată',
                        'Timp buffer între sloturi pentru curățenie/reset',
                        'Capacitate per slot cu disponibilitate live',
                        'Timp minim de rezervare + fereastră maximă în avans',
                        'Număr minim/maxim de participanți per rezervare',
                        'Check-in QR cu aceeași aplicație staff scan',
                        'Vedere calendar pentru rezervări viitoare',
                        'Reutilizează pipeline-ul existent de Order + Stripe + factură',
                        'Pagini publice SEO-ready cu Schema.org Product + Offer',
                        'Flag-uri indoor/outdoor + kid-friendly + accesibil + sensibil la vreme',
                        'Politică de anulare + fereastră de anulare',
                        'Durată, nivel de dificultate, vârstă, limbi disponibile',
                        'Listă elemente incluse/neincluse + cerințe',
                        'Punct de întâlnire + instrucțiuni',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'category' => 'commerce',
                'is_active' => true,
                'metadata' => json_encode([
                    'endpoints' => [
                        'GET /api/marketplace/activities',
                        'GET /api/marketplace/activities/{slug}',
                        'GET /api/marketplace/activities/{slug}/slots?date=YYYY-MM-DD',
                        'POST /api/marketplace/activity-bookings',
                        'DELETE /api/marketplace/activity-bookings/{id}/hold',
                        'POST /api/marketplace/activity-bookings/{id}/confirm',
                        'GET /api/staff/scan-activity-ticket',
                    ],
                    'database_tables' => [
                        'activities',
                        'activity_schedules',
                        'activity_schedule_exceptions',
                        'activity_variants',
                        'activity_bookings',
                    ],
                    'reuses' => [
                        'orders (with order_type=activity_booking)',
                        'tickets (with activity_booking_id FK)',
                        'marketplace_organizers',
                        'venues',
                        'marketplace_cities',
                        'marketplace_event_categories (aliased as MarketplaceCategory)',
                        'email_templates',
                        'facebook_capi_events',
                    ],
                    'gate_helper' => "MarketplaceClient::hasMicroservice('activities-module')",
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info("✓ Activities Module microservice metadata seeded (slug: activities-module, is_active: true)");
        $this->command->line('  Activate per marketplace via /admin/microservices or the marketplace_client_microservices pivot.');
    }
}
