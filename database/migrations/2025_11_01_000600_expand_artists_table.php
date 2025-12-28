<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            if (!Schema::hasColumn('artists', 'slug')) {
                $table->string('slug', 190)->unique()->after('name');
            }

            // Bio as HTML (WYSIWYG)
            if (!Schema::hasColumn('artists', 'bio_html')) {
                $table->longText('bio_html')->nullable()->after('slug');
            }

            // Public links
            if (!Schema::hasColumn('artists', 'website')) {
                $table->string('website', 255)->nullable()->after('bio_html');
            }
            if (!Schema::hasColumn('artists', 'facebook_url')) {
                $table->string('facebook_url', 255)->nullable()->after('website');
            }
            if (!Schema::hasColumn('artists', 'instagram_url')) {
                $table->string('instagram_url', 255)->nullable()->after('facebook_url');
            }
            if (!Schema::hasColumn('artists', 'tiktok_url')) {
                $table->string('tiktok_url', 255)->nullable()->after('instagram_url');
            }
            if (!Schema::hasColumn('artists', 'youtube_url')) {
                $table->string('youtube_url', 255)->nullable()->after('tiktok_url');
            }
            if (!Schema::hasColumn('artists', 'youtube_id')) {
                $table->string('youtube_id', 190)->nullable()->after('youtube_url');
            }
            if (!Schema::hasColumn('artists', 'spotify_url')) {
                $table->string('spotify_url', 255)->nullable()->after('youtube_id');
            }
            if (!Schema::hasColumn('artists', 'spotify_id')) {
                $table->string('spotify_id', 190)->nullable()->after('spotify_url');
            }

            // Media
            if (!Schema::hasColumn('artists', 'main_image_url')) {
                $table->string('main_image_url', 255)->nullable()->after('spotify_id');
            }
            if (!Schema::hasColumn('artists', 'logo_url')) {
                $table->string('logo_url', 255)->nullable()->after('main_image_url');
            }
            if (!Schema::hasColumn('artists', 'portrait_url')) {
                $table->string('portrait_url', 255)->nullable()->after('logo_url');
            }

            // Videos
            if (!Schema::hasColumn('artists', 'youtube_videos')) {
                $table->json('youtube_videos')->nullable()->after('portrait_url');
            }

            // Location & contacts
            if (!Schema::hasColumn('artists', 'city')) {
                $table->string('city', 120)->nullable()->after('youtube_videos');
            }
            if (!Schema::hasColumn('artists', 'country')) {
                $table->string('country', 120)->nullable()->after('city');
            }
            if (!Schema::hasColumn('artists', 'phone')) {
                $table->string('phone', 120)->nullable()->after('country');
            }
            if (!Schema::hasColumn('artists', 'email')) {
                $table->string('email', 190)->nullable()->after('phone');
            }

            // Manager contact
            if (!Schema::hasColumn('artists', 'manager_first_name')) {
                $table->string('manager_first_name', 120)->nullable()->after('email');
            }
            if (!Schema::hasColumn('artists', 'manager_last_name')) {
                $table->string('manager_last_name', 120)->nullable()->after('manager_first_name');
            }
            if (!Schema::hasColumn('artists', 'manager_email')) {
                $table->string('manager_email', 190)->nullable()->after('manager_last_name');
            }
            if (!Schema::hasColumn('artists', 'manager_phone')) {
                $table->string('manager_phone', 120)->nullable()->after('manager_email');
            }
            if (!Schema::hasColumn('artists', 'manager_website')) {
                $table->string('manager_website', 255)->nullable()->after('manager_phone');
            }

            // Booking agent contact
            if (!Schema::hasColumn('artists', 'agent_first_name')) {
                $table->string('agent_first_name', 120)->nullable()->after('manager_website');
            }
            if (!Schema::hasColumn('artists', 'agent_last_name')) {
                $table->string('agent_last_name', 120)->nullable()->after('agent_first_name');
            }
            if (!Schema::hasColumn('artists', 'agent_email')) {
                $table->string('agent_email', 190)->nullable()->after('agent_last_name');
            }
            if (!Schema::hasColumn('artists', 'agent_phone')) {
                $table->string('agent_phone', 120)->nullable()->after('agent_email');
            }
            if (!Schema::hasColumn('artists', 'agent_website')) {
                $table->string('agent_website', 255)->nullable()->after('agent_phone');
            }

            // Status
            if (!Schema::hasColumn('artists', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('agent_website');
            }
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            // safe drop (only if exists)
            foreach ([
                'slug','bio_html','website','facebook_url','instagram_url','tiktok_url','youtube_url','youtube_id',
                'spotify_url','spotify_id','main_image_url','logo_url','portrait_url','youtube_videos',
                'city','country','phone','email',
                'manager_first_name','manager_last_name','manager_email','manager_phone','manager_website',
                'agent_first_name','agent_last_name','agent_email','agent_phone','agent_website',
                'is_active',
            ] as $col) {
                if (Schema::hasColumn('artists', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
