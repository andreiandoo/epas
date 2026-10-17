<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un abonat la newsletterul unui tenant (ex. fanii unui artist). Abonarea vine din site
 * (formularul de newsletter), de la plată (bifa „vreau noutăți”) sau din panou.
 * Tokenul e linkul personal de dezabonare din fiecare email.
 */
class TenantNewsletterSubscriber extends Model
{
    public const SOURCES = ['site' => 'Site', 'checkout' => 'La plată', 'panel' => 'Adăugat în panou', 'import' => 'Import'];

    protected $fillable = [
        'tenant_id', 'email', 'first_name', 'locale', 'source', 'status', 'token',
        'consent_ip', 'subscribed_at', 'unsubscribed_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->email = mb_strtolower(trim((string) $s->email));
            $s->token = $s->token ?: Str::random(48);
            $s->subscribed_at = $s->subscribed_at ?: now();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'subscribed');
    }

    /**
     * Abonează (sau reabonează) o adresă la newsletterul tenantului. Un abonat dezabonat
     * revine doar printr-o acțiune nouă, explicită, a lui (formularul de pe site sau bifa de la plată).
     */
    public static function subscribe(Tenant $tenant, string $email, array $extra = []): self
    {
        $email = mb_strtolower(trim($email));
        $s = static::firstOrNew(['tenant_id' => $tenant->id, 'email' => $email]);
        if (! $s->exists || $s->status !== 'subscribed') {
            $s->status = 'subscribed';
            $s->subscribed_at = now();
            $s->unsubscribed_at = null;
        }
        foreach (['first_name', 'locale', 'source', 'consent_ip'] as $k) {
            if (! empty($extra[$k]) && ($k !== 'source' || ! $s->exists)) {
                $s->{$k} = $extra[$k];
            }
        }
        $s->save();
        return $s;
    }

    public function unsubscribe(): void
    {
        if ($this->status !== 'unsubscribed') {
            $this->forceFill(['status' => 'unsubscribed', 'unsubscribed_at' => now()])->save();
        }
    }
}
