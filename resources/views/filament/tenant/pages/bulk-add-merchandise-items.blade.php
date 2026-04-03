<x-filament-panels::page>
    {{-- Header form --}}
    <form wire:submit="save">
        {{ $this->headerForm }}

        @if(!$showResults)
        {{-- Spreadsheet-like table --}}
        <div class="mt-6" x-data="bulkTable()">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Produse</h3>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="addRows"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        +5 randuri
                    </button>
                    <button type="button" @click="pasteFromClipboard()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Paste din Excel
                    </button>
                </div>
            </div>

            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                Poti face paste direct din Excel/Google Sheets. Ordinea coloanelor: <strong>Nume | Tip | Unitate | Cantitate | Pret</strong>.
                Sau completeaza manual mai jos.
            </p>

            <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 w-8">#</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 min-w-[200px]">Nume produs *</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 w-32">Tip</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 w-28">Unitate</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 w-28">Cantitate *</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 w-32">Pret unitar (RON) *</th>
                            <th class="px-2 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($rows as $i => $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ trim($row['name'] ?? '') ? '' : 'opacity-70' }}">
                            <td class="px-2 py-1 text-xs text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-1 py-1">
                                <input type="text" wire:model.blur="rows.{{ $i }}.name"
                                    class="w-full px-2 py-1.5 text-sm border-gray-300 rounded dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="Pahar 500ml">
                            </td>
                            <td class="px-1 py-1">
                                <select wire:model="rows.{{ $i }}.type"
                                    class="w-full px-2 py-1.5 text-sm border-gray-300 rounded dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                    <option value="consumable">Consumabil</option>
                                    <option value="equipment">Echipament</option>
                                    <option value="packaging">Ambalaj</option>
                                    <option value="ingredient">Ingredient</option>
                                    <option value="other">Altele</option>
                                </select>
                            </td>
                            <td class="px-1 py-1">
                                <select wire:model="rows.{{ $i }}.unit"
                                    class="w-full px-2 py-1.5 text-sm border-gray-300 rounded dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                    <option value="buc">Bucati</option>
                                    <option value="kg">Kilograme</option>
                                    <option value="l">Litri</option>
                                    <option value="set">Seturi</option>
                                </select>
                            </td>
                            <td class="px-1 py-1">
                                <input type="number" step="0.01" min="0" wire:model.blur="rows.{{ $i }}.quantity"
                                    class="w-full px-2 py-1.5 text-sm border-gray-300 rounded dark:border-gray-600 dark:bg-gray-800 dark:text-white text-right focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="0">
                            </td>
                            <td class="px-1 py-1">
                                <input type="number" step="0.01" min="0" wire:model.blur="rows.{{ $i }}.price"
                                    class="w-full px-2 py-1.5 text-sm border-gray-300 rounded dark:border-gray-600 dark:bg-gray-800 dark:text-white text-right focus:ring-primary-500 focus:border-primary-500"
                                    placeholder="0.00">
                            </td>
                            <td class="px-1 py-1 text-center">
                                <button type="button" wire:click="removeRow({{ $i }})"
                                    class="text-gray-400 hover:text-red-500 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Summary --}}
            @php
                $filledRows = collect($rows)->filter(fn($r) => trim($r['name'] ?? '') !== '');
                $totalValue = $filledRows->sum(fn($r) => ((float)($r['quantity'] ?? 0)) * ((float)($r['price'] ?? 0)));
            @endphp
            <div class="flex items-center justify-between mt-4 px-2">
                <div class="text-sm text-gray-500">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $filledRows->count() }}</span> produse completate
                    &middot;
                    Valoare totala: <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($totalValue, 2) }} RON</span>
                </div>

                <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 transition">
                    <span wire:loading.remove wire:target="save">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </span>
                    <span wire:loading wire:target="save">
                        <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                    Salveaza {{ $filledRows->count() }} produse
                </button>
            </div>

            {{-- Hidden textarea for paste --}}
            <textarea x-ref="pasteArea" class="sr-only" @paste="handlePaste($event)"></textarea>
        </div>
        @endif

        {{-- Results --}}
        @if($showResults)
        <div class="mt-6 p-6 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-green-800 dark:text-green-200">{{ $savedCount }} produse adaugate!</h4>
                    <p class="text-sm text-green-600 dark:text-green-400">Produsele au fost salvate cu succes.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ MerchandiseItemResource::getUrl() }}"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">
                    Vezi lista produse
                </a>
                <button type="button" wire:click="resetForm"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                    Adauga alte produse
                </button>
            </div>
        </div>
        @endif
    </form>

    <script>
    function bulkTable() {
        return {
            pasteFromClipboard() {
                this.$refs.pasteArea.value = '';
                this.$refs.pasteArea.focus();
                // After paste event fires, handlePaste processes the data
            },
            handlePaste(event) {
                event.preventDefault();
                const text = (event.clipboardData || window.clipboardData).getData('text');
                if (!text) return;

                const typeMap = {
                    'consumabil': 'consumable', 'echipament': 'equipment',
                    'ambalaj': 'packaging', 'ingredient': 'ingredient', 'altele': 'other',
                };
                const unitMap = {
                    'bucati': 'buc', 'buc': 'buc', 'kilograme': 'kg', 'kg': 'kg',
                    'litri': 'l', 'l': 'l', 'seturi': 'set', 'set': 'set',
                };

                const lines = text.trim().split('\n').filter(l => l.trim());
                const newRows = [];

                for (const line of lines) {
                    // Split by tab (Excel) or semicolon (CSV)
                    const cells = line.includes('\t') ? line.split('\t') : line.split(';');

                    const name = (cells[0] || '').trim();
                    if (!name) continue;

                    const rawType = (cells[1] || '').trim().toLowerCase();
                    const rawUnit = (cells[2] || '').trim().toLowerCase();
                    const quantity = (cells[3] || '').trim().replace(',', '.');
                    const price = (cells[4] || '').trim().replace(',', '.');

                    newRows.push({
                        name: name,
                        type: typeMap[rawType] || 'consumable',
                        unit: unitMap[rawUnit] || 'buc',
                        quantity: quantity || '',
                        price: price || '',
                    });
                }

                if (newRows.length > 0) {
                    // Replace empty rows or append
                    @this.set('rows', newRows);
                }
            }
        };
    }
    </script>
</x-filament-panels::page>
