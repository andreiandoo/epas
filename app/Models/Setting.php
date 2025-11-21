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
        'meta',
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
        'vat_enabled' => 'boolean',
        'vat_rate' => 'decimal:2',
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
}
