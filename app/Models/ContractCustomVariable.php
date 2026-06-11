<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractCustomVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'label',
        'description',
        'type',
        'options',
        'default_value',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenantValues(): HasMany
    {
        return $this->hasMany(TenantCustomVariable::class);
    }

    /**
     * Get all active variables
     */
    public static function getActive()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get the placeholder key format
     */
    public function getPlaceholder(): string
    {
        return '{{' . $this->name . '}}';
    }
}
