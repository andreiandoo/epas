<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogAuthor extends Model
{
    protected $table = 'blog_authors';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'slug',
        'email',
        'bio',
        'short_bio',
        'avatar_url',
        'website_url',
        'twitter_handle',
        'linkedin_url',
        'github_url',
        'social_links',
        'is_active',
        'is_featured',
        'article_count',
        'total_views',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BlogArticle::class, 'author_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function updateStats(): void
    {
        $this->update([
            'article_count' => $this->articles()->where('status', 'published')->count(),
            'total_views' => $this->articles()->sum('view_count'),
        ]);
    }
}
