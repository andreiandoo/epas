<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplaceOrganizerUser;
use App\Models\Tenant;
use App\Services\Marketplace\OrganizerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class OrganizerRegistrationController extends Controller
{
    protected OrganizerRegistrationService $registrationService;

    public function __construct(OrganizerRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    /**
     * Register a new organizer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        if (!$tenant->isMarketplace()) {
            return response()->json(['error' => 'This tenant is not a marketplace'], 403);
        }

        // Check if registration is enabled
        if (!($tenant->marketplace_settings['allow_registration'] ?? true)) {
            return response()->json(['error' => 'Organizer registration is currently disabled'], 403);
        }

        $validator = Validator::make($request->all(), [
            // Organizer data
            'organizer_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'company_name' => 'nullable|string|max:255',
            'cui' => 'nullable|string|max:50',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:10',
            'website_url' => 'nullable|url|max:255',

            // Admin user data
            'admin_name' => 'required|string|max:255',
            'admin_email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) use ($tenant) {
                    // Check if email is already registered for this marketplace
                    $exists = MarketplaceOrganizerUser::whereHas('organizer', function ($q) use ($tenant) {
                        $q->where('tenant_id', $tenant->id);
                    })->where('email', $value)->exists();

                    if ($exists) {
                        $fail('This email is already registered.');
                    }
                },
            ],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
            'admin_phone' => 'nullable|string|max:50',

            // Terms acceptance
            'accept_terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $organizerData = [
                'name' => $request->organizer_name,
                'description' => $request->description,
                'company_name' => $request->company_name,
                'cui' => $request->cui,
                'contact_name' => $request->contact_name,
                'contact_email' => $request->contact_email,
                'contact_phone' => $request->contact_phone,
                'city' => $request->city,
                'country' => $request->country ?? 'RO',
                'website_url' => $request->website_url,
            ];

            $userData = [
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => $request->admin_password,
                'phone' => $request->admin_phone,
            ];

            $organizer = $this->registrationService->register($tenant, $organizerData, $userData);

            return response()->json([
                'success' => true,
                'message' => 'Registration submitted successfully. You will be notified once your account is approved.',
                'organizer' => [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'status' => $organizer->status,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if an email is available for registration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['available' => false, 'error' => 'Invalid email format']);
        }

        $exists = MarketplaceOrganizerUser::whereHas('organizer', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        })->where('email', $request->email)->exists();

        return response()->json([
            'available' => !$exists,
        ]);
    }

    /**
     * Check organizer name availability.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkName(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['available' => false, 'error' => 'Invalid name']);
        }

        $slug = \Illuminate\Support\Str::slug($request->name);

        $exists = MarketplaceOrganizer::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->exists();

        return response()->json([
            'available' => !$exists,
            'slug' => $slug,
        ]);
    }

    /**
     * Resolve the marketplace tenant from the request.
     */
    protected function resolveMarketplace(Request $request): ?Tenant
    {
        $marketplaceId = $request->header('X-Marketplace-Id');
        if ($marketplaceId) {
            return Tenant::find($marketplaceId);
        }

        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        return Tenant::where('slug', $subdomain)
            ->orWhere('custom_domain', $host)
            ->first();
    }
}
