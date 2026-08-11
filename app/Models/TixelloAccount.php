<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Contul din aplicația Tixello.
 *
 * Nu apartine niciunui tenant si niciunui marketplace — de-aia exista. Ce
 * poate vedea omul din lumile alea vine exclusiv din `links()`, adica din ce
 * a acceptat el explicit sa lege.
 */
class TixelloAccount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'email', 'password', 'name', 'phone',
        'email_verified_at', 'verification_code', 'verification_expires_at', 'verification_attempts',
        'is_organizer', 'avatar', 'locale', 'status', 'last_login_at',
        'invite_code', 'friends_visibility',
    ];

    protected $hidden = ['password', 'verification_code'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_organizer' => 'boolean',
        'verification_attempts' => 'integer',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(TixelloAccountLink::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(TixelloAccountToken::class);
    }

    public function activeLinks(): HasMany
    {
        return $this->links()->where('status', 'active');
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /* ---------- verificarea emailului ---------- */

    /**
     * Genereaza un cod de verificare.
     *
     * Emailul e cheia care leaga o comanda facuta in aplicatie de contul de la
     * partener, deci verificarea nu e o formalitate: fara ea, cineva se poate
     * inregistra cu adresa altcuiva, iar comenzile ar ateriza in contul aceluia.
     */
    public function issueVerificationCode(int $minutes = 15): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'verification_code' => Hash::make($code),
            'verification_expires_at' => now()->addMinutes($minutes),
            'verification_attempts' => 0,
        ])->save();

        return $code;
    }

    /** Verifica un cod. Limiteaza incercarile, ca sa nu poata fi ghicit. */
    public function confirmVerificationCode(string $code): bool
    {
        if (! $this->verification_code || ! $this->verification_expires_at) {
            return false;
        }
        if ($this->verification_expires_at->isPast() || $this->verification_attempts >= 5) {
            return false;
        }

        $this->increment('verification_attempts');

        if (! Hash::check($code, $this->verification_code)) {
            return false;
        }

        $this->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_expires_at' => null,
            'verification_attempts' => 0,
        ])->save();

        return true;
    }

    /* ---------- token de sesiune ---------- */

    /** Intoarce tokenul in clar O SINGURA DATA; in baza se pastreaza hash-ul. */
    public function issueToken(?string $deviceId = null, int $days = 30): string
    {
        $plain = Str::random(64);

        $this->tokens()->create([
            'token' => hash('sha256', $plain),
            'name' => 'tixello-app',
            'device_id' => $deviceId,
            'expires_at' => now()->addDays($days),
            'last_used_at' => now(),
        ]);

        return $plain;
    }

    public static function findByToken(string $plain): ?self
    {
        $row = TixelloAccountToken::where('token', hash('sha256', $plain))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (! $row) {
            return null;
        }

        $row->forceFill(['last_used_at' => now()])->save();

        $account = $row->account;

        return $account && $account->status === 'active' ? $account : null;
    }
}
