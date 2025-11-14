<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Order;
use App\Models\Event;
use App\Models\Performance;
use App\Models\TicketType;
use App\Models\Ticket;
use Illuminate\Support\Str;

class DevSeed extends Seeder
{
    public function run(): void
    {
        // Tenant demo
        $tenant = Tenant::query()->firstOrCreate(
            ['domain' => 'odeon.local'],
            [
                'name' => 'Teatrul Odeon',
                'slug' => 'teatrul-odeon',
                'status' => 'active',
                'plan' => 'A',
                'settings' => [
                    'currency' => 'RON',
                    'timezone' => 'Europe/Bucharest',
                ],
            ]
        );

        // Orders demo
        $order1 = Order::query()->updateOrCreate(
            ['id' => 1],
            [
                'tenant_id' => $tenant->id,
                'customer_email' => 'ana.popescu@example.com',
                'total_cents' => 12000,
                'status' => 'paid',
                'meta' => ['channel' => 'facebook'],
            ]
        );

        $order2 = Order::query()->updateOrCreate(
            ['id' => 2],
            [
                'tenant_id' => $tenant->id,
                'customer_email' => 'ion.ionescu@example.com',
                'total_cents' => 8500,
                'status' => 'pending',
                'meta' => ['channel' => 'organic'],
            ]
        );

        // Event + performance
        $event = Event::query()->firstOrCreate(
            ['slug' => 'concert-odeon'],
            [
                'tenant_id' => $tenant->id,
                'title' => 'Concert Odeon',
                'description' => 'Un concert demo pentru seed.',
                'venue_name' => 'Sala Mare',
                'city' => 'București',
                'status' => 'published',
                'seo_title' => 'Concert Odeon - Bilete',
                'seo_description' => 'Cumpără bilete la Concert Odeon.',
            ]
        );

        $perf = Performance::query()->firstOrCreate(
            ['event_id' => $event->id, 'starts_at' => now()->addDays(7)->setTime(19, 30)],
            [
                'ends_at' => now()->addDays(7)->setTime(21, 30),
                'status' => 'active',
            ]
        );

        // Ticket types
        $ttEarly = TicketType::query()->firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Early Bird'],
            [
                'price_cents' => 8000,
                'currency' => 'RON',
                'quota_total' => 100,
                'quota_sold' => 2,
                'status' => 'active',
                'sales_start_at' => now()->subDays(5),
                'sales_end_at' => now()->addDays(1),
                'meta' => ['label' => 'promo'],
            ]
        );

        $ttStandard = TicketType::query()->firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Standard'],
            [
                'price_cents' => 12000,
                'currency' => 'RON',
                'quota_total' => 300,
                'quota_sold' => 1,
                'status' => 'active',
                'sales_start_at' => now(),
                'sales_end_at' => now()->addDays(30),
            ]
        );

        // Tickets legate de Order #1
        Ticket::query()->updateOrCreate(
            ['code' => 'T-' . Str::upper(Str::random(10))],
            [
                'order_id' => $order1->id,
                'ticket_type_id' => $ttEarly->id,
                'performance_id' => $perf->id,
                'status' => 'valid',
                'seat_label' => 'A-12',
                'meta' => ['buyer' => 'Ana Popescu'],
            ]
        );

        Ticket::query()->updateOrCreate(
            ['code' => 'T-' . Str::upper(Str::random(10))],
            [
                'order_id' => $order1->id,
                'ticket_type_id' => $ttStandard->id,
                'performance_id' => $perf->id,
                'status' => 'used',
                'seat_label' => 'A-13',
                'meta' => ['buyer' => 'Ana Popescu'],
            ]
        );

        // Ticket nealocat (încă fără order) pentru demo
        Ticket::query()->updateOrCreate(
            ['code' => 'T-' . Str::upper(Str::random(10))],
            [
                'order_id' => null,
                'ticket_type_id' => $ttStandard->id,
                'performance_id' => $perf->id,
                'status' => 'valid',
                'seat_label' => 'B-20',
            ]
        );
    }
}
