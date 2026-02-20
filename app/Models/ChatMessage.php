<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chat_conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_results',
        'tokens_used',
        'rating',
        'created_at',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'tokens_used' => 'integer',
        'rating' => 'integer',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
