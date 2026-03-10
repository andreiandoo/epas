<div class="space-y-1">
    @if(count($rows) === 0)
        <div class="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
            Nu există evenimente încheiate.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10 text-left">
                        <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Eveniment</th>
                        <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Organizator</th>
                        <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Data</th>
                        <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400 text-right">Sold disponibil</th>
                        <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400 text-right">Acțiuni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $row['title'] }}</div>
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['organizer_name'] }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['event_date'] }}</td>
                            <td class="px-3 py-2 text-right font-mono">
                                @if($row['balance'] > 0)
                                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ number_format($row['balance'], 2) }} RON</span>
                                @else
                                    <span class="text-gray-400">0.00 RON</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($row['existing_payout'])
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['existing_payout']->reference }}</span>
                                        <a href="{{ \App\Filament\Marketplace\Resources\PayoutResource::getUrl('view', ['record' => $row['existing_payout']->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10">
                                            <x-heroicon-m-eye class="w-3.5 h-3.5" />
                                            Vezi decont
                                        </a>
                                    </div>
                                @elseif($row['balance'] > 0)
                                    <button type="button"
                                            wire:click="generateEventDecont({{ $row['event']->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="generateEventDecont({{ $row['event']->id }})"
                                            class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-500 disabled:opacity-50">
                                        <x-heroicon-m-document-plus class="w-3.5 h-3.5" />
                                        <span wire:loading.remove wire:target="generateEventDecont({{ $row['event']->id }})">Generează decont</span>
                                        <span wire:loading wire:target="generateEventDecont({{ $row['event']->id }})">Se generează...</span>
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400">Sold 0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
