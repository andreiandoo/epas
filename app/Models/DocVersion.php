<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_id',
        'version',
        'content',
        'changelog',
        'created_by',
    ];

    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
