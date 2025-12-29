<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Email Templates for marketplace
        Schema::create('marketplace_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('slug')->index(); // e.g., 'ticket_purchase', 'welcome', 'points_earned'
            $table->string('name');
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('variables')->nullable(); // Available template variables
            $table->string('category')->default('transactional'); // transactional, marketing, notification
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug']);
        });

        // Email Logs
        Schema::create('marketplace_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_organizer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketplace_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketplace_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_slug')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('subject');
            $table->text('body_html')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, bounced, opened, clicked
            $table->string('message_id')->nullable(); // From email provider
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['marketplace_client_id', 'status']);
            $table->index('to_email');
        });

        // Contact Lists for newsletters
        Schema::create('marketplace_contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('subscriber_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('marketplace_client_id');
        });

        // Contact Tags
        Schema::create('marketplace_contact_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('color')->default('#6366f1');
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'name']);
        });

        // Contact List Members (pivot)
        Schema::create('marketplace_contact_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('marketplace_contact_lists')->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('subscribed'); // subscribed, unsubscribed, bounced
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['list_id', 'marketplace_customer_id']);
        });

        // Contact Tag assignments (pivot)
        Schema::create('marketplace_customer_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('marketplace_contact_tags')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['marketplace_customer_id', 'tag_id']);
        });

        // Newsletters/Campaigns
        Schema::create('marketplace_newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('target_lists')->nullable(); // List IDs to send to
            $table->json('target_tags')->nullable(); // Tag IDs to filter by
            $table->string('status')->default('draft'); // draft, scheduled, sending, sent, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('marketplace_admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_client_id', 'status']);
        });

        // Newsletter Recipients
        Schema::create('marketplace_newsletter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained('marketplace_newsletters')->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('status')->default('pending'); // pending, sent, failed, opened, clicked, unsubscribed
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['newsletter_id', 'status']);
        });

        // SMTP Settings (add to marketplace_clients settings or separate table)
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->json('smtp_settings')->nullable()->after('settings');
            $table->json('email_settings')->nullable()->after('smtp_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn(['smtp_settings', 'email_settings']);
        });

        Schema::dropIfExists('marketplace_newsletter_recipients');
        Schema::dropIfExists('marketplace_newsletters');
        Schema::dropIfExists('marketplace_customer_tags');
        Schema::dropIfExists('marketplace_contact_list_members');
        Schema::dropIfExists('marketplace_contact_tags');
        Schema::dropIfExists('marketplace_contact_lists');
        Schema::dropIfExists('marketplace_email_logs');
        Schema::dropIfExists('marketplace_email_templates');
    }
};
