<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // MEDIA
            if (! Schema::hasColumn('events', 'poster_url')) {
                $table->string('poster_url')->nullable();
            }
            if (! Schema::hasColumn('events', 'hero_image_url')) {
                $table->string('hero_image_url')->nullable();
            }

            // FLAGS
            if (! Schema::hasColumn('events', 'is_sold_out')) {
                $table->boolean('is_sold_out')->default(false);
            }
            if (! Schema::hasColumn('events', 'is_cancelled')) {
                $table->boolean('is_cancelled')->default(false);
            }
            if (! Schema::hasColumn('events', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
            if (! Schema::hasColumn('events', 'is_postponed')) {
                $table->boolean('is_postponed')->default(false);
            }
            if (! Schema::hasColumn('events', 'postponed_date')) {
                $table->date('postponed_date')->nullable();
            }
            if (! Schema::hasColumn('events', 'postponed_start_time')) {
                $table->time('postponed_start_time')->nullable();
            }
            if (! Schema::hasColumn('events', 'postponed_door_time')) {
                $table->time('postponed_door_time')->nullable();
            }
            if (! Schema::hasColumn('events', 'postponed_end_time')) {
                $table->time('postponed_end_time')->nullable();
            }
            if (! Schema::hasColumn('events', 'door_sales_only')) {
                $table->boolean('door_sales_only')->default(false);
            }
            if (! Schema::hasColumn('events', 'is_promoted')) {
                $table->boolean('is_promoted')->default(false);
            }
            if (! Schema::hasColumn('events', 'promoted_until')) {
                $table->date('promoted_until')->nullable();
            }

            // TYPE & DURATION
            if (! Schema::hasColumn('events', 'event_type')) {
                // 'concert', 'festival'
                $table->string('event_type', 32)->default('concert');
            }
            if (! Schema::hasColumn('events', 'duration_mode')) {
                // 'single_day', 'range', 'multi_day'
                $table->string('duration_mode', 32)->default('single_day');
            }

            // SINGLE DAY fields
            if (! Schema::hasColumn('events', 'event_date')) {
                $table->date('event_date')->nullable();
            }
            if (! Schema::hasColumn('events', 'start_time')) {
                $table->time('start_time')->nullable();
            }
            if (! Schema::hasColumn('events', 'door_time')) {
                $table->time('door_time')->nullable();
            }
            if (! Schema::hasColumn('events', 'end_time')) {
                $table->time('end_time')->nullable();
            }

            // RANGE fields
            if (! Schema::hasColumn('events', 'range_start_date')) {
                $table->date('range_start_date')->nullable();
            }
            if (! Schema::hasColumn('events', 'range_end_date')) {
                $table->date('range_end_date')->nullable();
            }
            if (! Schema::hasColumn('events', 'range_start_time')) {
                $table->time('range_start_time')->nullable();
            }
            if (! Schema::hasColumn('events', 'range_end_time')) {
                $table->time('range_end_time')->nullable();
            }

            // MULTI-DAY slots (fallback generic, îl poți modela ulterior în performances)
            if (! Schema::hasColumn('events', 'multi_slots')) {
                $table->json('multi_slots')->nullable();
            }

            // LOCATION / LINKS
            if (! Schema::hasColumn('events', 'venue')) {
                $table->string('venue', 190)->nullable();
            }
            if (! Schema::hasColumn('events', 'address')) {
                $table->string('address', 255)->nullable();
            }
            if (! Schema::hasColumn('events', 'website_url')) {
                $table->string('website_url', 255)->nullable();
            }
            if (! Schema::hasColumn('events', 'facebook_url')) {
                $table->string('facebook_url', 255)->nullable();
            }
            if (! Schema::hasColumn('events', 'event_website_url')) {
                $table->string('event_website_url', 255)->nullable();
            }

            // CONTENT
            if (! Schema::hasColumn('events', 'short_description')) {
                $table->text('short_description')->nullable();
            }
            if (! Schema::hasColumn('events', 'description')) {
                $table->longText('description')->nullable();
            }
            if (! Schema::hasColumn('events', 'ticket_terms')) {
                $table->longText('ticket_terms')->nullable();
            }

            // SEO (JSON)
            if (! Schema::hasColumn('events', 'seo')) {
                $table->json('seo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $dropIf = function (string $col) use ($table) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            };

            // MEDIA
            $dropIf('poster_url');
            $dropIf('hero_image_url');

            // FLAGS
            $dropIf('is_sold_out');
            $dropIf('is_cancelled');
            $dropIf('cancel_reason');
            $dropIf('is_postponed');
            $dropIf('postponed_date');
            $dropIf('postponed_start_time');
            $dropIf('postponed_door_time');
            $dropIf('postponed_end_time');
            $dropIf('door_sales_only');
            $dropIf('is_promoted');
            $dropIf('promoted_until');

            // TYPE & DURATION
            $dropIf('event_type');
            $dropIf('duration_mode');

            // SINGLE DAY
            $dropIf('event_date');
            $dropIf('start_time');
            $dropIf('door_time');
            $dropIf('end_time');

            // RANGE
            $dropIf('range_start_date');
            $dropIf('range_end_date');
            $dropIf('range_start_time');
            $dropIf('range_end_time');

            // MULTI-DAY
            $dropIf('multi_slots');

            // LOCATION / LINKS
            $dropIf('venue');
            $dropIf('address');
            $dropIf('website_url');
            $dropIf('facebook_url');
            $dropIf('event_website_url');

            // CONTENT
            $dropIf('short_description');
            $dropIf('description');
            $dropIf('ticket_terms');

            // SEO
            $dropIf('seo');
        });
    }
};
