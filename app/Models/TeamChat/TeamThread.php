<?php

namespace App\Models\TeamChat;

use App\Models\MarketplaceAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamThread extends Model
{
    public const TYPE_DM = 'dm';
    public const TYPE_GROUP = 'group';
    public const TYPE_HELP = 'help';

    protected $fillable = [
        'marketplace_client_id',
        'type',
        'name',
        'system_key',
        'is_system',
        'support_department_id',
        'chat_conversation_id',
        'created_by_marketplace_admin_id',
        'last_message_at',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(TeamThreadMember::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceAdmin::class, 'team_thread_members', 'team_thread_id', 'marketplace_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamMessage::class);
    }
}
