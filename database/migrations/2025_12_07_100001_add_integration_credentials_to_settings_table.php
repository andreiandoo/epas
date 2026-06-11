<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Slack Integration
            $table->string('slack_client_id')->nullable();
            $table->text('slack_client_secret')->nullable();
            $table->string('slack_signing_secret')->nullable();

            // Discord Integration
            $table->string('discord_client_id')->nullable();
            $table->text('discord_client_secret')->nullable();
            $table->text('discord_bot_token')->nullable();

            // Google Workspace Integration
            $table->string('google_workspace_client_id')->nullable();
            $table->text('google_workspace_client_secret')->nullable();

            // Microsoft 365 Integration
            $table->string('microsoft365_client_id')->nullable();
            $table->text('microsoft365_client_secret')->nullable();
            $table->string('microsoft365_tenant_id')->nullable()->default('common');

            // Salesforce Integration
            $table->string('salesforce_client_id')->nullable();
            $table->text('salesforce_client_secret')->nullable();

            // HubSpot Integration
            $table->string('hubspot_client_id')->nullable();
            $table->text('hubspot_client_secret')->nullable();

            // Jira/Atlassian Integration
            $table->string('jira_client_id')->nullable();
            $table->text('jira_client_secret')->nullable();

            // Zapier Integration (REST hooks)
            $table->string('zapier_client_id')->nullable();
            $table->text('zapier_client_secret')->nullable();

            // Google Sheets Integration (separate from Workspace)
            $table->string('google_sheets_client_id')->nullable();
            $table->text('google_sheets_client_secret')->nullable();

            // WhatsApp Business Cloud API
            $table->string('whatsapp_cloud_verify_token')->nullable();

            // Airtable Integration
            $table->string('airtable_client_id')->nullable();
            $table->text('airtable_client_secret')->nullable();

            // Square Integration
            $table->string('square_client_id')->nullable();
            $table->text('square_client_secret')->nullable();
            $table->string('square_environment')->nullable()->default('production');
            $table->text('square_webhook_signature_key')->nullable();

            // Zoom Integration
            $table->string('zoom_client_id')->nullable();
            $table->text('zoom_client_secret')->nullable();
            $table->text('zoom_webhook_secret_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'slack_client_id', 'slack_client_secret', 'slack_signing_secret',
                'discord_client_id', 'discord_client_secret', 'discord_bot_token',
                'google_workspace_client_id', 'google_workspace_client_secret',
                'microsoft365_client_id', 'microsoft365_client_secret', 'microsoft365_tenant_id',
                'salesforce_client_id', 'salesforce_client_secret',
                'hubspot_client_id', 'hubspot_client_secret',
                'jira_client_id', 'jira_client_secret',
                'zapier_client_id', 'zapier_client_secret',
                'google_sheets_client_id', 'google_sheets_client_secret',
                'whatsapp_cloud_verify_token',
                'airtable_client_id', 'airtable_client_secret',
                'square_client_id', 'square_client_secret', 'square_environment', 'square_webhook_signature_key',
                'zoom_client_id', 'zoom_client_secret', 'zoom_webhook_secret_token',
            ]);
        });
    }
};
