<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix column sizes for encrypted fields.
     * VARCHAR(255) is too small for encrypted values.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Change string columns that store encrypted data to text
            // Encrypted values are typically 2-3x longer than the original

            // Slack
            if (Schema::hasColumn('settings', 'slack_signing_secret')) {
                $table->text('slack_signing_secret')->nullable()->change();
            }

            // Microsoft 365
            if (Schema::hasColumn('settings', 'microsoft365_tenant_id')) {
                $table->text('microsoft365_tenant_id')->nullable()->change();
            }

            // WhatsApp
            if (Schema::hasColumn('settings', 'whatsapp_cloud_verify_token')) {
                $table->text('whatsapp_cloud_verify_token')->nullable()->change();
            }

            // Square
            if (Schema::hasColumn('settings', 'square_environment')) {
                $table->text('square_environment')->nullable()->change();
            }

            // Also fix any client_id columns that might be encrypted
            $clientIdColumns = [
                'slack_client_id',
                'discord_client_id',
                'google_workspace_client_id',
                'microsoft365_client_id',
                'salesforce_client_id',
                'hubspot_client_id',
                'jira_client_id',
                'zapier_client_id',
                'google_sheets_client_id',
                'airtable_client_id',
                'square_client_id',
                'zoom_client_id',
            ];

            foreach ($clientIdColumns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->text($column)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // Note: Reverting to string(255) may truncate encrypted data
        // Only run down migration if you're certain the data won't be lost
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'slack_signing_secret')) {
                $table->string('slack_signing_secret', 255)->nullable()->change();
            }
            if (Schema::hasColumn('settings', 'microsoft365_tenant_id')) {
                $table->string('microsoft365_tenant_id', 255)->nullable()->change();
            }
            if (Schema::hasColumn('settings', 'whatsapp_cloud_verify_token')) {
                $table->string('whatsapp_cloud_verify_token', 255)->nullable()->change();
            }
            if (Schema::hasColumn('settings', 'square_environment')) {
                $table->string('square_environment', 255)->nullable()->change();
            }
        });
    }
};
