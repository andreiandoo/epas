<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'contract_custom_variable_id',
        'value',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customVariable(): BelongsTo
    {
        return $this->belongsTo(ContractCustomVariable::class, 'contract_custom_variable_id');
    }
}
