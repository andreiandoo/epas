<?php

namespace App\Models\Integrations\Twilio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class TwilioConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'account_sid', 'auth_token', 'phone_number',
        'messaging_service_sid', 'enabled_channels', 'status',
        'connected_at', 'last_used_at', 'metadata',
    ];

    protected $casts = [
        'enabled_channels' => 'array',
        'metadata' => 'array',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['account_sid', 'auth_token'];

    public function setAccountSidAttribute($value): void
    {
        $this->attributes['account_sid'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccountSidAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAuthTokenAttribute($value): void
    {
        $this->attributes['auth_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAuthTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TwilioMessage::class, 'connection_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(TwilioCall::class, 'connection_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(TwilioWebhook::class, 'connection_id');
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->enabled_channels ?? []);
    }
}
