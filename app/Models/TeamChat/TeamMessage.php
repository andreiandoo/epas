<?php

namespace App\Models\TeamChat;

use App\Models\MarketplaceAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMessage extends Model
{
    use SoftDeletes;

    // Cât timp își poate edita autorul mesajul.
    public const EDIT_WINDOW_MINUTES = 15;

    protected $fillable = [
        'team_thread_id',
        'marketplace_admin_id',
        'body',
        'mentions',
        'edited_at',
    ];

    protected $casts = [
        'mentions' => 'array',
        'edited_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(TeamThread::class, 'team_thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'marketplace_admin_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TeamMessageAttachment::class);
    }
}
