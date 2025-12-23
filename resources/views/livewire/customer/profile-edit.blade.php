<div class="space-y-6">
    {{-- Profile Information --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">{{ __('Profile Information') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('Update your personal information.') }}</p>
        </div>
        <div class="p-6">
            @if($successMessage)
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-700">{{ $successMessage }}</p>
                </div>
            @endif

            @if($errorMessage)
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            @endif

            <form wire:submit="updateProfile" class="space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('First Name') }}
                        </label>
                        <input type="text"
                               id="firstName"
                               wire:model="firstName"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('firstName')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="lastName" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Last Name') }}
                        </label>
                        <input type="text"
                               id="lastName"
                               wire:model="lastName"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('lastName')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Email Address') }}
                    </label>
                    <input type="email"
                           id="email"
                           value="{{ $customer->email }}"
                           disabled
                           class="w-full rounded-lg border-gray-300 bg-gray-50 text-gray-500 shadow-sm cursor-not-allowed">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Email address cannot be changed.') }}</p>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Phone Number') }}
                    </label>
                    <input type="tel"
                           id="phone"
                           wire:model="phone"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="+40 7XX XXX XXX">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-4">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                        <span wire:loading.remove wire:target="updateProfile">{{ __('Save Changes') }}</span>
                        <span wire:loading wire:target="updateProfile">{{ __('Saving...') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Change Password --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">{{ __('Change Password') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('Update your password to keep your account secure.') }}</p>
        </div>
        <div class="p-6">
            @if($passwordSuccessMessage)
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-700">{{ $passwordSuccessMessage }}</p>
                </div>
            @endif

            @if($passwordErrorMessage)
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-700">{{ $passwordErrorMessage }}</p>
                </div>
            @endif

            <form wire:submit="updatePassword" class="space-y-4">
                <div>
                    <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('Current Password') }}
                    </label>
                    <input type="password"
                           id="currentPassword"
                           wire:model="currentPassword"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('currentPassword')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('New Password') }}
                        </label>
                        <input type="password"
                               id="newPassword"
                               wire:model="newPassword"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('newPassword')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="newPasswordConfirmation" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Confirm New Password') }}
                        </label>
                        <input type="password"
                               id="newPasswordConfirmation"
                               wire:model="newPasswordConfirmation"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('newPasswordConfirmation')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                        <span wire:loading.remove wire:target="updatePassword">{{ __('Change Password') }}</span>
                        <span wire:loading wire:target="updatePassword">{{ __('Updating...') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
