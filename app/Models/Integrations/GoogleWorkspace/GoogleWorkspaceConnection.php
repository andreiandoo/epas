<?php

namespace App\Models\Integrations\GoogleWorkspace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class GoogleWorkspaceConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'google_user_id', 'email', 'name', 'access_token', 'refresh_token',
        'token_expires_at', 'scopes', 'enabled_services', 'status', 'connected_at',
        'last_used_at', 'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'enabled_services' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function driveFiles(): HasMany
    {
        return $this->hasMany(GoogleDriveFile::class, 'connection_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(GoogleCalendarEvent::class, 'connection_id');
    }

    public function gmailMessages(): HasMany
    {
        return $this->hasMany(GoogleGmailMessage::class, 'connection_id');
    }

    public function hasService(string $service): bool
    {
        return in_array($service, $this->enabled_services ?? []);
    }
}
