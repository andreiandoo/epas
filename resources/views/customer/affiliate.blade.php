@extends('layouts.customer')

@section('title', __('Affiliate Program'))

@section('content')
    <div class="space-y-6">
        {{-- Page header --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Affiliate Program') }}</h1>
            <p class="text-gray-500 mt-1">{{ __('Earn commissions by referring customers to us.') }}</p>
        </div>

        {{-- Affiliate Dashboard Component --}}
        <livewire:customer.affiliate-dashboard :tenant="$tenant" />
    </div>
@endsection

@push('scripts')
<script>
    // Copy to clipboard functionality
    document.addEventListener('livewire:init', () => {
        Livewire.on('copy-to-clipboard', (data) => {
            navigator.clipboard.writeText(data.url).then(() => {
                // Success - component handles UI update
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        });

        Livewire.on('withdrawal-success', () => {
            // Could add confetti or other celebration effect here
        });
    });
</script>
@endpush
