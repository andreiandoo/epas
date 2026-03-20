@php $c = $this->record; @endphp

<div class="grid gap-4 md:grid-cols-2">
    <x-filament::section>
        <x-slot name="heading">First Touch</x-slot>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach(['Source' => $c->first_source, 'Medium' => $c->first_medium, 'Campaign' => $c->first_campaign, 'Referrer' => $c->first_referrer, 'UTM' => implode(' / ', array_filter([$c->first_utm_source, $c->first_utm_medium, $c->first_utm_campaign]))] as $label => $value)
                <div class="flex justify-between px-1 py-2 text-sm"><span class="text-gray-500 dark:text-gray-400">{{ $label }}</span><span class="text-gray-900 dark:text-gray-100">{{ e($value ?: '—') }}</span></div>
            @endforeach
        </div>
    </x-filament::section>
    <x-filament::section>
        <x-slot name="heading">Last Touch</x-slot>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach(['Source' => $c->last_source, 'Medium' => $c->last_medium, 'Campaign' => $c->last_campaign, 'UTM' => implode(' / ', array_filter([$c->last_utm_source, $c->last_utm_medium, $c->last_utm_campaign]))] as $label => $value)
                <div class="flex justify-between px-1 py-2 text-sm"><span class="text-gray-500 dark:text-gray-400">{{ $label }}</span><span class="text-gray-900 dark:text-gray-100">{{ e($value ?: '—') }}</span></div>
            @endforeach
        </div>
    </x-filament::section>
</div>

<div class="mt-4">
    <x-filament::section>
        <x-slot name="heading">Click IDs & Platform</x-slot>
        <div class="grid gap-x-6 gap-y-0 divide-y divide-gray-100 dark:divide-gray-800 md:grid-cols-3 md:divide-y-0">
            @php
                $marketplace = $c->primary_marketplace_client_id ? (\App\Models\MarketplaceClient::find($c->primary_marketplace_client_id)?->name ?? 'ID:' . $c->primary_marketplace_client_id) : '—';
                $tenant = $c->primary_tenant_id ? (\App\Models\Tenant::find($c->primary_tenant_id)?->public_name ?? \App\Models\Tenant::find($c->primary_tenant_id)?->name ?? 'ID:' . $c->primary_tenant_id) : '—';
            @endphp
            @foreach([
                'Google (gclid)' => $c->first_gclid ? substr($c->first_gclid, 0, 20) . '...' : '—',
                'Facebook (fbclid)' => $c->first_fbclid ? substr($c->first_fbclid, 0, 20) . '...' : '—',
                'TikTok (ttclid)' => $c->first_ttclid ? substr($c->first_ttclid, 0, 20) . '...' : '—',
                'LinkedIn' => $c->first_li_fat_id ? substr($c->first_li_fat_id, 0, 20) . '...' : '—',
                'Marketplace' => $marketplace,
                'Tenant' => $tenant,
                'Device' => ucfirst($c->device_type ?? $c->primary_device ?? '—'),
                'Browser' => $c->browser ?? $c->primary_browser ?? '—',
                'OS' => $c->os ?? '—',
            ] as $label => $value)
                <div class="flex justify-between px-1 py-2 text-sm"><span class="text-gray-500 dark:text-gray-400">{{ $label }}</span><span class="text-gray-900 dark:text-gray-100">{{ e($value) }}</span></div>
            @endforeach
        </div>
    </x-filament::section>
</div>

{{-- Event Timeline --}}
@php $events = $c->events()->orderBy('created_at', 'desc')->limit(50)->get(); @endphp
@if($events->isNotEmpty())
    <div class="mt-4">
        <x-filament::section>
            <x-slot name="heading">Event Timeline (last 50)</x-slot>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800"><tr>
                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Time</th>
                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Event</th>
                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Page</th>
                        <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Value</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($events as $ev)
                            <tr>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $ev->created_at->format('d M H:i:s') }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium
                                        {{ match($ev->event_type) { 'purchase' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300', 'add_to_cart' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300', 'begin_checkout' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300', default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' } }}">{{ $ev->event_type }}</span>
                                </td>
                                <td class="max-w-xs px-3 py-2 text-gray-600 truncate dark:text-gray-400">{{ $ev->page_url ?? $ev->page_path ?? '—' }}</td>
                                <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">{{ $ev->conversion_value ? number_format((float)$ev->conversion_value, 2) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
@endif
