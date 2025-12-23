<?php

namespace App\Livewire\Customer;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ProfileEdit extends Component
{
    public Tenant $tenant;
    public ?Customer $customer = null;

    // Profile fields
    public string $firstName = '';
    public string $lastName = '';
    public string $phone = '';

    // Password change fields
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    public string $successMessage = '';
    public string $errorMessage = '';
    public string $passwordSuccessMessage = '';
    public string $passwordErrorMessage = '';

    protected $rules = [
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'phone' => 'nullable|string|max:50',
    ];

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->customer = auth('customer')->user();

        if ($this->customer) {
            $this->firstName = $this->customer->first_name ?? '';
            $this->lastName = $this->customer->last_name ?? '';
            $this->phone = $this->customer->phone ?? '';
        }
    }

    public function updateProfile()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        $this->validate();

        try {
            $this->customer->update([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'phone' => $this->phone,
            ]);

            $this->successMessage = __('Profile updated successfully!');

        } catch (\Exception $e) {
            $this->errorMessage = __('An error occurred. Please try again.');
            \Log::error('Profile update error', [
                'customer_id' => $this->customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updatePassword()
    {
        $this->passwordSuccessMessage = '';
        $this->passwordErrorMessage = '';

        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required|min:8',
            'newPasswordConfirmation' => 'required|same:newPassword',
        ], [
            'newPasswordConfirmation.same' => __('The password confirmation does not match.'),
        ]);

        // Verify current password
        if (!Hash::check($this->currentPassword, $this->customer->password)) {
            $this->passwordErrorMessage = __('The current password is incorrect.');
            return;
        }

        try {
            $this->customer->update([
                'password' => Hash::make($this->newPassword),
            ]);

            $this->currentPassword = '';
            $this->newPassword = '';
            $this->newPasswordConfirmation = '';

            $this->passwordSuccessMessage = __('Password changed successfully!');

        } catch (\Exception $e) {
            $this->passwordErrorMessage = __('An error occurred. Please try again.');
            \Log::error('Password update error', [
                'customer_id' => $this->customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.customer.profile-edit');
    }
}
