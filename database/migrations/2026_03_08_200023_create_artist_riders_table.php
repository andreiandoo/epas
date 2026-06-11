<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Artist riders: technical and hospitality requirements
        Schema::create('artist_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Can belong to either a global artist, tenant artist, or agency artist
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_artist_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 32)->comment('technical|hospitality|security|travel');
            $table->jsonb('title')->comment('Translatable');
            $table->jsonb('content')->nullable()->comment('Translatable: HTML rich text content');

            // Structured technical rider data
            $table->json('stage_plot_url')->nullable();
            $table->json('input_list')->nullable()
                ->comment('[{channel: 1, instrument: "Kick", mic: "SM91", stand: "boom", notes: ""}]');
            $table->json('backline_requirements')->nullable()
                ->comment('[{item: "Guitar amp", spec: "Marshall JCM800", qty: 1, provided_by: "artist|venue"}]');
            $table->json('monitor_requirements')->nullable()
                ->comment('[{position: "Lead vocal", type: "wedge|IEM", mix_notes: "heavy vocals"}]');

            // Hospitality rider data
            $table->json('catering')->nullable()
                ->comment('{"hot_meals": 6, "dietary": ["vegetarian x2"], "drinks": [...], "dressing_room": "..."}');
            $table->json('accommodation')->nullable()
                ->comment('{"rooms_single": 2, "rooms_double": 1, "hotel_standard": "4star", "notes": ""}');
            $table->json('transport')->nullable()
                ->comment('{"ground_transport": "van for 8", "flights": "2 economy", "parking_spots": 1}');

            // Files
            $table->json('attachments')->nullable()
                ->comment('[{name: "Stage Plot.pdf", url: "...", type: "stage_plot|input_list|other"}]');

            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index(['artist_id']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_riders');
    }
};
