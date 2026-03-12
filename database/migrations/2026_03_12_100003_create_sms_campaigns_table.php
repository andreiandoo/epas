<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->string('name'); // internal campaign name
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');
            $table->text('message_text');
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();

            // Audience filters (JSON)
            $table->json('filters')->nullable()->comment('city_ids, artist_ids, genre_ids, venue_ids');

            // Audience stats (calculated before sending)
            $table->unsignedInteger('total_audience')->default(0)->comment('Total matching customers');
            $table->unsignedInteger('audience_with_phone')->default(0)->comment('Matching customers with phone');
            $table->unsignedInteger('sms_per_recipient')->default(1)->comment('Number of SMS per recipient based on msg length');
            $table->unsignedInteger('total_sms_needed')->default(0)->comment('audience_with_phone * sms_per_recipient');

            // Sending stats
            $table->unsignedInteger('sms_sent')->default(0);
            $table->unsignedInteger('sms_delivered')->default(0);
            $table->unsignedInteger('sms_failed')->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index('marketplace_client_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_campaigns');
    }
};
