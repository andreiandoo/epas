<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An article on a media partner's site about one or more artists, shown on the
 * marketplace artist page as a short card linking back to the partner.
 */
class PartnerArticle extends Model
{
    protected $fillable = [
        'marketplace_partner_id',
        'marketplace_client_id',
        'source_id',
        'type',
        'title',
        'excerpt',
        'url',
        'image_url',
        'author',
        'published_at',
        'source_updated_at',
        'is_hidden',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'is_hidden' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(MarketplacePartner::class, 'marketplace_partner_id');
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'partner_article_artist');
    }

    /**
     * Articles a visitor may see: not hidden, already published, from an active partner.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('partner_articles.is_hidden', false)
            ->where('partner_articles.published_at', '<=', now())
            ->whereHas('partner', fn ($q) => $q->where('status', 'active'));
    }
}
