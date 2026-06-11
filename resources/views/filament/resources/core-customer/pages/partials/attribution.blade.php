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

{{-- Event Timeline moved to dedicated "Engagement Tracking" tab --}}
