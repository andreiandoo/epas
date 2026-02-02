<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zapier_connections')) {
            return;
        }

        Schema::create('zapier_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('api_key')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id']);
        });

        if (Schema::hasTable('zapier_triggers')) {
            return;
        }

        Schema::create('zapier_triggers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('trigger_type'); // order_created, ticket_sold, customer_created, event_published
            $table->string('webhook_url');
            $table->string('zap_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zapier_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'trigger_type', 'webhook_url']);
        });

        if (Schema::hasTable('zapier_trigger_logs')) {
            return;
        }

        Schema::create('zapier_trigger_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trigger_id');
            $table->string('trigger_type');
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->integer('http_status')->nullable();
            $table->text('response')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->foreign('trigger_id')->references('id')->on('zapier_triggers')->onDelete('cascade');
        });

        if (Schema::hasTable('zapier_actions')) {
            return;
        }

        Schema::create('zapier_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('action_type');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zapier_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zapier_actions');
        Schema::dropIfExists('zapier_trigger_logs');
        Schema::dropIfExists('zapier_triggers');
        Schema::dropIfExists('zapier_connections');
    }
};
