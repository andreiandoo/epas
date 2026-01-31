<?php

namespace App\Models\Integrations\Zapier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZapierConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'api_key', 'status', 'connected_at', 'last_used_at', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['api_key'];

    public function triggers(): HasMany
    {
        return $this->hasMany(ZapierTrigger::class, 'connection_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ZapierAction::class, 'connection_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->api_key) {
                $model->api_key = bin2hex(random_bytes(32));
            }
        });
    }
}
