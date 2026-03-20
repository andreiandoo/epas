@php $c = $this->record; @endphp

<x-filament::section>
    <x-slot name="heading">External Integrations</x-slot>
    <div class="grid gap-x-6 gap-y-0 divide-y divide-gray-100 dark:divide-gray-800 md:grid-cols-2 md:divide-y-0">
        @foreach([
            'Stripe Customer ID' => $c->stripe_customer_id ?? '—',
            'Facebook User ID' => $c->facebook_user_id ?? '—',
            'Google User ID' => $c->google_user_id ?? '—',
            'Cohort Month' => $c->cohort_month ?? '—',
            'Cohort Week' => $c->cohort_week ?? '—',
            'UUID' => $c->uuid ?? '—',
        ] as $label => $value)
            <div class="flex justify-between px-1 py-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <span class="font-mono text-gray-900 dark:text-gray-100" style="font-size:12px">{{ e($value) }}</span>
            </div>
        @endforeach
    </div>
</x-filament::section>
