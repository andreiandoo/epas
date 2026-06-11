<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogComment extends Model
{
    use HasUuids;

    protected $table = 'blog_comments';

    protected $fillable = [
        'article_id',
        'tenant_id',
        'parent_id',
        'author_type',
        'user_id',
        'guest_name',
        'guest_email',
        'content',
        'content_html',
        'status',
        'like_count',
        'ip_address',
        'user_agent',
        'moderated_by',
        'moderated_at',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BlogComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'parent_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function getAuthorName(): string
    {
        if ($this->author_type === 'user' && $this->user) {
            return $this->user->name;
        }

        return $this->guest_name ?? 'Anonymous';
    }

    public function approve(?int $moderatorId = null): void
    {
        $this->update([
            'status' => 'approved',
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
        ]);

        // Update article comment count
        $this->article->increment('comment_count');
    }

    public function markAsSpam(?int $moderatorId = null): void
    {
        $this->update([
            'status' => 'spam',
            'moderated_by' => $moderatorId,
            'moderated_at' => now(),
        ]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForArticle($query, $articleId)
    {
        return $query->where('article_id', $articleId);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
