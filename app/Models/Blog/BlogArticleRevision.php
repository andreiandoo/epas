<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogArticleRevision extends Model
{
    protected $table = 'blog_article_revisions';

    protected $fillable = [
        'article_id',
        'tenant_id',
        'revision_number',
        'title',
        'content',
        'content_json',
        'change_summary',
        'changed_by',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'content_json' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(BlogArticle::class, 'article_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function restore(): void
    {
        $this->article->update([
            'title' => $this->title,
            'content' => $this->content,
            'content_json' => $this->content_json,
        ]);
    }
}
