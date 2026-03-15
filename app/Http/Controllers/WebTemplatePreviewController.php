<?php

namespace App\Http\Controllers;

use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use App\Services\WebTemplate\DemoDataTransformer;
use Illuminate\Http\Request;

class WebTemplatePreviewController extends Controller
{
    public function __construct(
        private DemoDataTransformer $transformer,
    ) {}

    /**
     * Show the demo preview of a template with default demo data.
     */
    public function preview(string $templateSlug)
    {
        $template = WebTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $demoData = $this->transformer->transform($template->default_demo_data ?? []);

        return view('web-templates.preview', [
            'template' => $template,
            'demo_data' => $demoData,
            'customization' => null,
            'color_scheme' => $template->color_scheme ?? [],
            'is_demo' => true,
        ]);
    }

    /**
     * Show a customized preview with merged data.
     */
    public function customizedPreview(string $templateSlug, string $token)
    {
        $template = WebTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $customization = WebTemplateCustomization::where('unique_token', $token)
            ->where('web_template_id', $template->id)
            ->where('status', 'active')
            ->firstOrFail();

        $customization->recordView();

        $mergedData = $this->transformer->transform($customization->getMergedData());

        return view('web-templates.preview', [
            'template' => $template,
            'demo_data' => $mergedData,
            'customization' => $customization,
            'color_scheme' => array_merge(
                $template->color_scheme ?? [],
                array_filter([
                    'primary' => $customization->customization_data['primary_color'] ?? null,
                    'secondary' => $customization->customization_data['secondary_color'] ?? null,
                    'accent' => $customization->customization_data['accent_color'] ?? null,
                ])
            ),
            'is_demo' => false,
        ]);
    }

    /**
     * API endpoint: return the merged data as JSON (for Alpine.js / frontend consumption).
     */
    public function templateData(string $templateSlug, ?string $token = null)
    {
        $template = WebTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        if ($token) {
            $customization = WebTemplateCustomization::where('unique_token', $token)
                ->where('web_template_id', $template->id)
                ->where('status', 'active')
                ->firstOrFail();

            $customization->recordView();
            $demoData = $this->transformer->transform($customization->getMergedData());
            $colorScheme = array_merge(
                $template->color_scheme ?? [],
                array_filter([
                    'primary' => $customization->customization_data['primary_color'] ?? null,
                    'secondary' => $customization->customization_data['secondary_color'] ?? null,
                    'accent' => $customization->customization_data['accent_color'] ?? null,
                ])
            );
        } else {
            $demoData = $this->transformer->transform($template->default_demo_data ?? []);
            $colorScheme = $template->color_scheme ?? [];
        }

        return response()->json([
            'template' => [
                'name' => $template->name,
                'slug' => $template->slug,
                'category' => $template->category->value,
                'category_label' => $template->category->label(),
                'version' => $template->version,
            ],
            'data' => $demoData,
            'color_scheme' => $colorScheme,
            'customizable_fields' => $template->customizable_fields ?? [],
        ]);
    }

    /**
     * List all active templates (for a gallery/showcase page).
     */
    public function index()
    {
        $templates = WebTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($t) => $t->category->value);

        return view('web-templates.index', [
            'templatesByCategory' => $templates,
        ]);
    }
}
