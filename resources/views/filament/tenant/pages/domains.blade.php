<x-filament-panels::page>
    @if($domains->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border p-12 text-center">
            <x-heroicon-o-globe-alt class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No domains configured</h3>
            <p class="text-gray-600">Contact support to add your website domains.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Domain</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Status</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Primary</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Added</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($domains as $domain)
                        <tr>
                            <td class="px-6 py-4">
                                <a href="https://{{ $domain->domain }}" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $domain->domain }}
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                @if($domain->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Suspended
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($domain->is_primary)
                                    <x-heroicon-s-star class="w-5 h-5 text-yellow-500" />
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $domain->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $package = $domain->packages()->where('status', 'ready')->latest()->first();
                                    $tenant = auth()->user()->tenant;
                                @endphp
                                @if($package)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.tenant.package.download', ['tenant' => $tenant->id, 'domain' => $domain->id]) }}"
                                           class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200">
                                            <x-heroicon-o-arrow-down-tray class="w-3 h-3 mr-1" />
                                            Download
                                        </a>
                                        <a href="{{ route('admin.tenant.package.instructions', ['tenant' => $tenant->id, 'domain' => $domain->id]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-700 hover:bg-gray-200">
                                            <x-heroicon-o-document-text class="w-3 h-3 mr-1" />
                                            Instructions
                                        </a>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">No package</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
