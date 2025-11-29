@php
    $beneficiaries = $record->meta['beneficiaries'] ?? [];
@endphp

@if(!empty($beneficiaries))
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
    @foreach($beneficiaries as $index => $beneficiary)
        <div class="p-3 bg-gray-50 rounded-lg border">
            <div class="flex items-center mb-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-medium mr-2">
                    {{ $index + 1 }}
                </span>
                <span class="font-medium text-gray-900">
                    {{ $beneficiary['name'] ?? 'N/A' }}
                </span>
            </div>
            @if(!empty($beneficiary['email']))
                <div class="text-sm text-gray-600 flex items-center">
                    <x-heroicon-o-envelope class="w-4 h-4 mr-1.5 text-gray-400" />
                    <a href="mailto:{{ $beneficiary['email'] }}" class="hover:text-primary-600">
                        {{ $beneficiary['email'] }}
                    </a>
                </div>
            @endif
            @if(!empty($beneficiary['phone']))
                <div class="text-sm text-gray-600 flex items-center mt-1">
                    <x-heroicon-o-phone class="w-4 h-4 mr-1.5 text-gray-400" />
                    <a href="tel:{{ $beneficiary['phone'] }}" class="hover:text-primary-600">
                        {{ $beneficiary['phone'] }}
                    </a>
                </div>
            @endif
        </div>
    @endforeach
</div>
@else
    <p class="text-gray-500 text-sm">Nu sunt definiți beneficiari pentru această comandă.</p>
@endif
