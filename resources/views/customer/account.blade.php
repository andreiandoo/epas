@extends('layouts.customer')

@section('title', __('My Account'))

@section('content')
    <div class="space-y-6">
        {{-- Welcome header --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900">
                {{ __('Welcome, :name!', ['name' => $customer->first_name ?? $customer->email]) }}
            </h1>
            <p class="text-gray-500 mt-1">{{ __('Manage your account and view your activity.') }}</p>
        </div>

        {{-- Affiliate signup card (if affiliate program is active) --}}
        @if($tenant->hasMicroservice('affiliate-tracking'))
            <livewire:customer.affiliate-signup-card :tenant="$tenant" />
        @endif

        {{-- Quick stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ $customer->orders()->where('tenant_id', $tenant->id)->count() }}
                        </div>
                        <div class="text-sm text-gray-500">{{ __('Total Orders') }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ $customer->orders()->where('tenant_id', $tenant->id)->withCount('tickets')->get()->sum('tickets_count') }}
                        </div>
                        <div class="text-sm text-gray-500">{{ __('Total Tickets') }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ number_format($customer->points_balance ?? 0) }}
                        </div>
                        <div class="text-sm text-gray-500">{{ __('Loyalty Points') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Account details --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Account Details') }}</h2>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Name') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->full_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Phone') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->phone ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('Member Since') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $customer->created_at->format('d M Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endsection
