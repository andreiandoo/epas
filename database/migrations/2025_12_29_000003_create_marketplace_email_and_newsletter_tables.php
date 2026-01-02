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
            $table->unsignedBigInteger('marketplace_client_id');
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

            $table->foreign('marketplace_client_id', 'mkt_email_tpl_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->unique(['marketplace_client_id', 'slug'], 'mkt_email_tpl_client_slug_unique');
        });

        // Email Logs
        Schema::create('marketplace_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable();
            $table->unsignedBigInteger('marketplace_customer_id')->nullable();
            $table->unsignedBigInteger('marketplace_event_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('template_slug')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('subject');
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, failed, bounced, opened, clicked
            $table->string('message_id')->nullable(); // From email provider
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('marketplace_client_id', 'mkt_email_log_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->foreign('marketplace_organizer_id', 'mkt_email_log_org_fk')
                ->references('id')->on('marketplace_organizers')->nullOnDelete();
            $table->foreign('marketplace_customer_id', 'mkt_email_log_cust_fk')
                ->references('id')->on('marketplace_customers')->nullOnDelete();
            $table->foreign('marketplace_event_id', 'mkt_email_log_event_fk')
                ->references('id')->on('marketplace_events')->nullOnDelete();
            $table->foreign('order_id', 'mkt_email_log_order_fk')
                ->references('id')->on('orders')->nullOnDelete();
            $table->index(['marketplace_client_id', 'status'], 'mkt_email_log_status_idx');
            $table->index('to_email');
        });

        // Contact Lists for newsletters
        Schema::create('marketplace_contact_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('subscriber_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('marketplace_client_id', 'mkt_contact_list_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->index('marketplace_client_id');
        });

        // Contact Tags
        Schema::create('marketplace_contact_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->string('name');
            $table->string('color')->default('#6366f1');
            $table->timestamps();

            $table->foreign('marketplace_client_id', 'mkt_contact_tag_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->unique(['marketplace_client_id', 'name'], 'mkt_contact_tag_unique');
        });

        // Contact List Members (pivot)
        Schema::create('marketplace_contact_list_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id');
            $table->unsignedBigInteger('marketplace_customer_id');
            $table->string('status')->default('subscribed'); // subscribed, unsubscribed, bounced
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->foreign('list_id', 'mkt_list_member_list_fk')
                ->references('id')->on('marketplace_contact_lists')->onDelete('cascade');
            $table->foreign('marketplace_customer_id', 'mkt_list_member_cust_fk')
                ->references('id')->on('marketplace_customers')->onDelete('cascade');
            $table->unique(['list_id', 'marketplace_customer_id'], 'mkt_list_customer_unique');
        });

        // Contact Tag assignments (pivot)
        Schema::create('marketplace_customer_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_customer_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('marketplace_customer_id', 'mkt_cust_tag_cust_fk')
                ->references('id')->on('marketplace_customers')->onDelete('cascade');
            $table->foreign('tag_id', 'mkt_cust_tag_tag_fk')
                ->references('id')->on('marketplace_contact_tags')->onDelete('cascade');
            $table->unique(['marketplace_customer_id', 'tag_id'], 'mkt_customer_tag_unique');
        });

        // Newsletters/Campaigns
        Schema::create('marketplace_newsletters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('marketplace_client_id', 'mkt_newsletter_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->foreign('created_by', 'mkt_newsletter_admin_fk')
                ->references('id')->on('marketplace_admins')->nullOnDelete();
            $table->index(['marketplace_client_id', 'status'], 'mkt_newsletter_status_idx');
        });

        // Newsletter Recipients
        Schema::create('marketplace_newsletter_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newsletter_id');
            $table->unsignedBigInteger('marketplace_customer_id');
            $table->string('email');
            $table->string('status')->default('pending'); // pending, sent, failed, bounced, opened, clicked, unsubscribed
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('newsletter_id', 'mkt_nl_recip_newsletter_fk')
                ->references('id')->on('marketplace_newsletters')->onDelete('cascade');
            $table->foreign('marketplace_customer_id', 'mkt_nl_recip_customer_fk')
                ->references('id')->on('marketplace_customers')->onDelete('cascade');
            $table->index(['newsletter_id', 'status'], 'mkt_nl_recip_status_idx');
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
