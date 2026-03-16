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
        $categoryPrefix = $this->getCategoryPrefix();
        return "https://{$categoryPrefix}.{$domain}/{$this->slug}/demo";
    }

    public function getPreviewUrl(WebTemplateCustomization $customization, string $domain = 'tixello.ro'): string
    {
        $categoryPrefix = $this->getCategoryPrefix();
        return "https://{$categoryPrefix}.{$domain}/{$this->slug}/{$customization->unique_token}";
    }

    public function getCategoryPrefix(): string
    {
        return match ($this->category) {
            WebTemplateCategory::SimpleOrganizer => 'organizator',
            WebTemplateCategory::Marketplace => 'marketplace',
            WebTemplateCategory::ArtistAgency => 'agentie',
            WebTemplateCategory::Theater => 'teatru',
            WebTemplateCategory::Festival => 'festival',
            WebTemplateCategory::Stadium => 'stadion',
        };
    }

    /**
     * Export template definition as array (for JSON export).
     */
    public function toExportArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category->value,
            'description' => $this->description,
            'html_template_path' => $this->html_template_path,
            'tech_stack' => $this->tech_stack,
            'compatible_microservices' => $this->compatible_microservices,
            'default_demo_data' => $this->default_demo_data,
            'customizable_fields' => $this->customizable_fields,
            'color_scheme' => $this->color_scheme,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'version' => $this->version,
            'exported_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Import a template from an export array.
     */
    public static function importFromArray(array $data, bool $overwrite = false): self
    {
        $existing = static::where('slug', $data['slug'])->first();

        if ($existing && !$overwrite) {
            $data['slug'] = $data['slug'] . '-import-' . now()->format('His');
            $data['name'] = $data['name'] . ' (importat)';
        }

        unset($data['exported_at']);

        if ($existing && $overwrite) {
            $existing->update($data);
            return $existing->fresh();
        }

        return static::create($data);
    }
}
