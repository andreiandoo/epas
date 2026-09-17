<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ad format a partner offers (zone + size), as returned by its GET /ads/slots.
 */
class PartnerAdFormat extends Model
{
    protected $fillable = [
        'marketplace_partner_id',
        'slot',
        'slot_label',
        'format',
        'label',
        'spec',
        'is_available',
        'synced_at',
    ];

    protected $casts = [
        'spec' => 'array',
        'is_available' => 'boolean',
        'synced_at' => 'datetime',
    ];

    /**
     * Form value for a format: zone and format code, since codes repeat across zones.
     */
    public function key(): string
    {
        return $this->slot . '|' . $this->format;
    }

    /**
     * @return array{0: string, 1: string}|null [slot, format]
     */
    public static function splitKey(?string $key): ?array
    {
        $parts = explode('|', (string) $key, 2);

        return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' ? $parts : null;
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(MarketplacePartner::class, 'marketplace_partner_id');
    }

    /**
     * Fields the partner renders for this format, e.g. ["image", "title", "text", "cta"].
     */
    public function fields(): array
    {
        return array_values(array_filter((array) ($this->spec['fields'] ?? ['image', 'title', 'text', 'cta'])));
    }

    public function usesField(string $field): bool
    {
        return in_array($field, $this->fields(), true);
    }

    public function maxLength(string $field): ?int
    {
        $value = $this->spec['max_length'][$field] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return array{width: int, height: int, required: bool}|null
     */
    public function imageSize(string $key = 'image'): ?array
    {
        $size = $this->spec[$key] ?? null;

        if (!is_array($size) || empty($size['width']) || empty($size['height'])) {
            return null;
        }

        return [
            'width' => (int) $size['width'],
            'height' => (int) $size['height'],
            'required' => (bool) ($size['required'] ?? false),
        ];
    }

    public function optionLabel(): string
    {
        $size = $this->imageSize();

        return trim(($this->slot_label ?: $this->slot) . ' — ' . ($this->label ?: $this->format)
            . ($size ? " ({$size['width']}×{$size['height']})" : ''));
    }
}
