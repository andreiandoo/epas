<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add custom related events and featured images to events table
        Schema::table('events', function (Blueprint $table) {
            // Custom related events
            if (!Schema::hasColumn('events', 'has_custom_related')) {
                $table->boolean('has_custom_related')->default(false)->after('is_category_featured');
            }
            if (!Schema::hasColumn('events', 'custom_related_event_ids')) {
                $table->json('custom_related_event_ids')->nullable()->after('has_custom_related');
            }

            // Featured images
            if (!Schema::hasColumn('events', 'homepage_featured_image')) {
                $table->string('homepage_featured_image', 500)->nullable()->after('custom_related_event_ids');
            }
            if (!Schema::hasColumn('events', 'featured_image')) {
                $table->string('featured_image', 500)->nullable()->after('homepage_featured_image');
            }
        });

        // Add ordering and headliner flags to event_artist pivot table
        Schema::table('event_artist', function (Blueprint $table) {
            if (!Schema::hasColumn('event_artist', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('artist_id');
            }
            if (!Schema::hasColumn('event_artist', 'is_headliner')) {
                $table->boolean('is_headliner')->default(false)->after('sort_order');
            }
            if (!Schema::hasColumn('event_artist', 'is_co_headliner')) {
                $table->boolean('is_co_headliner')->default(false)->after('is_headliner');
            }

            // Add index for ordering
            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'has_custom_related',
                'custom_related_event_ids',
                'homepage_featured_image',
                'featured_image',
            ]);
        });

        Schema::table('event_artist', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'sort_order']);
            $table->dropColumn(['sort_order', 'is_headliner', 'is_co_headliner']);
        });
    }
};
