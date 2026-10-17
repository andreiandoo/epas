<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletterul unui tenant (fanii unui artist, publicul unui teatru …): abonații strânși
 * pe site-ul tenantului și la plată, plus campaniile trimise din panou. Separat de
 * newsletterul marketplace-urilor (marketplace_newsletters), care are alt public.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_newsletter_subscribers')) {
            Schema::create('tenant_newsletter_subscribers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('email', 190);
                $table->string('first_name', 100)->nullable();
                $table->string('locale', 5)->default('ro');
                $table->string('source', 30)->default('site'); // site | checkout | panel | import
                $table->string('status', 20)->default('subscribed'); // subscribed | unsubscribed
                $table->string('token', 64)->unique(); // linkul personal de dezabonare
                $table->string('consent_ip', 45)->nullable();
                $table->timestamp('subscribed_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'email']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('tenant_newsletters')) {
            Schema::create('tenant_newsletters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('subject', 190);
                $table->string('preheader', 190)->nullable();
                $table->longText('body')->nullable();
                $table->string('button_label', 80)->nullable();
                $table->string('button_url', 500)->nullable();
                $table->string('locale', 5)->default('all'); // all | ro | en
                $table->string('status', 20)->default('draft'); // draft | sending | sent | failed
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_newsletters');
        Schema::dropIfExists('tenant_newsletter_subscribers');
    }
};
