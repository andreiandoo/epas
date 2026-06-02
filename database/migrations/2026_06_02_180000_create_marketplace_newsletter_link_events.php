<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter link engagement events: one row per open (pixel hit) and
 * per click (redirect through /newsletter/click/{token}). The aggregate
 * counters on marketplace_newsletters (opened_count, clicked_count)
 * keep being incremented in lockstep so the existing UI keeps working;
 * this table holds the granular trail for click-by-link and
 * recipient-aware analytics.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_newsletter_link_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newsletter_id');
            $table->string('event_type', 16); // 'open' | 'click'
            $table->string('link_key', 64)->nullable(); // sha1(dest_url) for click events, null for opens
            $table->text('dest_url')->nullable(); // original destination of the click
            $table->unsignedBigInteger('recipient_id')->nullable(); // marketplace_newsletter_recipients.id when known
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 1024)->nullable();
            $table->timestamps();

            $table->index(['newsletter_id', 'event_type']);
            $table->index(['newsletter_id', 'link_key']);
            $table->index('recipient_id');

            $table->foreign('newsletter_id', 'mnle_newsletter_fk')
                ->references('id')->on('marketplace_newsletters')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_newsletter_link_events');
    }
};
