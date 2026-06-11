<?php

namespace App\Models\Integrations\GoogleWorkspace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveFile extends Model
{
    protected $fillable = [
        'connection_id', 'file_id', 'name', 'mime_type', 'size',
        'web_view_link', 'parent_folder_id', 'correlation_ref', 'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleWorkspaceConnection::class, 'connection_id');
    }
}
