<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutomationEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'customer_id', 'current_step_id',
        'status', 'enrolled_at', 'completed_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function workflow(): BelongsTo { return $this->belongsTo(AutomationWorkflow::class, 'workflow_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function currentStep(): BelongsTo { return $this->belongsTo(AutomationStep::class, 'current_step_id'); }
}
