<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Admin;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizersController extends BaseController
{
    /**
     * List all organizers
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.view')) {
            return $this->error('Unauthorized', 403);
        }

        $clientId = $admin->marketplace_client_id;

        $query = MarketplaceOrganizer::where('marketplace_client_id', $clientId);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('verified_only')) {
            $query->whereNotNull('verified_at');
        }

        if ($request->boolean('pending_only')) {
            $query->where('status', 'pending');
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $organizers = $query->paginate($perPage);

        return $this->paginated($organizers, function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'email' => $org->email,
                'phone' => $org->phone,
                'company_name' => $org->company_name,
                'status' => $org->status,
                'verified' => $org->verified_at !== null,
                'email_verified' => $org->email_verified_at !== null,
                'total_events' => $org->total_events,
                'total_tickets_sold' => $org->total_tickets_sold,
                'total_revenue' => (float) $org->total_revenue,
                'available_balance' => (float) $org->available_balance,
                'commission_rate' => $org->commission_rate,
                'created_at' => $org->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get pending organizers
     */
    public function pending(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.view')) {
            return $this->error('Unauthorized', 403);
        }

        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'email' => $org->email,
                'phone' => $org->phone,
                'company_name' => $org->company_name,
                'company_tax_id' => $org->company_tax_id,
                'description' => $org->description,
                'website' => $org->website,
                'email_verified' => $org->email_verified_at !== null,
                'created_at' => $org->created_at->toIso8601String(),
            ]);

        return $this->success([
            'organizers' => $organizers,
            'count' => $organizers->count(),
        ]);
    }

    /**
     * Get single organizer details
     */
    public function show(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.view')) {
            return $this->error('Unauthorized', 403);
        }

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->withCount('events')
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        return $this->success([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'email' => $organizer->email,
                'phone' => $organizer->phone,
                'contact_name' => $organizer->contact_name,
                'company_name' => $organizer->company_name,
                'company_tax_id' => $organizer->company_tax_id,
                'company_registration' => $organizer->company_registration,
                'company_address' => $organizer->company_address,
                'logo' => $organizer->logo,
                'description' => $organizer->description,
                'website' => $organizer->website,
                'social_links' => $organizer->social_links,
                'status' => $organizer->status,
                'verified' => $organizer->verified_at !== null,
                'verified_at' => $organizer->verified_at?->toIso8601String(),
                'email_verified' => $organizer->email_verified_at !== null,
                'commission_rate' => $organizer->commission_rate,
                'payout_details' => $organizer->payout_details,
                'settings' => $organizer->settings,
                'total_events' => $organizer->total_events,
                'events_count' => $organizer->events_count,
                'total_tickets_sold' => $organizer->total_tickets_sold,
                'total_revenue' => (float) $organizer->total_revenue,
                'available_balance' => (float) $organizer->available_balance,
                'pending_balance' => (float) $organizer->pending_balance,
                'total_paid_out' => (float) $organizer->total_paid_out,
                'created_at' => $organizer->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Approve an organizer
     */
    public function approve(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        if ($organizer->status !== 'pending') {
            return $this->error('Organizer is not pending approval', 400);
        }

        $organizer->update([
            'status' => 'active',
        ]);

        Log::channel('marketplace')->info('Organizer approved', [
            'organizer_id' => $organizer->id,
            'admin_id' => $admin->id,
        ]);

        // TODO: Send notification to organizer

        return $this->success([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'status' => $organizer->status,
            ],
        ], 'Organizer approved');
    }

    /**
     * Verify an organizer (mark as trusted)
     */
    public function verify(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        $organizer->update([
            'verified_at' => now(),
        ]);

        Log::channel('marketplace')->info('Organizer verified', [
            'organizer_id' => $organizer->id,
            'admin_id' => $admin->id,
        ]);

        return $this->success([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'verified' => true,
                'verified_at' => $organizer->verified_at->toIso8601String(),
            ],
        ], 'Organizer verified');
    }

    /**
     * Suspend an organizer
     */
    public function suspend(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        $organizer->update([
            'status' => 'suspended',
            'settings' => array_merge($organizer->settings ?? [], [
                'suspension_reason' => $validated['reason'],
                'suspended_at' => now()->toIso8601String(),
                'suspended_by' => $admin->id,
            ]),
        ]);

        Log::channel('marketplace')->warning('Organizer suspended', [
            'organizer_id' => $organizer->id,
            'admin_id' => $admin->id,
            'reason' => $validated['reason'],
        ]);

        // TODO: Send notification to organizer

        return $this->success(null, 'Organizer suspended');
    }

    /**
     * Reactivate a suspended organizer
     */
    public function reactivate(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        if ($organizer->status !== 'suspended') {
            return $this->error('Organizer is not suspended', 400);
        }

        $settings = $organizer->settings ?? [];
        unset($settings['suspension_reason'], $settings['suspended_at'], $settings['suspended_by']);

        $organizer->update([
            'status' => 'active',
            'settings' => $settings,
        ]);

        Log::channel('marketplace')->info('Organizer reactivated', [
            'organizer_id' => $organizer->id,
            'admin_id' => $admin->id,
        ]);

        return $this->success([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'status' => $organizer->status,
            ],
        ], 'Organizer reactivated');
    }

    /**
     * Update organizer commission rate
     */
    public function updateCommission(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:50',
        ]);

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        $oldRate = $organizer->commission_rate;
        $organizer->update(['commission_rate' => $validated['commission_rate']]);

        Log::channel('marketplace')->info('Organizer commission rate updated', [
            'organizer_id' => $organizer->id,
            'admin_id' => $admin->id,
            'old_rate' => $oldRate,
            'new_rate' => $validated['commission_rate'],
        ]);

        return $this->success([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'commission_rate' => (float) $organizer->commission_rate,
            ],
        ], 'Commission rate updated');
    }

    /**
     * Get organizer's events
     */
    public function events(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.view')) {
            return $this->error('Unauthorized', 403);
        }

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        $events = $organizer->events()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'status' => $e->status,
                'starts_at' => $e->starts_at->toIso8601String(),
                'venue_city' => $e->venue_city,
                'tickets_sold' => $e->tickets_sold,
                'revenue' => (float) $e->revenue,
            ]);

        return $this->success(['events' => $events]);
    }

    /**
     * Get organizer's transactions
     */
    public function transactions(Request $request, int $organizerId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('organizers.view')) {
            return $this->error('Unauthorized', 403);
        }

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $organizerId)
            ->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        $transactions = $organizer->transactions()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'description' => $t->description,
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return $this->success(['transactions' => $transactions]);
    }

    /**
     * Require authenticated admin
     */
    protected function requireAdmin(Request $request): MarketplaceAdmin
    {
        $admin = $request->user();

        if (!$admin instanceof MarketplaceAdmin) {
            abort(401, 'Unauthorized');
        }

        return $admin;
    }
}
