<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplaceOrganizerUser;
use App\Models\Tenant;
use App\Notifications\Marketplace\OrganizerRegistrationSubmitted;
use App\Notifications\Marketplace\OrganizerApproved;
use App\Notifications\Marketplace\OrganizerSuspended;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * OrganizerRegistrationService
 *
 * Handles organizer registration, approval, and management.
 */
class OrganizerRegistrationService
{
    /**
     * Register a new organizer.
     *
     * @param Tenant $marketplace The marketplace tenant
     * @param array $organizerData Organizer data
     * @param array $userData Admin user data
     * @return MarketplaceOrganizer The created organizer
     */
    public function register(Tenant $marketplace, array $organizerData, array $userData): MarketplaceOrganizer
    {
        if (!$marketplace->isMarketplace()) {
            throw new \InvalidArgumentException('Tenant is not a marketplace');
        }

        return DB::transaction(function () use ($marketplace, $organizerData, $userData) {
            // Create organizer
            $organizer = MarketplaceOrganizer::create([
                'tenant_id' => $marketplace->id,
                'name' => $organizerData['name'],
                'slug' => Str::slug($organizerData['name']),
                'status' => MarketplaceOrganizer::STATUS_PENDING_APPROVAL,
                'description' => $organizerData['description'] ?? null,
                // Company details
                'company_name' => $organizerData['company_name'] ?? null,
                'cui' => $organizerData['cui'] ?? null,
                'reg_com' => $organizerData['reg_com'] ?? null,
                'address' => $organizerData['address'] ?? null,
                'city' => $organizerData['city'] ?? null,
                'county' => $organizerData['county'] ?? null,
                'country' => $organizerData['country'] ?? 'RO',
                'postal_code' => $organizerData['postal_code'] ?? null,
                // Contact
                'contact_name' => $organizerData['contact_name'],
                'contact_email' => $organizerData['contact_email'],
                'contact_phone' => $organizerData['contact_phone'] ?? null,
                // Branding
                'logo' => $organizerData['logo'] ?? null,
                'website_url' => $organizerData['website_url'] ?? null,
                // Payout defaults
                'payout_method' => MarketplaceOrganizer::PAYOUT_BANK_TRANSFER,
                'payout_frequency' => MarketplaceOrganizer::PAYOUT_MONTHLY,
                'minimum_payout' => 50.00,
                'payout_currency' => $marketplace->currency ?? 'RON',
            ]);

            // Create admin user
            MarketplaceOrganizerUser::create([
                'organizer_id' => $organizer->id,
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'phone' => $userData['phone'] ?? null,
                'role' => MarketplaceOrganizerUser::ROLE_ADMIN,
                'is_active' => true,
            ]);

            // Notify marketplace admins about new registration
            $this->notifyMarketplaceAdmins($marketplace, $organizer);

            return $organizer;
        });
    }

