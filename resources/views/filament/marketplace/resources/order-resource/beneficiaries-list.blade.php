<div class="space-y-2">
    @php
        $beneficiaries = $record->meta['beneficiaries'] ?? [];
    @endphp

    @forelse($beneficiaries as $index => $beneficiary)
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-medium">
                    {{ $index + 1 }}
                </div>
                <div>
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $beneficiary['name'] ?? 'N/A' }}
                    </div>
                    @if(!empty($beneficiary['email']))
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $beneficiary['email'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="text-gray-500 dark:text-gray-400 text-center py-4">
            Nu exista beneficiari pentru aceasta comanda.
        </div>
    @endforelse
</div>
