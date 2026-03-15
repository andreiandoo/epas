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
    public function customizedPreview(Request $request, string $templateSlug, string $token)
    {
        $template = WebTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $customization = WebTemplateCustomization::where('unique_token', $token)
            ->where('web_template_id', $template->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Password protection check
        if ($customization->hasPassword()) {
            $sessionKey = 'wt_auth_' . $customization->unique_token;
            if (!session($sessionKey)) {
                if ($request->isMethod('post')) {
                    if ($customization->checkPassword($request->input('password'))) {
                        session([$sessionKey => true]);
                    } else {
                        return view('web-templates.password', [
                            'template' => $template,
                            'customization' => $customization,
                            'error' => 'Parolă incorectă.',
                        ]);
                    }
                } else {
                    return view('web-templates.password', [
                        'template' => $template,
                        'customization' => $customization,
                        'error' => null,
                    ]);
                }
            }
        }

        // Capture UTM parameters
        $utmParams = [
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
            'referrer' => $request->header('referer'),
        ];

        $customization->recordView(array_filter($utmParams) ?: null);

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
     * List all active templates (gallery page with Alpine.js filtering).
     */
    public function index()
    {
        $templates = WebTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('web-templates.index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Compare 2-3 templates side by side.
     */
    public function compare(Request $request)
    {
        $slugs = $request->query('templates', '');
        $slugList = array_filter(explode(',', $slugs));

        if (count($slugList) < 2 || count($slugList) > 3) {
            return redirect()->route('web-template.index')
                ->with('error', 'Selectează 2-3 template-uri pentru comparare.');
        }

        $templates = WebTemplate::where('is_active', true)
            ->whereIn('slug', $slugList)
            ->get();

        if ($templates->count() < 2) {
            return redirect()->route('web-template.index');
        }

        return view('web-templates.compare', [
            'templates' => $templates,
        ]);
    }
}
