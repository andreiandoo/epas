<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ArtistEpk extends Model
{
    protected $fillable = [
        'artist_id',
        'active_variant_id',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ArtistEpkVariant::class)->orderBy('id');
    }

    public function activeVariant(): BelongsTo
    {
        return $this->belongsTo(ArtistEpkVariant::class, 'active_variant_id');
    }

    /**
     * Returnează EPK-ul artistului. Dacă nu există, îl crează cu o variantă
     * "Default" auto-populată cu cele 12 secțiuni goale + branding implicit.
     * Idempotent — apel multiplu nu strică nimic.
     */
    public static function getOrCreateForArtist(Artist $artist): self
    {
        return DB::transaction(function () use ($artist) {
            $epk = self::firstOrCreate(['artist_id' => $artist->id]);

            if ($epk->variants()->count() === 0) {
                $variant = $epk->variants()->create([
                    'name' => 'Default',
                    'target' => 'Universal',
                    'slug' => 'default',
                    'accent_color' => '#A51C30',
                    'template' => 'modern',
                    'sections' => ArtistEpkVariant::defaultSections($artist),
                ]);
                $epk->update(['active_variant_id' => $variant->id]);
                $epk->refresh();
            } elseif (!$epk->active_variant_id) {
                $first = $epk->variants()->first();
                $epk->update(['active_variant_id' => $first->id]);
                $epk->refresh();
            }

            return $epk;
        });
    }
}
