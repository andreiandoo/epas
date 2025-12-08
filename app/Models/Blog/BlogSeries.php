<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogSeries extends Model
{
    use Translatable;

    protected $table = 'blog_series';

    public array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'cover_image_url',
        'is_complete',
        'is_visible',
        'article_count',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'is_complete' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BlogArticle::class, 'series_id')->orderBy('series_order');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function updateArticleCount(): void
    {
        $this->update([
            'article_count' => $this->articles()->where('status', 'published')->count(),
        ]);
    }
}
