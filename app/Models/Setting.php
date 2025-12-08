<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name',
        'cui',
        'reg_com',
        'vat_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'default_currency',
        'bank_name',
        'bank_account',
        'bank_swift',
        'invoice_prefix',
        'invoice_next_number',
        'invoice_series',
        'default_payment_terms_days',
        'logo_path',
        'invoice_footer',
        'email_footer',
        'stripe_mode',
        'stripe_test_public_key',
        'stripe_test_secret_key',
        'stripe_live_public_key',
        'stripe_live_secret_key',
        'stripe_webhook_secret',
        'vat_enabled',
        'vat_rate',
        'youtube_api_key',
        'spotify_client_id',
        'spotify_client_secret',
        'google_maps_api_key',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_phone_number',
        'openweather_api_key',
        'facebook_app_id',
        'facebook_app_secret',
        'facebook_access_token',
        'google_analytics_property_id',
        'google_analytics_credentials_json',
        'brevo_api_key',
        'tiktok_client_key',
        'tiktok_client_secret',
        'meta',
        // Integration Microservices
        'slack_client_id',
        'slack_client_secret',
        'slack_signing_secret',
        'discord_client_id',
        'discord_client_secret',
        'discord_bot_token',
        'google_workspace_client_id',
        'google_workspace_client_secret',
        'microsoft365_client_id',
        'microsoft365_client_secret',
        'microsoft365_tenant_id',
        'salesforce_client_id',
        'salesforce_client_secret',
        'hubspot_client_id',
        'hubspot_client_secret',
        'jira_client_id',
        'jira_client_secret',
        'zapier_client_id',
        'zapier_client_secret',
        'google_sheets_client_id',
        'google_sheets_client_secret',
        'whatsapp_cloud_verify_token',
        'airtable_client_id',
        'airtable_client_secret',
        'square_client_id',
        'square_client_secret',
        'square_environment',
        'square_webhook_signature_key',
        'zoom_client_id',
        'zoom_client_secret',
        'zoom_webhook_secret_token',
        // Ad Platform Connectors
        'google_ads_client_id',
        'google_ads_client_secret',
        'google_ads_developer_token',
        'tiktok_ads_app_id',
        'tiktok_ads_app_secret',
        'linkedin_ads_client_id',
        'linkedin_ads_client_secret',
    ];

    protected $casts = [
        'meta' => 'array',
        'invoice_next_number' => 'integer',
        'default_payment_terms_days' => 'integer',
        'stripe_test_secret_key' => 'encrypted',
        'stripe_live_secret_key' => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
        'youtube_api_key' => 'encrypted',
        'spotify_client_secret' => 'encrypted',
        'google_maps_api_key' => 'encrypted',
        'twilio_auth_token' => 'encrypted',
        'openweather_api_key' => 'encrypted',
        'facebook_app_secret' => 'encrypted',
        'facebook_access_token' => 'encrypted',
        'google_analytics_credentials_json' => 'encrypted',
        'brevo_api_key' => 'encrypted',
        'tiktok_client_secret' => 'encrypted',
        'vat_enabled' => 'boolean',
        'vat_rate' => 'decimal:2',
        // Integration Microservices - encrypted secrets
        'slack_client_secret' => 'encrypted',
        'slack_signing_secret' => 'encrypted',
        'discord_client_secret' => 'encrypted',
        'discord_bot_token' => 'encrypted',
        'google_workspace_client_secret' => 'encrypted',
        'microsoft365_client_secret' => 'encrypted',
        'salesforce_client_secret' => 'encrypted',
        'hubspot_client_secret' => 'encrypted',
        'jira_client_secret' => 'encrypted',
        'zapier_client_secret' => 'encrypted',
        'google_sheets_client_secret' => 'encrypted',
        'airtable_client_secret' => 'encrypted',
        'square_client_secret' => 'encrypted',
        'square_webhook_signature_key' => 'encrypted',
        'zoom_client_secret' => 'encrypted',
        'zoom_webhook_secret_token' => 'encrypted',
        // Ad Platform Connectors - encrypted
        'google_ads_client_secret' => 'encrypted',
        'google_ads_developer_token' => 'encrypted',
        'tiktok_ads_app_secret' => 'encrypted',
        'linkedin_ads_client_secret' => 'encrypted',
    ];

    /**
     * Get the single settings record (singleton pattern)
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /**
     * Generate next invoice number and increment
     */
    public function getNextInvoiceNumber(): string
    {
        $number = $this->invoice_next_number;
        $prefix = $this->invoice_prefix ?? 'INV';
        $series = $this->invoice_series ? "{$this->invoice_series}-" : '';

        // Increment for next time
        $this->increment('invoice_next_number');

        return "{$prefix}-{$series}" . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get active Stripe keys based on mode
     */
    public function getStripePublicKey(): ?string
    {
        return $this->stripe_mode === 'live'
            ? $this->stripe_live_public_key
            : $this->stripe_test_public_key;
    }

    public function getStripeSecretKey(): ?string
    {
        return $this->stripe_mode === 'live'
            ? $this->stripe_live_secret_key
            : $this->stripe_test_secret_key;
    }

    public function isStripeConfigured(): bool
    {
        $publicKey = $this->getStripePublicKey();
        $secretKey = $this->getStripeSecretKey();

        return !empty($publicKey) && !empty($secretKey);
    }

    // ==========================================
    // INTEGRATION CONFIGURATION CHECKS
    // ==========================================

    public function isSlackConfigured(): bool
    {
        return !empty($this->slack_client_id) && !empty($this->slack_client_secret);
    }

    public function isDiscordConfigured(): bool
    {
        return !empty($this->discord_client_id) && !empty($this->discord_client_secret);
    }

    public function isGoogleWorkspaceConfigured(): bool
    {
        return !empty($this->google_workspace_client_id) && !empty($this->google_workspace_client_secret);
    }

    public function isMicrosoft365Configured(): bool
    {
        return !empty($this->microsoft365_client_id) && !empty($this->microsoft365_client_secret);
    }

    public function isSalesforceConfigured(): bool
    {
        return !empty($this->salesforce_client_id) && !empty($this->salesforce_client_secret);
    }

    public function isHubSpotConfigured(): bool
    {
        return !empty($this->hubspot_client_id) && !empty($this->hubspot_client_secret);
    }

    public function isJiraConfigured(): bool
    {
        return !empty($this->jira_client_id) && !empty($this->jira_client_secret);
    }

    public function isGoogleSheetsConfigured(): bool
    {
        return !empty($this->google_sheets_client_id) && !empty($this->google_sheets_client_secret);
    }

    public function isAirtableConfigured(): bool
    {
        return !empty($this->airtable_client_id) && !empty($this->airtable_client_secret);
    }

    public function isSquareConfigured(): bool
    {
        return !empty($this->square_client_id) && !empty($this->square_client_secret);
    }

    public function isZoomConfigured(): bool
    {
        return !empty($this->zoom_client_id) && !empty($this->zoom_client_secret);
    }

    /**
     * Get the base URL for OAuth callbacks
     */
    public function getOAuthCallbackBaseUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/integrations';
    }
}
