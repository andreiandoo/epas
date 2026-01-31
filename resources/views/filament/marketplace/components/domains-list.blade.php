@if($domains->isEmpty())
    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-8 text-center">
        <x-heroicon-o-globe-alt class="w-10 h-10 text-gray-300 mx-auto mb-3" />
        <p class="text-sm text-gray-500 dark:text-gray-400">No additional domains configured.</p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">The marketplace uses the primary domain configured in the admin panel.</p>
    </div>
@else
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Domain</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Status</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Primary</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($domains as $domain)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30">
                        <td class="px-4 py-3">
                            <a href="https://{{ $domain->domain }}" target="_blank" class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 font-medium">
                                {{ $domain->domain }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            @if($domain->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Suspended
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($domain->is_primary)
                                <x-heroicon-s-star class="w-5 h-5 text-amber-500 mx-auto" />
                            @else
                                <span class="text-gray-300 dark:text-gray-600">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                            {{ $domain->created_at->format('M d, Y') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
