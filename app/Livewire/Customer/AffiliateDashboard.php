<?php

namespace App\Livewire\Customer;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\AffiliateSettings;
use App\Models\AffiliateWithdrawal;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\AffiliateTrackingService;
use Livewire\Component;
use Livewire\WithPagination;

class AffiliateDashboard extends Component
{
    use WithPagination;

    public Tenant $tenant;
    public ?Customer $customer = null;
    public ?Affiliate $affiliate = null;
    public ?AffiliateSettings $settings = null;

    public array $stats = [];
    public string $trackingUrl = '';
    public bool $urlCopied = false;

    // Withdrawal form
    public bool $showWithdrawalModal = false;
    public float $withdrawalAmount = 0;
    public string $paymentMethod = 'bank_transfer';
    public array $paymentDetails = [];
    public string $withdrawalError = '';
    public string $withdrawalSuccess = '';

    // Payment detail fields
    public string $bankName = '';
    public string $iban = '';
    public string $accountHolder = '';
    public string $paypalEmail = '';
    public string $revolutTag = '';
    public string $wiseEmail = '';

    protected $listeners = ['refreshDashboard' => '$refresh'];

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->customer = auth('customer')->user();
        $this->settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();

        if ($this->customer) {
            $this->affiliate = $this->customer->getAffiliateFor($tenant->id);

            if ($this->affiliate) {
                $this->loadStats();
                $this->trackingUrl = $this->affiliate->getTrackingUrl();

                // Load saved payment details
                if ($this->affiliate->payment_method) {
                    $this->paymentMethod = $this->affiliate->payment_method;
                }
                $this->loadPaymentDetails();
            }
        }
    }

    protected function loadStats()
    {
        if (!$this->affiliate) {
            return;
        }

        $service = app(AffiliateTrackingService::class);
        $this->stats = $service->getAffiliateStats($this->affiliate->id);

        // Add click stats
        $this->stats['total_clicks'] = $this->affiliate->clicks()->count();
        $this->stats['clicks_this_month'] = $this->affiliate->clicks()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    protected function loadPaymentDetails()
    {
        $details = $this->affiliate->payment_details ?? [];

        $this->bankName = $details['bank_name'] ?? '';
        $this->iban = $details['iban'] ?? '';
        $this->accountHolder = $details['account_holder'] ?? '';
        $this->paypalEmail = $details['paypal_email'] ?? '';
        $this->revolutTag = $details['revolut_tag'] ?? '';
        $this->wiseEmail = $details['wise_email'] ?? '';
    }

    public function copyUrl()
    {
        $this->dispatch('copy-to-clipboard', url: $this->trackingUrl);
        $this->urlCopied = true;
    }

    public function openWithdrawalModal()
    {
        $this->withdrawalAmount = $this->affiliate->available_balance ?? 0;
        $this->withdrawalError = '';
        $this->withdrawalSuccess = '';
        $this->showWithdrawalModal = true;
    }

    public function closeWithdrawalModal()
    {
        $this->showWithdrawalModal = false;
    }

    public function requestWithdrawal()
    {
        $this->withdrawalError = '';
        $this->withdrawalSuccess = '';

        // Validate amount
        if ($this->withdrawalAmount <= 0) {
            $this->withdrawalError = __('Please enter a valid amount.');
            return;
        }

        if ($this->withdrawalAmount > $this->affiliate->available_balance) {
            $this->withdrawalError = __('Amount exceeds available balance.');
            return;
        }

        $minAmount = $this->settings->min_withdrawal_amount ?? 50;
        if ($this->withdrawalAmount < $minAmount) {
            $this->withdrawalError = __('Minimum withdrawal amount is :amount :currency.', [
                'amount' => number_format($minAmount, 2),
                'currency' => $this->settings->currency ?? 'RON',
            ]);
            return;
        }

        // Validate payment details based on method
        $paymentDetails = $this->validateAndGetPaymentDetails();
        if (!$paymentDetails) {
            return; // Error already set
        }

        try {
            // Create withdrawal request
            $withdrawal = $this->affiliate->requestWithdrawal(
                $this->withdrawalAmount,
                $this->paymentMethod,
                $paymentDetails,
                request()->ip()
            );

            if (!$withdrawal) {
                $this->withdrawalError = __('Unable to process withdrawal request. Please try again.');
                return;
            }

            // Save payment details to affiliate for future use
            $this->affiliate->update([
                'payment_method' => $this->paymentMethod,
                'payment_details' => $paymentDetails,
            ]);

            $this->withdrawalSuccess = __('Withdrawal request submitted successfully! Reference: :reference', [
                'reference' => $withdrawal->reference,
            ]);

            $this->loadStats();
            $this->affiliate->refresh();

            // Close modal after short delay
            $this->dispatch('withdrawal-success');

        } catch (\Exception $e) {
            $this->withdrawalError = __('An error occurred. Please try again later.');
            \Log::error('Withdrawal request error', [
                'affiliate_id' => $this->affiliate->id,
                'amount' => $this->withdrawalAmount,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function validateAndGetPaymentDetails(): ?array
    {
        switch ($this->paymentMethod) {
            case 'bank_transfer':
                if (empty($this->bankName) || empty($this->iban) || empty($this->accountHolder)) {
                    $this->withdrawalError = __('Please fill in all bank transfer details.');
                    return null;
                }
                return [
                    'bank_name' => $this->bankName,
                    'iban' => $this->iban,
                    'account_holder' => $this->accountHolder,
                ];

            case 'paypal':
                if (empty($this->paypalEmail)) {
                    $this->withdrawalError = __('Please enter your PayPal email.');
                    return null;
                }
                return ['paypal_email' => $this->paypalEmail];

            case 'revolut':
                if (empty($this->revolutTag)) {
                    $this->withdrawalError = __('Please enter your Revolut tag or phone number.');
                    return null;
                }
                return ['revolut_tag' => $this->revolutTag];

            case 'wise':
                if (empty($this->wiseEmail)) {
                    $this->withdrawalError = __('Please enter your Wise email.');
                    return null;
                }
                return ['wise_email' => $this->wiseEmail];

            default:
                $this->withdrawalError = __('Invalid payment method.');
                return null;
        }
    }

    public function getRecentConversionsProperty()
    {
        if (!$this->affiliate) {
            return collect();
        }

        return AffiliateConversion::where('affiliate_id', $this->affiliate->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function getRecentWithdrawalsProperty()
    {
        if (!$this->affiliate) {
            return collect();
        }

        return AffiliateWithdrawal::where('affiliate_id', $this->affiliate->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.customer.affiliate-dashboard');
    }
}
