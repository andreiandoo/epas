<?php

namespace App\Models\Integrations\Jira;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JiraProject extends Model
{
    protected $fillable = [
        'connection_id', 'project_id', 'project_key', 'name',
        'project_type', 'is_synced',
    ];

    protected $casts = ['is_synced' => 'boolean'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(JiraConnection::class, 'connection_id');
    }
}
