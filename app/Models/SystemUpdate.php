<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SystemUpdate — per-marketplace changelog / product announcement entry.
 *
 * Authored inside a marketplace admin (Filament Marketplace panel). Each
 * marketplace_client has its OWN updates — the model doesn't cross
 * marketplaces. Public API is scoped by the incoming X-API-Key header
 * (MarketplaceClientAuth middleware), so /noutati on ambilet.ro only ever
 * sees ambilet's updates.
 */
class SystemUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_client_id',
        'title',
        'slug',
        'category',
        'status',
        'excerpt',
        'body',
        'featured_image',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public const CATEGORIES = ['interfata', 'organizator', 'client'];
    public const STATUSES = ['draft', 'published'];

    // Boot: slug auto-generation + set published_at on first publish.
    protected static function booted(): void
    {
        static::creating(function (SystemUpdate $u) {
            if (empty($u->slug) && !empty($u->title)) {
                $u->slug = static::generateUniqueSlug($u->title, $u->marketplace_client_id);
            }
            // Auto-set published_at when the row is CREATED already published
            // (e.g. operator toggled 'published' before first save).
            if ($u->status === 'published' && empty($u->published_at)) {
                $u->published_at = now();
            }
        });

        static::updating(function (SystemUpdate $u) {
            // First-time transition to 'published' → stamp published_at.
            // Never overwrite an existing published_at (preserves the
            // original announcement date across future edits).
            if ($u->isDirty('status')
                && $u->status === 'published'
                && empty($u->getOriginal('published_at'))
                && empty($u->published_at)
            ) {
                $u->published_at = now();
            }
        });
    }

    protected static function generateUniqueSlug(string $title, ?int $marketplaceClientId): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'update';
        }

        $slug = $base;
        $i = 2;
        while (
            static::query()
                ->when($marketplaceClientId, fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * HTMLPurifier the WYSIWYG body on every write. Single choke-point —
     * whether the operator submits via Filament admin or a future direct
     * API PUT, the sanitizer runs. The `system_update` profile
     * (config/purifier.php) is the more permissive sibling of
     * `thank_you_message`: allows h1-h6, blockquote, pre/code, hr in
     * addition to the base link/image/iframe (YouTube+Vimeo only) set.
     */
    public function setBodyAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['body'] = null;
            return;
        }

        try {
            $this->attributes['body'] = \Mews\Purifier\Facades\Purifier::clean((string) $value, 'system_update');
        } catch (\Throwable $e) {
            \Log::warning('HTMLPurifier failed on system_update.body', [
                'id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            // Never persist unsanitized HTML if Purifier dies — fall back
            // to a strict tag whitelist.
            $this->attributes['body'] = strip_tags(
                (string) $value,
                '<p><br><b><strong><i><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote>'
            );
        }
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function reactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SystemUpdateReaction::class);
    }

    /**
     * Aggregate reaction counts keyed by type, e.g.
     * `['thumbs_up' => 3, 'heart' => 12, 'rocket' => 0, 'party' => 0]`.
     * Zero-fills unknown types so the frontend never has to `?? 0`.
     */
    public function getReactionCounts(): array
    {
        $rows = $this->reactions()
            ->selectRaw('reaction_type, COUNT(*) AS c')
            ->groupBy('reaction_type')
            ->pluck('c', 'reaction_type')
            ->all();

        $out = [];
        foreach (SystemUpdateReaction::TYPES as $t) {
            $out[$t] = (int) ($rows[$t] ?? 0);
        }
        return $out;
    }

    /**
     * Return the reaction types the given session_hash already voted
     * for. Used to render "you already voted" state in the UI.
     *
     * @return array<int, string>
     */
    public function getReactionsForSession(?string $sessionHash): array
    {
        if (!$sessionHash) return [];
        return $this->reactions()
            ->where('session_hash', $sessionHash)
            ->pluck('reaction_type')
            ->all();
    }

    // Query scopes -----------------------------------------------------

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeForMarketplace(Builder $q, int $marketplaceClientId): Builder
    {
        return $q->where('marketplace_client_id', $marketplaceClientId);
    }

    // Accessors --------------------------------------------------------

    /**
     * Public URL for the featured image, or null if not set. Uses the
     * `public` disk (symlinked to /storage) — same convention as Event's
     * poster_url and BlogArticle's featured_image_url.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (empty($this->featured_image)) {
            return null;
        }
        // Support both legacy full URLs (starts with http) and
        // storage-relative paths (the common case going forward).
        if (str_starts_with($this->featured_image, 'http')) {
            return $this->featured_image;
        }
        return Storage::disk('public')->url($this->featured_image);
    }
}
