<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token cu viaţă lungă pentru widget-ul de Android.
 *
 * Se compară pe hash, niciodată pe valoarea în clar: dacă baza scapă, nu
 * scapă şi accesul la cifrele platformei.
 */
class TixelloWidgetToken extends Model
{
    protected $fillable = [
        'name',
        'token_hash',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Generează un token nou. Valoarea în clar se întoarce o singură dată —
     * apelantul o afişează şi o uită.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        /* Prefix ca să fie recunoscut instant într-un log sau într-un screenshot. */
        $plain = 'twg_' . Str::random(48);

        $token = static::create([
            'name' => $name,
            'token_hash' => static::hash($plain),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Token-ul valid pentru valoarea în clar dată, sau null.
     */
    public static function findValid(string $plain): ?self
    {
        $token = static::where('token_hash', static::hash($plain))->first();

        return $token && $token->isUsable() ? $token : null;
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
