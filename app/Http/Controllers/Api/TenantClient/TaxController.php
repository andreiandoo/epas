<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use App\Services\Tax\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TaxController extends Controller
{
    public function __construct(
        protected TaxService $taxService
    ) {}

    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant') ?? $request->header('X-Tenant-ID');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();
            return $domain?->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    /**
     * Get all applicable taxes for a given context
     */
    public function getApplicableTaxes(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'event_type_id' => 'nullable|integer|exists:event_types,id',
            'country' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $eventTypeId = $request->input('event_type_id');
        $country = $request->input('country');
        $county = $request->input('county');
        $city = $request->input('city');
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : null;

        $taxes = $this->taxService->getAllApplicableTaxes(
            $tenant->id,
            $eventTypeId,
            $country,
            $county,
            $city,
            $date
        );

        return response()->json([
            'success' => true,
            'data' => [
                'general' => $taxes['general']->map(fn ($tax) => [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'value' => (float) $tax->value,
                    'value_type' => $tax->value_type,
                    'currency' => $tax->currency,
                    'formatted_value' => $tax->getFormattedValue(),
                    'priority' => $tax->priority,
                    'event_type_id' => $tax->event_type_id,
                    'explanation' => $tax->explanation,
                ]),
                'local' => $taxes['local']->map(fn ($tax) => [
                    'id' => $tax->id,
                    'location' => $tax->getLocationString(),
                    'country' => $tax->country,
                    'county' => $tax->county,
                    'city' => $tax->city,
                    'value' => (float) $tax->value,
                    'formatted_value' => $tax->getFormattedValue(),
                    'priority' => $tax->priority,
                    'explanation' => $tax->explanation,
                    'source_url' => $tax->source_url,
                ]),
                'total_count' => $taxes['total_count'],
            ],
        ]);
    }

    /**
     * Calculate taxes for a given amount
     */
    public function calculateTaxes(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'event_type_id' => 'nullable|integer|exists:event_types,id',
            'country' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = (float) $request->input('amount');
        $currency = $request->input('currency');
        $eventTypeId = $request->input('event_type_id');
        $country = $request->input('country');
        $county = $request->input('county');
        $city = $request->input('city');
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : null;

        $result = $this->taxService->calculateTaxes(
            $tenant->id,
            $amount,
            $eventTypeId,
            $country,
            $county,
            $city,
            $date,
            $currency
        );

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
        ]);
    }

    /**
     * Get effective tax rate for a context
     */
    public function getEffectiveRate(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'event_type_id' => 'nullable|integer|exists:event_types,id',
            'country' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $eventTypeId = $request->input('event_type_id');
        $country = $request->input('country');
        $county = $request->input('county');
        $city = $request->input('city');
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : null;

        $rate = $this->taxService->getEffectiveTaxRate(
            $tenant->id,
            $eventTypeId,
            $country,
            $county,
            $city,
            $date
        );

        return response()->json([
            'success' => true,
            'data' => [
                'effective_rate' => round($rate, 4),
                'formatted_rate' => number_format($rate, 2) . '%',
            ],
        ]);
    }

    /**
     * Get tax summary for the tenant
     */
    public function getSummary(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $summary = $this->taxService->getTaxSummary($tenant->id);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get available locations (countries with local taxes)
     */
    public function getLocations(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Get unique countries with local taxes
        $countries = LocalTax::forTenant($tenant->id)
            ->active()
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'countries' => $countries,
            ],
        ]);
    }

    /**
     * Get counties for a country
     */
    public function getCounties(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'country' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $country = $request->input('country');
        $counties = LocalTax::getCountiesForCountry($tenant->id, $country);

        return response()->json([
            'success' => true,
            'data' => [
                'counties' => $counties,
            ],
        ]);
    }

    /**
     * Get taxes visible on checkout (including VAT for VAT payer tenants)
     */
    public function getCheckoutTaxes(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = (float) $request->input('amount');
        $currency = $request->input('currency', 'RON');

        // Check if tenant is VAT payer
        $isVatPayer = $tenant && (bool) $tenant->vat_payer;

        // Get global taxes that are visible on checkout
        $taxes = GeneralTax::query()
            ->whereNull('tenant_id') // Global taxes only
            ->active()
            ->visibleOnCheckout()
            ->validOn(Carbon::today())
            ->orderByDesc('priority')
            ->get();

        $taxBreakdown = [];
        $totalTax = 0;
        $vatAmount = 0;
        $vatRate = 0;

        foreach ($taxes as $tax) {
            // Check if this is a VAT tax
            $isVatTax = str_contains(strtolower($tax->name ?? ''), 'tva') ||
                        str_contains(strtolower($tax->name ?? ''), 'vat');

            // Skip VAT taxes if tenant is not a VAT payer
            if ($isVatTax && !$isVatPayer) {
                continue;
            }

            $taxAmount = $tax->calculateTax($amount);
            $totalTax += $taxAmount;

            if ($isVatTax) {
                $vatAmount = $taxAmount;
                $vatRate = (float) $tax->value;
            }

            $taxBreakdown[] = [
                'id' => $tax->id,
                'name' => $tax->name,
                'value' => (float) $tax->value,
                'value_type' => $tax->value_type,
                'formatted_value' => $tax->getFormattedValue(),
                'tax_amount' => round($taxAmount, 2),
                'explanation' => strip_tags($tax->explanation ?? ''),
                'is_added_to_price' => $tax->is_added_to_price,
                'is_vat' => $isVatTax,
            ];
        }

        // If tenant is VAT payer but no VAT tax found in global taxes, calculate standard VAT
        if ($isVatPayer && $vatAmount === 0) {
            // Standard Romanian VAT rate for tickets is 9% (reduced rate for cultural events)
            $standardVatRate = 9;
            $vatAmount = $amount * ($standardVatRate / 100);

            // Add VAT as first item (highest priority)
            array_unshift($taxBreakdown, [
                'id' => 0,
                'name' => 'TVA',
                'value' => $standardVatRate,
                'value_type' => 'percent',
                'formatted_value' => $standardVatRate . '%',
                'tax_amount' => round($vatAmount, 2),
                'explanation' => 'Taxa pe valoarea adaugata conform Codului Fiscal',
                'is_added_to_price' => false,
                'is_vat' => true,
            ]);

            $totalTax += $vatAmount;
            $vatRate = $standardVatRate;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'taxes' => $taxBreakdown,
                'total_tax' => round($totalTax, 2),
                'vat_amount' => round($vatAmount, 2),
                'vat_rate' => $vatRate,
                'is_vat_payer' => $isVatPayer,
                'base_amount' => $amount,
                'currency' => $currency,
            ],
        ]);
    }

    /**
     * Get cities for a country/county
     */
    public function getCities(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'country' => 'required|string|max:100',
            'county' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $country = $request->input('country');
        $county = $request->input('county');
        $cities = LocalTax::getCitiesForLocation($tenant->id, $country, $county);

        return response()->json([
            'success' => true,
            'data' => [
                'cities' => $cities,
            ],
        ]);
    }
}
