<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceCustomerBeneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Family / beneficiary CRUD for /cont/setari → Familie tab.
 *
 * One row per saved person; the customer can re-use them at checkout to
 * speed up "Pe ce nume e biletul" and they feed the recommendations engine
 * with age + declared interests.
 */
class BeneficiariesController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        // Defensive: marketplace_customer_beneficiaries was added in the
        // 2026-05-30 migration. If a deployed instance hasn't run it yet,
        // return an empty list instead of 500 so the UI degrades gracefully.
        try {
            $rows = MarketplaceCustomerBeneficiary::where('marketplace_customer_id', $customer->id)
                ->orderBy('is_active', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('Beneficiaries query failed (table likely missing)', ['error' => $e->getMessage()]);
            return $this->success(['beneficiaries' => []]);
        }

        return $this->success([
            'beneficiaries' => $rows->map(fn ($b) => $this->present($b))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $this->validatePayload($request);

        if (MarketplaceCustomerBeneficiary::where('marketplace_customer_id', $customer->id)
            ->whereNull('deleted_at')->count() >= 25) {
            return $this->error('Limita maximă este de 25 beneficiari per cont.', 422);
        }

        $beneficiary = MarketplaceCustomerBeneficiary::create(array_merge($validated, [
            'marketplace_client_id'   => $customer->marketplace_client_id,
            'marketplace_customer_id' => $customer->id,
            'is_active'               => true,
        ]));

        return $this->success(['beneficiary' => $this->present($beneficiary)], 'Beneficiarul a fost adăugat.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $beneficiary = MarketplaceCustomerBeneficiary::where('marketplace_customer_id', $customer->id)
            ->find($id);
        if (! $beneficiary) {
            return $this->error('Beneficiarul nu a fost găsit.', 404);
        }

        $validated = $this->validatePayload($request);
        $beneficiary->update($validated);

        return $this->success(['beneficiary' => $this->present($beneficiary->fresh())], 'Beneficiarul a fost actualizat.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $beneficiary = MarketplaceCustomerBeneficiary::where('marketplace_customer_id', $customer->id)
            ->find($id);
        if (! $beneficiary) {
            return $this->error('Beneficiarul nu a fost găsit.', 404);
        }

        $beneficiary->delete();

        return $this->success(null, 'Beneficiarul a fost șters.');
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'       => 'required|string|max:150',
            'relation'   => 'nullable|string|max:40',
            'birth_date' => 'nullable|date|before_or_equal:today',
            'email'      => 'nullable|email|max:200',
            'phone'      => 'nullable|string|max:30',
            'interests'  => 'nullable|array|max:20',
            'interests.*'=> 'string|max:50',
            'notes'      => 'nullable|string|max:1000',
            'is_active'  => 'sometimes|boolean',
        ]);
    }

    protected function present(MarketplaceCustomerBeneficiary $b): array
    {
        return [
            'id'         => $b->id,
            'name'       => $b->name,
            'relation'   => $b->relation,
            'birth_date' => optional($b->birth_date)->format('Y-m-d'),
            'age'        => $b->age,
            'email'      => $b->email,
            'phone'      => $b->phone,
            'interests'  => $b->interests ?? [],
            'notes'      => $b->notes,
            'is_active'  => (bool) $b->is_active,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
