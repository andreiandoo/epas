<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogArticleView extends Model
{
    protected $table = 'blog_article_views';

    protected $fillable = [
        'article_id',
        'tenant_id',
        'visitor_id',
        'user_id',
        'referrer',
        'source',
        'medium',
        'device',
        'country',
        'time_on_page',
        'scroll_depth',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(BlogArticle::class, 'article_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForArticle($query, $articleId)
    {
        return $query->where('article_id', $articleId);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
