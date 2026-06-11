<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebTemplateFeedback extends Model
{
    protected $table = 'web_template_feedbacks';

    protected $fillable = [
        'web_template_customization_id',
        'rating',
        'comment',
        'name',
        'email',
        'company',
        'ip_hash',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function customization(): BelongsTo
    {
        return $this->belongsTo(WebTemplateCustomization::class, 'web_template_customization_id');
    }
}
