<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    use Translatable;

    protected $table = 'blog_tags';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'article_count',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(BlogArticle::class, 'blog_article_tag', 'tag_id', 'article_id')
            ->withTimestamps();
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('article_count', 'desc')->limit($limit);
    }

    public function updateArticleCount(): void
    {
        $this->update([
            'article_count' => $this->articles()->where('status', 'published')->count(),
        ]);
    }
}
