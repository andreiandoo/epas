<?php

namespace App\Models\TeamChat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMessageAttachment extends Model
{
    // Atașamentele se șterg după 30 de zile; cele marcate „Păstrează”, după un an.
    public const RETENTION_DAYS = 30;
    public const KEEP_DAYS = 365;

    protected $fillable = [
        'team_message_id',
        'path',
        'name',
        'mime',
        'size',
        'keep',
        'purged_at',
    ];

    protected $casts = [
        'keep' => 'boolean',
        'purged_at' => 'datetime',
        'size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TeamMessage::class, 'team_message_id');
    }

    public function expiresAt(): ?\Carbon\CarbonInterface
    {
        return $this->created_at?->copy()->timezone('Europe/Bucharest')->addDays($this->keep ? self::KEEP_DAYS : self::RETENTION_DAYS);
    }
}