    /**
     * Approve an organizer.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param int|null $approvedBy User ID who approved
     * @return bool Success status
     */
    public function approve(MarketplaceOrganizer $organizer, ?int $approvedBy = null): bool
    {
        if (!$organizer->isPendingApproval()) {
            return false;
        }

        $result = $organizer->approve($approvedBy);

        if ($result) {
            // Notify organizer admin users
            $adminUsers = $organizer->adminUsers;
            foreach ($adminUsers as $user) {
                try {
                    $user->notify(new OrganizerApproved($organizer));
                } catch (\Exception $e) {
                    // Log notification failure but don't fail the approval
                    \Log::warning("Failed to notify organizer user {$user->id}: " . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * Reject an organizer registration.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param string|null $reason Rejection reason
     * @return bool Success status
     */
    public function reject(MarketplaceOrganizer $organizer, ?string $reason = null): bool
    {
        if (!$organizer->isPendingApproval()) {
            return false;
        }

        $organizer->status = MarketplaceOrganizer::STATUS_CLOSED;
        $organizer->settings = array_merge($organizer->settings ?? [], [
            'rejection_reason' => $reason,
            'rejected_at' => now()->toISOString(),
        ]);

        return $organizer->save();
    }

    /**
     * Suspend an organizer.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param string|null $reason Suspension reason
     * @return bool Success status
     */
    public function suspend(MarketplaceOrganizer $organizer, ?string $reason = null): bool
    {
        if (!$organizer->isActive()) {
            return false;
        }

        $organizer->status = MarketplaceOrganizer::STATUS_SUSPENDED;
        $organizer->settings = array_merge($organizer->settings ?? [], [
            'suspension_reason' => $reason,
            'suspended_at' => now()->toISOString(),
        ]);

        if ($organizer->save()) {
            // Notify organizer admin users
            $adminUsers = $organizer->adminUsers;
            foreach ($adminUsers as $user) {
                try {
                    $user->notify(new OrganizerSuspended($organizer, $reason));
                } catch (\Exception $e) {
                    \Log::warning("Failed to notify organizer user {$user->id}: " . $e->getMessage());
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Reactivate a suspended organizer.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @return bool Success status
     */
    public function reactivate(MarketplaceOrganizer $organizer): bool
    {
        if (!$organizer->isSuspended()) {
            return false;
        }

        return $organizer->reactivate();
    }

    /**
     * Add a user to an organizer.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param array $userData User data
     * @return MarketplaceOrganizerUser The created user
     */
    public function addUser(MarketplaceOrganizer $organizer, array $userData): MarketplaceOrganizerUser
    {
        return MarketplaceOrganizerUser::create([
            'organizer_id' => $organizer->id,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'phone' => $userData['phone'] ?? null,
            'role' => $userData['role'] ?? MarketplaceOrganizerUser::ROLE_EDITOR,
            'position' => $userData['position'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Update organizer commission settings.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param string|null $type Commission type
     * @param float|null $percent Commission percentage
     * @param float|null $fixed Fixed commission amount
     * @return bool Success status
     */
    public function updateCommission(
        MarketplaceOrganizer $organizer,
        ?string $type,
        ?float $percent = null,
        ?float $fixed = null
    ): bool {
        $organizer->commission_type = $type;
        $organizer->commission_percent = $type && in_array($type, ['percent', 'both']) ? $percent : null;
        $organizer->commission_fixed = $type && in_array($type, ['fixed', 'both']) ? $fixed : null;

        return $organizer->save();
    }

    /**
     * Update payout settings.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param array $settings Payout settings
     * @return bool Success status
     */
    public function updatePayoutSettings(MarketplaceOrganizer $organizer, array $settings): bool
    {
        if (isset($settings['method'])) {
            $organizer->payout_method = $settings['method'];
        }
        if (isset($settings['details'])) {
            $organizer->payout_details = $settings['details'];
        }
        if (isset($settings['frequency'])) {
            $organizer->payout_frequency = $settings['frequency'];
        }
        if (isset($settings['minimum'])) {
            $organizer->minimum_payout = $settings['minimum'];
        }
        if (isset($settings['currency'])) {
            $organizer->payout_currency = $settings['currency'];
        }

        return $organizer->save();
    }

    /**
     * Notify marketplace admins about new registration.
     */
    protected function notifyMarketplaceAdmins(Tenant $marketplace, MarketplaceOrganizer $organizer): void
    {
        // Get marketplace owner
        $owner = $marketplace->owner;

        if ($owner) {
            try {
                $owner->notify(new OrganizerRegistrationSubmitted($organizer));
            } catch (\Exception $e) {
                \Log::warning("Failed to notify marketplace owner: " . $e->getMessage());
            }
        }
    }

    /**
     * Get registration statistics for a marketplace.
     *
     * @param Tenant $marketplace The marketplace
     * @return array Statistics
     */
    public function getRegistrationStats(Tenant $marketplace): array
    {
        $organizers = MarketplaceOrganizer::where('tenant_id', $marketplace->id);

        return [
            'total' => (clone $organizers)->count(),
            'pending_approval' => (clone $organizers)->pendingApproval()->count(),
            'active' => (clone $organizers)->active()->count(),
            'suspended' => (clone $organizers)->where('status', MarketplaceOrganizer::STATUS_SUSPENDED)->count(),
            'verified' => (clone $organizers)->verified()->count(),
        ];
    }
}
