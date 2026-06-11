<?php

namespace App\Models\Integrations\Jira;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JiraIssue extends Model
{
    protected $fillable = [
        'connection_id', 'issue_id', 'issue_key', 'project_key', 'issue_type',
        'summary', 'description', 'status', 'priority', 'assignee_id',
        'reporter_id', 'direction', 'correlation_ref', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(JiraConnection::class, 'connection_id');
    }
}
