<div>
    {{-- Check if affiliate program is active for this tenant --}}
    @if(!$settings || !$settings->is_active)
        {{-- Don't show anything if program is not active --}}
    @elseif($affiliate)
        {{-- Already an affiliate - show status card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    @if($affiliate->status === 'active')
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    @elseif($affiliate->status === 'pending')
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    @else
                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    @endif
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ $settings->program_name ?? __('Affiliate Program') }}
                    </h3>
                    @if($affiliate->status === 'active')
                        <p class="mt-1 text-sm text-green-600">{{ __('Your affiliate account is active!') }}</p>
                        <a href="{{ route('customer.affiliate', ['tenant' => $tenant->slug]) }}"
                           class="mt-3 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                            {{ __('View Dashboard') }}
                            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @elseif($affiliate->status === 'pending')
                        <p class="mt-1 text-sm text-yellow-600">{{ __('Your application is pending approval.') }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ __('We will notify you once your application is reviewed.') }}</p>
                    @else
                        <p class="mt-1 text-sm text-gray-600">{{ __('Status:') }} {{ $affiliate->getStatusLabel() }}</p>
                    @endif
                </div>
            </div>
        </div>

    @elseif($successMessage)
        {{-- Success message after signup --}}
        <div class="bg-green-50 border border-green-200 rounded-xl p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-green-900">{{ __('Success!') }}</h3>
                    <p class="mt-1 text-sm text-green-700">{{ $successMessage }}</p>
                </div>
            </div>
        </div>

    @elseif($showSignupForm)
        {{-- Signup form --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">{{ __('Join Our Affiliate Program') }}</h3>
            </div>
            <div class="p-6">
                @if($errorMessage)
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                    </div>
                @endif

                {{-- Program details --}}
                <div class="mb-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('Commission Rate') }}</h4>
                    <p class="text-2xl font-bold text-indigo-600">
                        @if($settings->default_commission_type === 'percent')
                            {{ number_format($settings->default_commission_value, 0) }}%
                        @else
                            {{ number_format($settings->default_commission_value, 2) }} {{ $settings->currency ?? 'RON' }}
                        @endif
                        <span class="text-sm font-normal text-gray-500">{{ __('per sale') }}</span>
                    </p>
                </div>

                @if($settings->program_benefits)
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('Benefits') }}</h4>
                        <ul class="space-y-2">
                            @foreach($settings->program_benefits as $benefit)
                                <li class="flex items-start text-sm text-gray-600">
                                    <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ $benefit }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Terms & Conditions --}}
                @if($settings->registration_terms)
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg max-h-48 overflow-y-auto">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('Terms & Conditions') }}</h4>
                        <div class="text-sm text-gray-600 prose prose-sm max-w-none">
                            {!! nl2br(e($settings->registration_terms)) !!}
                        </div>
                    </div>
                @endif

                {{-- Terms acceptance checkbox --}}
                <div class="mb-6">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox"
                               wire:model="termsAccepted"
                               class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <span class="ml-3 text-sm text-gray-600">
                            {{ __('I have read and agree to the affiliate program terms and conditions.') }}
                        </span>
                    </label>
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center justify-between">
                    <button type="button"
                            wire:click="hideForm"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button"
                            wire:click="signup"
                            wire:loading.attr="disabled"
                            class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                        <span wire:loading.remove wire:target="signup">{{ __('Join Program') }}</span>
                        <span wire:loading wire:target="signup">{{ __('Processing...') }}</span>
                    </button>
                </div>
            </div>
        </div>

    @else
        {{-- Initial signup card --}}
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 text-white">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold mb-2">
                    {{ $settings->program_name ?? __('Become an Affiliate') }}
                </h3>
                <p class="text-white/80 text-sm mb-4">
                    {{ $settings->program_description ?? __('Earn money by referring customers to us. Get a commission on every sale you generate!') }}
                </p>
                <div class="flex items-center mb-6">
                    <span class="text-3xl font-bold">
                        @if($settings->default_commission_type === 'percent')
                            {{ number_format($settings->default_commission_value, 0) }}%
                        @else
                            {{ number_format($settings->default_commission_value, 2) }} {{ $settings->currency ?? 'RON' }}
                        @endif
                    </span>
                    <span class="ml-2 text-white/70 text-sm">{{ __('commission per sale') }}</span>
                </div>
                @if($settings->allow_self_registration)
                    <button type="button"
                            wire:click="showForm"
                            class="w-full px-6 py-3 text-sm font-semibold text-indigo-600 bg-white rounded-lg hover:bg-gray-100 transition shadow-sm">
                        {{ __('Learn More & Join') }}
                    </button>
                @else
                    <p class="text-sm text-white/70 italic">
                        {{ __('Contact us to join the affiliate program.') }}
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
