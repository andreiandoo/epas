<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SaniusSled extends Model
{
    public const STATUSES = ['available', 'rented', 'repair', 'lost', 'retired'];

    /** Prefixul din QR-ul lipit pe sanie; il deosebeste de un bilet. */
    public const QR_PREFIX = 'SANIE:';

    protected $table = 'sanius_sleds';

    protected $fillable = ['event_id', 'number', 'label', 'code', 'status', 'is_test', 'notes'];

    protected $casts = [
        'number' => 'integer',
        'is_test' => 'boolean',
    ];

    public static function newCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Accepta atat QR-ul „SANIE:XXXX” cat si codul tastat de mana. */
    public static function normalizeCode(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        if (str_starts_with($raw, self::QR_PREFIX)) {
            $raw = substr($raw, strlen(self::QR_PREFIX));
        }

        return $raw;
    }

    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->code;
    }

    public function displayName(): string
    {
        return $this->label ?: (($this->is_test ? 'Sanie test #' : 'Sanie #').$this->number);
    }
}
