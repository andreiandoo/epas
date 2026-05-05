<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_audience_segments')) {
            Schema::create('customer_audience_segments', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('criteria')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketplace_organizer_audience_subscriptions')) {
            Schema::create('marketplace_organizer_audience_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketplace_organizer_id');
                $table->unsignedBigInteger('audience_segment_id');
                $table->boolean('is_active')->default(true);

                // Meta-side IDs after first successful sync
                $table->string('meta_audience_id', 100)->nullable();
                $table->string('meta_audience_name')->nullable();

                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_sync_status', 20)->nullable();
                $table->text('last_sync_error')->nullable();
                $table->integer('member_count')->default(0);

                $table->timestamps();

                $table->foreign('marketplace_organizer_id')
                    ->references('id')->on('marketplace_organizers')
                    ->cascadeOnDelete();
                $table->foreign('audience_segment_id')
                    ->references('id')->on('customer_audience_segments')
                    ->cascadeOnDelete();

                $table->unique(
                    ['marketplace_organizer_id', 'audience_segment_id'],
                    'mp_org_aud_subs_unique'
                );
                $table->index(
                    ['is_active', 'last_synced_at'],
                    'mp_org_aud_subs_active_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_audience_subscriptions');
        Schema::dropIfExists('customer_audience_segments');
    }
};
