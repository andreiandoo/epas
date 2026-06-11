@php $c = $this->record; @endphp

{{-- Core Email Engagement --}}
<x-filament::section>
    <x-slot name="heading">Email Engagement (Core)</x-slot>
    <div class="grid gap-x-6 gap-y-0 divide-y divide-gray-100 dark:divide-gray-800 md:grid-cols-3 md:divide-y-0">
        @foreach([
            'Subscribed' => $c->email_subscribed ? 'Yes' : 'No',
            'Emails Sent' => $c->emails_sent ?? 0,
            'Emails Opened' => $c->emails_opened ?? 0,
            'Emails Clicked' => $c->emails_clicked ?? 0,
            'Open Rate' => $c->email_open_rate !== null ? number_format((float) $c->email_open_rate, 1) . '%' : '—',
            'Click Rate' => $c->email_click_rate !== null ? number_format((float) $c->email_click_rate, 1) . '%' : '—',
            'Last Opened' => $c->last_email_opened_at?->format('d M Y H:i') ?? '—',
        ] as $label => $value)
            <div class="flex justify-between px-1 py-2 text-sm">
                <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
            </div>
        @endforeach
    </div>
</x-filament::section>

{{-- Consent & Privacy --}}
<div class="mt-4">
    <x-filament::section>
        <x-slot name="heading">Consent & Privacy</x-slot>
        <div class="grid gap-x-6 gap-y-0 divide-y divide-gray-100 dark:divide-gray-800 md:grid-cols-3 md:divide-y-0">
            @foreach([
                'Marketing Consent' => $c->marketing_consent ? 'Yes' : 'No',
                'Analytics Consent' => $c->analytics_consent ? 'Yes' : 'No',
                'Personalization' => $c->personalization_consent ? 'Yes' : 'No',
                'Consent Updated' => $c->consent_updated_at?->format('d M Y') ?? '—',
                'Consent Source' => $c->consent_source ?? '—',
                'GDPR Anonymized' => $c->is_anonymized ? 'Yes' : 'No',
            ] as $label => $value)
                <div class="flex justify-between px-1 py-2 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                    <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</div>

{{-- Marketplace Email Logs --}}
@if($this->hasMarketplaceData && !empty($emailLogs))
    <div class="mt-4">
        <x-filament::section>
            <x-slot name="heading">Istoric Email-uri Marketplace ({{ count($emailLogs) }})</x-slot>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800"><tr>
                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Subiect</th>
                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Data</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($emailLogs as $log)
                            <tr>
                                <td class="max-w-xs px-3 py-2 text-gray-800 truncate dark:text-gray-200">{{ $log->subject }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ match($log->status) { 'sent' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300', 'failed' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300', default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' } }}">{{ ucfirst($log->status) }}</span>
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $log->sent_at ? \Carbon\Carbon::parse($log->sent_at)->format('d.m.Y H:i') : ($log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('d.m.Y H:i') : '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
@endif
