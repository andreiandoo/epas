<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessNfcKey extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'key_slot',
        'encrypted_key',
        'key_version',
        'rotated_at',
        'created_by',
    ];

    protected $casts = [
        'key_version' => 'integer',
        'rotated_at'  => 'datetime',
    ];

    protected $hidden = ['encrypted_key'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Decrypt the stored key using the app encryption key.
     */
    public function getDecryptedKey(): string
    {
        return decrypt($this->encrypted_key);
    }

    /**
     * Store an AES key encrypted with the app key.
     */
    public static function storeKey(
        int $tenantId,
        int $editionId,
        string $slot,
        string $aesKeyHex,
        ?int $createdBy = null,
    ): self {
        return static::updateOrCreate(
            [
                'festival_edition_id' => $editionId,
                'key_slot'            => $slot,
            ],
            [
                'tenant_id'     => $tenantId,
                'encrypted_key' => encrypt($aesKeyHex),
                'key_version'   => \DB::raw('COALESCE(key_version, 0) + 1'),
                'rotated_at'    => now(),
                'created_by'    => $createdBy,
            ]
        );
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }

    public function scopeSlot($query, string $slot)
    {
        return $query->where('key_slot', $slot);
    }
}
