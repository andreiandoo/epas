<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCityIntent extends Model
{
    use HasFactory, Translatable;

    protected $table = 'marketplace_city_intents';

    /**
     * Translatable JSON columns. Each stores an associative array keyed by
     * locale code, e.g. ['ro' => 'Indoor', 'en' => 'Indoor activities'].
     * Template strings support {placeholders} resolved at render time.
     */
    public array $translatable = [
        'name',
        'title_template',
        'h1_template',
        'meta_description_template',
        'intro_copy',
        'seo_copy',
    ];

    protected $fillable = [
        'marketplace_client_id',
        'slug',
        'name',
        'title_template',
        'h1_template',
        'meta_description_template',
        'intro_copy',
        'seo_copy',
        'filter_rule_json',
        'icon',
        'accent_color',
        'cover_image_url',
        'min_results_for_index',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'title_template' => 'array',
        'h1_template' => 'array',
        'meta_description_template' => 'array',
        'intro_copy' => 'array',
        'seo_copy' => 'array',
        'filter_rule_json' => 'array',
        'is_active' => 'boolean',
        'min_results_for_index' => 'integer',
        'sort_order' => 'integer',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    /**
     * Resolve a localized template string with placeholder substitution.
     *
     * Supported placeholders:
     *   {intent_label}  — name in $locale
     *   {city_name}     — pass via $context['city_name']
     *   {result_count}  — pass via $context['result_count']
     *
     * Any unknown placeholder is left intact so callers can detect mistakes.
     */
    public function renderTemplate(string $field, string $locale = 'ro', array $context = []): string
    {
        $tpl = $this->getTranslation($field, $locale) ?? $this->getTranslation($field, 'ro') ?? '';
        if ($tpl === '') {
            return '';
        }

        $context['intent_label'] = $context['intent_label']
            ?? $this->getTranslation('name', $locale)
            ?? $this->getTranslation('name', 'ro')
            ?? '';

        foreach ($context as $key => $value) {
            $tpl = str_replace('{' . $key . '}', (string) $value, $tpl);
        }

        return $tpl;
    }
}
