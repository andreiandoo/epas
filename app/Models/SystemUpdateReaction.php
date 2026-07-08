<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SystemUpdateReaction — anonymous session-based reaction on a
 * changelog entry. Identity is a long-lived browser cookie (SHA-256
 * hashed) so we can dedup + let the visitor toggle their own votes
 * without requiring a login.
 */
class SystemUpdateReaction extends Model
{
    protected $fillable = [
        'system_update_id',
        'session_hash',
        'reaction_type',
    ];

    /**
     * The reaction types the frontend and API both accept. Add here to
     * enable more emojis on the detail page.
     */
    public const TYPES = ['thumbs_up', 'heart', 'rocket', 'party'];

    public function systemUpdate(): BelongsTo
    {
        return $this->belongsTo(SystemUpdate::class);
    }
}
