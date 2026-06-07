<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a handful of APPROVED customer reviews for a demo activity so the GYG
 * single-activity page shows a populated "Customer reviews" section.
 *
 * Safe + idempotent:
 *  - targets one activity by slug (default: demo-camera-13-escape-room)
 *  - reuses existing marketplace_customers for that client; creates a few demo
 *    customers only if none exist (defensive, wrapped in try/catch)
 *  - skips a review if (customer, activity) already has one (unique constraint)
 *  - requires the activity_id column (run migrations first)
 *
 * Run:  php artisan db:seed --class=Database\\Seeders\\DemoActivityReviewsSeeder
 */
class DemoActivityReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $slug = env('DEMO_REVIEW_ACTIVITY_SLUG', 'demo-camera-13-escape-room');

        // Raw column check — Schema::hasColumn returns stale results on this
        // environment, so query the live table metadata directly.
        $hasActivityCol = ! empty(DB::select("SHOW COLUMNS FROM marketplace_customer_reviews LIKE 'activity_id'"));
        if (! $hasActivityCol) {
            $this->command?->warn('Column activity_id missing — run `php artisan migrate` first. Skipping.');
            return;
        }

        $activity = DB::table('activities')->where('slug', $slug)->first();
        if (! $activity) {
            $this->command?->warn("Activity '{$slug}' not found. Skipping.");
            return;
        }
        $clientId = $activity->marketplace_client_id;

        // Collect customer ids for this client (need distinct ones — 1 review each).
        $customerIds = DB::table('marketplace_customers')
            ->where('marketplace_client_id', $clientId)
            ->orderBy('id')
            ->limit(6)
            ->pluck('id')
            ->all();

        $reviews = [
            ['rating' => 5, 'text' => 'Experiență super! Camera e foarte bine gândită, game master-ul ne-a ghidat exact cât trebuia. Recomand cu încredere pentru o seară diferită.', 'detailed' => ['organizare' => 5, 'experienta' => 5, 'valoare' => 4], 'name' => ['Alina', 'P']],
            ['rating' => 5, 'text' => 'Am mers în 4 și ne-am distrat enorm. Atmosfera horror e pe bune, dar fără să fie exagerat. Am ieșit cu 3 minute înainte de final — adrenalină garantată.', 'detailed' => ['organizare' => 5, 'experienta' => 5, 'valoare' => 5], 'name' => ['Mihai', 'D']],
            ['rating' => 4, 'text' => 'Foarte fain, puzzle-uri logice și bine legate. Singurul minus: e cam scurt dacă echipa e rapidă. În rest, merită toți banii.', 'detailed' => ['organizare' => 4, 'experienta' => 5, 'valoare' => 4], 'name' => ['Denisa', 'R']],
            ['rating' => 5, 'text' => 'Cea mai tare cameră în care am fost în București. Rezervarea online a fost simplă, biletul a venit instant pe email. Revenim sigur la altă cameră.', 'detailed' => ['organizare' => 5, 'experienta' => 5, 'valoare' => 5], 'name' => ['Andrei', 'M']],
            ['rating' => 4, 'text' => 'Recomand pentru grupuri de prieteni. Necesită puțină comunicare, deci e perfect pentru team building. Personalul, foarte amabil.', 'detailed' => ['organizare' => 5, 'experienta' => 4, 'valoare' => 4], 'name' => ['Ioana', 'S']],
            ['rating' => 5, 'text' => 'Aniversare reușită! Am rezervat pentru iubitul meu și a fost încântat. Decorul și efectele sunt la alt nivel față de alte escape rooms.', 'detailed' => ['organizare' => 5, 'experienta' => 5, 'valoare' => 4], 'name' => ['Bianca', 'T']],
        ];

        // If the client has no customers, create a few demo ones (defensive).
        if (empty($customerIds)) {
            foreach ($reviews as $i => $rv) {
                try {
                    $id = DB::table('marketplace_customers')->insertGetId(array_filter([
                        'marketplace_client_id' => $clientId,
                        'first_name'            => $rv['name'][0],
                        'last_name'             => $rv['name'][1] . '.',
                        'email'                 => 'demo-review-' . $i . '-' . $clientId . '@example.com',
                        'status'                => 'active',
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ], fn ($v) => $v !== null));
                    $customerIds[] = $id;
                } catch (\Throwable $e) {
                    // column mismatch — stop trying to create, use whatever we have
                    break;
                }
            }
        }

        if (empty($customerIds)) {
            $this->command?->warn('No customers available for this client and could not create demo ones. Skipping.');
            return;
        }

        $inserted = 0;
        foreach ($reviews as $i => $rv) {
            if (! isset($customerIds[$i])) break;
            $customerId = $customerIds[$i];

            $exists = DB::table('marketplace_customer_reviews')
                ->where('marketplace_customer_id', $customerId)
                ->where('activity_id', $activity->id)
                ->exists();
            if ($exists) continue;

            try {
                DB::table('marketplace_customer_reviews')->insert([
                    'marketplace_client_id'   => $clientId,
                    'marketplace_customer_id' => $customerId,
                    'marketplace_event_id'    => null,
                    'activity_id'             => $activity->id,
                    'rating'                  => $rv['rating'],
                    'text'                    => $rv['text'],
                    'detailed_ratings'        => json_encode($rv['detailed']),
                    'recommend'               => true,
                    'is_anonymous'            => false,
                    'status'                  => 'approved',
                    'helpful_count'           => random_int(0, 14),
                    'created_at'              => now()->subDays(($i + 1) * 9),
                    'updated_at'              => now()->subDays(($i + 1) * 9),
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $this->command?->warn('Review insert failed: ' . $e->getMessage());
            }
        }

        $this->command?->info("Seeded {$inserted} approved review(s) for activity '{$slug}' (#{$activity->id}).");
    }
}
