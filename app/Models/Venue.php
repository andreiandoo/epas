<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Venue extends Model
{
    use Translatable;

    /**
     * Translatable fields
     */
    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id','name','slug','address','city','state','country',
        'website_url','phone','email',
        'facebook_url','instagram_url','tiktok_url',
        'image_url','gallery','capacity','capacity_total','capacity_standing','capacity_seated',
        'lat','lng','established_at','description','meta',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'meta' => 'array',
        'gallery' => 'array',
        'established_at' => 'date',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function events(): HasMany
    {
        return $this->hasMany(\App\Models\Event::class);
    }

    public function seatingLayouts(): HasMany
    {
        return $this->hasMany(\App\Models\Seating\SeatingLayout::class);
    }

    // public route binding by slug
    public function getRouteKeyName(): string { return 'slug'; }

    protected static function booted(): void
    {
        static::creating(function (self $venue) {
            if (blank($venue->slug)) {
                // Get English name from translatable field
                $name = is_array($venue->name) ? ($venue->name['en'] ?? '') : $venue->name;
                if (filled($name)) {
                    $venue->slug = static::uniqueSlug(Str::slug($name));
                }
            }
        });

        // nu schimbăm slug-ul la update automat (ca să nu rupem URL-urile).
    }

    protected static function uniqueSlug(string $base): string
    {
        $slug = $base ?: 'venue';
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }
}
