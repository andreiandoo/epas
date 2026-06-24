<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Angajat permanent al unui marketplace_organizer leisure (Sf. Ana etc.).
 * Are un QR fix individual care nu expiră — îl folosește la fiecare tură.
 * Tracking-ul intrărilor se face în leisure_staff_checkins.
 */
class LeisureStaffMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leisure_staff_members';

    protected $fillable = [
        'marketplace_organizer_id',
        'first_name',
        'last_name',
        'phone',
        'position',
        'qr_code',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Prefix folosit de scanner ca să distingă QR-ul de staff de QR-ul de bilete. */
    public const QR_PREFIX = 'STAFF-';

    protected static function booted(): void
    {
        static::creating(function (self $staff) {
            if (empty($staff->qr_code)) {
                $staff->qr_code = self::generateUniqueQrCode();
            }
        });
    }

    /**
     * Generează un cod QR unic, format `STAFF-{12 hex chars}`. Verifică
     * existența în DB ca să evite (improbabila) coliziune. 12 hex = 48 bits =
     * ~280 trilioane combinații — coliziune practic imposibilă.
     */
    public static function generateUniqueQrCode(): string
    {
        do {
            $code = self::QR_PREFIX . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        } while (self::where('qr_code', $code)->exists());
        return $code;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(LeisureStaffCheckin::class, 'staff_member_id');
    }
}
