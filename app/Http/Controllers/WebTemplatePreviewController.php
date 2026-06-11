<?php

namespace App\Http\Controllers;

use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use App\Models\WebTemplateFeedback;
use App\Services\WebTemplate\DemoDataTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $demoData = $this->getCachedTransformedData(
            "wt_demo_{$template->id}",
            $template->default_demo_data ?? []
        );

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
                if ($request->isMethod('post') && $request->has('password')) {
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

        $mergedData = $this->getCachedTransformedData(
            "wt_custom_{$customization->id}_{$customization->updated_at?->timestamp}",
            $customization->getMergedData()
        );

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
            $demoData = $this->getCachedTransformedData(
                "wt_api_custom_{$customization->id}_{$customization->updated_at?->timestamp}",
                $customization->getMergedData()
            );
            $colorScheme = array_merge(
                $template->color_scheme ?? [],
                array_filter([
                    'primary' => $customization->customization_data['primary_color'] ?? null,
                    'secondary' => $customization->customization_data['secondary_color'] ?? null,
                    'accent' => $customization->customization_data['accent_color'] ?? null,
                ])
            );
        } else {
            $demoData = $this->getCachedTransformedData(
                "wt_api_demo_{$template->id}",
                $template->default_demo_data ?? []
            );
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

    /**
     * Submit prospect feedback on a customized preview.
     */
    public function submitFeedback(Request $request, string $token)
    {
        $customization = WebTemplateCustomization::where('unique_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:100',
        ]);

        // Rate limit: 1 feedback per IP per customization per hour
        $ipHash = substr(md5($request->ip()), 0, 16);
        $recentFeedback = WebTemplateFeedback::where('web_template_customization_id', $customization->id)
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($recentFeedback) {
            return response()->json([
                'success' => false,
                'message' => 'Ai trimis deja feedback recent. Încearcă din nou mai târziu.',
            ], 429);
        }

        WebTemplateFeedback::create([
            'web_template_customization_id' => $customization->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'company' => $validated['company'] ?? null,
            'ip_hash' => $ipHash,
        ]);

        // Notify admins about new feedback
        $avgRating = $customization->getAverageRating();
        $feedbackCount = $customization->feedbacks()->count();

        if (in_array($feedbackCount, [1, 5, 10, 25])) {
            $users = \App\Models\User::where('role', 'admin')->orWhere('role', 'super-admin')->get();
            foreach ($users as $user) {
                \Filament\Notifications\Notification::make()
                    ->title("Feedback nou pe \u{201E}{$customization->label}\u{201D}")
                    ->body("{$feedbackCount} feedback-uri primite · Rating mediu: {$avgRating}/5")
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->iconColor($avgRating >= 4 ? 'success' : ($avgRating >= 3 ? 'warning' : 'danger'))
                    ->sendToDatabase($user);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Mulțumim pentru feedback!',
            'average_rating' => $avgRating,
            'feedback_count' => $feedbackCount,
        ]);
    }

    /**
     * Self-service editing portal for clients.
     */
    public function selfService(Request $request, string $token)
    {
        $customization = WebTemplateCustomization::where('self_service_token', $token)
            ->where('status', 'active')
            ->with('template')
            ->firstOrFail();

        $allowedFields = $customization->getAllowedSelfServiceFields();

        if ($request->isMethod('post')) {
            $currentData = $customization->customization_data ?? [];
            $allowedKeys = collect($allowedFields)->pluck('key')->toArray();

            foreach ($request->input('fields', []) as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $currentData[$key] = $value;
                }
            }

            $oldTimestamp = $customization->updated_at?->timestamp;
            $customization->update(['customization_data' => $currentData]);

            // Bust cache for this customization (include daily suffix used by getCachedTransformedData)
            $today = date('Y-m-d');
            Cache::forget("wt_custom_{$customization->id}_{$oldTimestamp}_{$today}");
            Cache::forget("wt_api_custom_{$customization->id}_{$oldTimestamp}_{$today}");

            return redirect()->back()->with('success', 'Modificările au fost salvate cu succes!');
        }

        return view('web-templates.self-service', [
            'customization' => $customization,
            'template' => $customization->template,
            'allowedFields' => $allowedFields,
        ]);
    }

    /**
     * Cache transformed demo data for 5 minutes, keyed by day (dates change daily).
     */
    private function getCachedTransformedData(string $key, array $rawData): array
    {
        $cacheKey = $key . '_' . date('Y-m-d');
        return Cache::remember($cacheKey, 300, fn () => $this->transformer->transform($rawData));
    }
}
