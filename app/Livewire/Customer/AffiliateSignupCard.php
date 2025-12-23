<?php

namespace App\Livewire\Customer;

use App\Models\Affiliate;
use App\Models\AffiliateSettings;
use App\Models\Customer;
use App\Models\Tenant;
use App\Notifications\NewAffiliateSignupNotification;
use Livewire\Component;

class AffiliateSignupCard extends Component
{
    public Tenant $tenant;
    public ?Customer $customer = null;
    public ?Affiliate $affiliate = null;
    public ?AffiliateSettings $settings = null;

    public bool $termsAccepted = false;
    public bool $showSignupForm = false;
    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->customer = auth('customer')->user();
        $this->settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();

        if ($this->customer) {
            $this->affiliate = $this->customer->getAffiliateFor($tenant->id);
        }
    }

    public function showForm()
    {
        $this->showSignupForm = true;
    }

    public function hideForm()
    {
        $this->showSignupForm = false;
        $this->termsAccepted = false;
        $this->errorMessage = '';
    }

    public function signup()
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        // Validate terms accepted
        if (!$this->termsAccepted) {
            $this->errorMessage = __('You must accept the affiliate terms and conditions.');
            return;
        }

        // Check if customer already has an affiliate account
        if ($this->affiliate) {
            $this->errorMessage = __('You already have an affiliate account.');
            return;
        }

        // Check settings
        if (!$this->settings || !$this->settings->is_active) {
            $this->errorMessage = __('The affiliate program is not available at this time.');
            return;
        }

        if (!$this->settings->allow_self_registration) {
            $this->errorMessage = __('Self-registration is not available. Please contact us to join the affiliate program.');
            return;
        }

        try {
            // Determine initial status based on approval requirement
            $status = $this->settings->require_approval
                ? Affiliate::STATUS_PENDING
                : Affiliate::STATUS_ACTIVE;

            // Create affiliate account
            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'name' => $this->customer->full_name ?? $this->customer->email,
                'contact_email' => $this->customer->email,
                'status' => $status,
                'commission_type' => $this->settings->default_commission_type ?? 'percent',
                'commission_rate' => $this->settings->default_commission_value ?? 10,
                'meta' => [
                    'terms_accepted_at' => now()->toIso8601String(),
                    'terms_accepted_ip' => request()->ip(),
                    'signup_source' => 'customer_account',
                ],
            ]);

            // Notify tenant admin about new affiliate signup
            $this->notifyTenantAdmin();

            $this->showSignupForm = false;

            if ($status === Affiliate::STATUS_PENDING) {
                $this->successMessage = __('Your affiliate application has been submitted! You will be notified once it is approved.');
            } else {
                $this->successMessage = __('Welcome to the affiliate program! Your account is now active.');
            }

            $this->dispatch('affiliate-signup-success');

        } catch (\Exception $e) {
            $this->errorMessage = __('An error occurred. Please try again later.');
            \Log::error('Affiliate signup error', [
                'customer_id' => $this->customer->id,
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyTenantAdmin(): void
    {
        try {
            // Notify tenant owner
            $owner = $this->tenant->owner;
            if ($owner) {
                $owner->notify(new NewAffiliateSignupNotification($this->affiliate));
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send affiliate signup notification', [
                'affiliate_id' => $this->affiliate->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.customer.affiliate-signup-card');
    }
}
