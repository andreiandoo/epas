<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutomationStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'order', 'type', 'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    const TYPE_EMAIL = 'email';
    const TYPE_WAIT = 'wait';
    const TYPE_CONDITION = 'condition';
    const TYPE_ACTION = 'action';

    public function workflow(): BelongsTo { return $this->belongsTo(AutomationWorkflow::class, 'workflow_id'); }
}
