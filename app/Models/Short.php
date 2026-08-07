<?php

namespace App\Models;

use App\Services\Video\VideoProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Short-form vertical video shown in the Tixello mobile app feed.
 *
 * NOTE: intentionally no SecureTenantScoping global scope (same stance as
 * MediaLibrary) — core admin curates across all tenants, and scoping is applied
 * per Filament resource / per API endpoint instead.
 */
class Short extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCES = ['upload', 'youtube', 'tiktok', 'instagram', 'facebook'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = ['draft', 'pending_review', 'published', 'archived', 'rejected'];

    public const CTA_TYPES = ['none', 'buy_tickets', 'open_event', 'open_artist', 'external'];

    protected $fillable = [
        'owner_type',
        'owner_id',
        'tenant_id',
        'marketplace_client_id',
        'event_id',
        'source',
        'source_url',
        'source_video_id',
        'embed_html',
        'video_provider',
        'provider_asset_id',
        'hls_url',
        'ready',
        'disk',
        'path',
        'mime_type',
        'duration',
        'width',
        'height',
        'poster_path',
        'title',
        'caption',
        'hashtags',
        'language',
        'music_credit',
        'cta_type',
        'cta_label',
        'cta_url',
        'cta_ticket_type_id',
        'promo_code',
        'status',
        'is_featured',
        'sort',
        'published_at',
        'expires_at',
        'share_card_path',
        'blurhash',
        'content_flags',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'content_flags' => 'array',
        'ready' => 'boolean',
        'is_featured' => 'boolean',
        'duration' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort' => 'integer',
        'impressions' => 'integer',
        'views' => 'integer',
        'completions' => 'integer',
        'likes' => 'integer',
        'saves' => 'integer',
        'shares' => 'integer',
        'cta_clicks' => 'integer',
        'conversions' => 'integer',
        'revenue_cents' => 'integer',
        'avg_watch_ratio' => 'decimal:3',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function ctaTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'cta_ticket_type_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShortEvent::class);
    }

    public function likeRecords(): HasMany
    {
        return $this->hasMany(ShortLike::class);
    }

    public function saveRecords(): HasMany
    {
        return $this->hasMany(ShortSave::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ShortShare::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ShortReminder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Everything the mobile feed is allowed to show right now.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            // Native uploads are only playable once the provider finished transcoding.
            ->where(fn (Builder $q) => $q->where('source', '!=', self::SOURCE_UPLOAD)->orWhere('ready', true));
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForOwner(Builder $query, Model $owner): Builder
    {
        return $query
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getIsExternalAttribute(): bool
    {
        return $this->source !== self::SOURCE_UPLOAD;
    }

    /**
     * Playback URL for the feed payload.
     *
     * Managed provider assets are signed at request time (short TTL) and are
     * never persisted — see docs/plans/shorts.md §C6.
     */
    public function getPlaybackUrlAttribute(): ?string
    {
        if ($this->is_external) {
            return $this->source_url;
        }

        if ($this->provider_asset_id && $this->video_provider) {
            $signed = $this->signedFromProvider('signedHls');
            if ($signed !== null) {
                return $signed;
            }
        }

        if ($this->hls_url) {
            return $this->hls_url;
        }

        if ($this->disk && $this->path) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return null;
    }

    public function getPosterUrlAttribute(): ?string
    {
        if ($this->poster_path) {
            if (str_starts_with($this->poster_path, 'http://') || str_starts_with($this->poster_path, 'https://')) {
                return $this->poster_path;
            }

            return Storage::disk('public')->url($this->poster_path);
        }

        if ($this->provider_asset_id && $this->video_provider) {
            return $this->signedFromProvider('signedPoster');
        }

        return null;
    }

    public function getAspectAttribute(): string
    {
        if ($this->width && $this->height) {
            return $this->width.':'.$this->height;
        }

        return '9:16';
    }

    /**
     * Ask the configured provider for a signed URL, tolerating an unconfigured
     * provider (placeholder keys in dev) without breaking the feed.
     */
    protected function signedFromProvider(string $method): ?string
    {
        try {
            $provider = app(VideoProvider::class);
        } catch (\Throwable) {
            return null;
        }

        if (! method_exists($provider, $method)) {
            return null;
        }

        try {
            return $provider->{$method}($this->provider_asset_id);
        } catch (\Throwable) {
            return null;
        }
    }
}
