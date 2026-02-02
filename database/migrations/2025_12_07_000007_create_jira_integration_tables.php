<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jira_connections')) {
            return;
        }

        Schema::create('jira_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('cloud_id')->nullable();
            $table->string('site_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('accessible_resources')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'cloud_id']);
        });

        if (Schema::hasTable('jira_projects')) {
            return;
        }

        Schema::create('jira_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('project_id');
            $table->string('project_key');
            $table->string('name');
            $table->string('project_type')->nullable();
            $table->boolean('is_synced')->default(true);
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('jira_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'project_id']);
        });

        if (Schema::hasTable('jira_issues')) {
            return;
        }

        Schema::create('jira_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('issue_id');
            $table->string('issue_key');
            $table->string('project_key');
            $table->string('issue_type');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('status');
            $table->string('priority')->nullable();
            $table->string('assignee_id')->nullable();
            $table->string('reporter_id')->nullable();
            $table->string('direction')->default('outbound');
            $table->string('correlation_ref')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('jira_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'issue_id']);
        });

        if (Schema::hasTable('jira_webhooks')) {
            return;
        }

        Schema::create('jira_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('webhook_id')->nullable();
            $table->string('event_type');
            $table->string('endpoint_url');
            $table->string('jql_filter')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('jira_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_webhooks');
        Schema::dropIfExists('jira_issues');
        Schema::dropIfExists('jira_projects');
        Schema::dropIfExists('jira_connections');
    }
};
