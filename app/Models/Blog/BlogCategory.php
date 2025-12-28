<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    use Translatable;

    protected $table = 'blog_categories';

    public array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'image_url',
        'icon',
        'color',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_visible',
        'article_count',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'is_visible' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BlogCategory::class, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BlogArticle::class, 'category_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function updateArticleCount(): void
    {
        $this->update([
            'article_count' => $this->articles()->where('status', 'published')->count(),
        ]);
    }
}
