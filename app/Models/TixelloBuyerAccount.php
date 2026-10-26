<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * Contul de cumpărător Tixello: biletele cumpărate de la toți organizatorii
 * Tixello, pe aceeași adresă de email, și acordul pentru informări de la Tixello.
 */
class TixelloBuyerAccount extends Model
{
    protected $fillable = [
        'email', 'first_name', 'last_name',
        'account_enabled', 'account_enabled_at',
        'marketing_opt_in', 'marketing_opt_in_at', 'marketing_opt_out_at',
        'email_verified_at', 'last_login_at',
    ];

    protected $casts = [
        'account_enabled' => 'boolean',
        'account_enabled_at' => 'datetime',
        'marketing_opt_in' => 'boolean',
        'marketing_opt_in_at' => 'datetime',
        'marketing_opt_out_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function consents(): HasMany
    {
        return $this->hasMany(TixelloBuyerConsent::class, 'account_id');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /** Linkul de intrare din email: semnat, valabil 30 de minute. */
    public function loginUrl(): string
    {
        return URL::temporarySignedRoute('buyer-account.login', now()->addMinutes(30), ['account' => $this->id]);
    }

    /** Dezabonarea din email: semnată, fără expirare, merge dintr-un click. */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('buyer-account.unsubscribe', ['account' => $this->id]);
    }
}
