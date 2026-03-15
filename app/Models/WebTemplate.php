<?php

namespace App\Models;

use App\Enums\WebTemplateCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'thumbnail',
        'preview_image',
        'html_template_path',
        'tech_stack',
        'compatible_microservices',
        'default_demo_data',
        'customizable_fields',
        'color_scheme',
        'is_active',
        'is_featured',
        'sort_order',
        'version',
    ];

    protected $casts = [
        'category' => WebTemplateCategory::class,
        'tech_stack' => 'array',
        'compatible_microservices' => 'array',
        'default_demo_data' => 'array',
        'customizable_fields' => 'array',
        'color_scheme' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    public function customizations(): HasMany
    {
        return $this->hasMany(WebTemplateCustomization::class);
    }

    public function getDemoUrl(string $domain = 'tixello.ro'): string
    {
        $categoryPrefix = match ($this->category) {
            WebTemplateCategory::SimpleOrganizer => 'organizator',
            WebTemplateCategory::Marketplace => 'marketplace',
            WebTemplateCategory::ArtistAgency => 'agentie',
            WebTemplateCategory::Theater => 'teatru',
            WebTemplateCategory::Festival => 'festival',
            WebTemplateCategory::Stadium => 'stadion',
        };

        return "https://{$categoryPrefix}.{$domain}/{$this->slug}/demo";
    }

    public function getPreviewUrl(WebTemplateCustomization $customization, string $domain = 'tixello.ro'): string
    {
        $categoryPrefix = match ($this->category) {
            WebTemplateCategory::SimpleOrganizer => 'organizator',
            WebTemplateCategory::Marketplace => 'marketplace',
            WebTemplateCategory::ArtistAgency => 'agentie',
            WebTemplateCategory::Theater => 'teatru',
            WebTemplateCategory::Festival => 'festival',
            WebTemplateCategory::Stadium => 'stadion',
        };

        return "https://{$categoryPrefix}.{$domain}/{$this->slug}/{$customization->unique_token}";
    }
}
