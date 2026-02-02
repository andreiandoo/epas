<?php

namespace App\Models\Integrations\WhatsAppCloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class WhatsAppCloudConnection extends Model
{
    use SoftDeletes;

    protected $table = 'whatsapp_cloud_connections';

    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'phone_number',
        'display_name',
        'business_account_id',
        'access_token',
        'webhook_verify_token',
        'status',
        'verified_at',
        'capabilities',
        'metadata',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'metadata' => 'array',
        'verified_at' => 'datetime',
    ];

    protected $hidden = ['access_token'];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppCloudTemplate::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppCloudMessage::class, 'connection_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WhatsAppCloudContact::class, 'connection_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WhatsAppCloudWebhookEvent::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
