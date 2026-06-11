<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Doc extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doc_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'type',
        'version',
        'status',
        'is_public',
        'is_featured',
        'order',
        'metadata',
        'tags',
        'author',
        'published_at',
        'parent_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
        'tags' => 'array',
        'published_at' => 'datetime',
    ];

    public const TYPES = [
        'general' => 'General',
        'component' => 'Component',
        'module' => 'Module',
        'microservice' => 'Microservice',
        'api' => 'API',
        'guide' => 'Guide',
        'tutorial' => 'Tutorial',
        'reference' => 'Reference',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($doc) {
            if (empty($doc->slug)) {
                $doc->slug = Str::slug($doc->title);
            }
        });

        static::saving(function ($doc) {
            if ($doc->status === 'published' && !$doc->published_at) {
                $doc->published_at = now();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocCategory::class, 'doc_category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Doc::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Doc::class, 'parent_id')->orderBy('order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocVersion::class)->orderByDesc('created_at');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true)->published();
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->public();
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('title');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('content', 'like', "%{$term}%")
              ->orWhere('excerpt', 'like', "%{$term}%")
              ->orWhereJsonContains('tags', $term);
        });
    }

    public function getReadTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, (int) ceil($wordCount / 200));
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function createVersion(?int $userId = null): DocVersion
    {
        return $this->versions()->create([
            'version' => $this->version,
            'content' => $this->content,
            'created_by' => $userId,
        ]);
    }
}
