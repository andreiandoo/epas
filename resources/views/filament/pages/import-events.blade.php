<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Progress Steps --}}
        <nav class="flex items-center justify-center gap-2 text-sm">
            @foreach ([1 => 'Detalii eveniment', 2 => 'Upload fișier', 3 => 'Procesare', 4 => 'Rezultate'] as $num => $label)
                <div class="flex items-center gap-2">
                    <span @class([
                        'inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold',
                        'bg-primary-600 text-white' => $stage === $num,
                        'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $stage > $num,
                        'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $stage < $num,
                    ])>
                        @if($stage > $num)
                            <x-heroicon-m-check class="w-4 h-4" />
                        @else
                            {{ $num }}
                        @endif
                    </span>
                    <span @class([
                        'font-medium' => $stage === $num,
                        'text-gray-500 dark:text-gray-400' => $stage !== $num,
                    ])>{{ $label }}</span>
                    @if($num < 4)
                        <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-300 dark:text-gray-600" />
                    @endif
                </div>
            @endforeach
        </nav>

        {{-- Stage 1: Event Setup Form --}}
        @if($stage === 1)
            <x-filament::section>
                <x-slot name="heading">Configurare Eveniment</x-slot>
                <x-slot name="description">Completează detaliile evenimentului pe care vrei să îl importi. Datele despre bilete, comenzi și clienți vor fi extrase din fișierul încărcat.</x-slot>

                <div class="space-y-6">
                    {{ $this->eventSetupForm }}

                    <div class="flex justify-end pt-4 border-t dark:border-gray-700">
                        <x-filament::button wire:click="goToStage2" icon="heroicon-m-arrow-right" icon-position="after">
                            Continuă la Upload Fișier
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Stage 2: File Upload & Preview --}}
        @if($stage === 2)
            <x-filament::section>
                <x-slot name="heading">Încărcare Fișier</x-slot>
                <x-slot name="description">
                    Încarcă fișierul CSV/TSV exportat din {{ $eventFormData['import_source'] === 'iabilet' ? 'iabilet.ro' : 'sursa selectată' }}.
                </x-slot>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Fișier CSV / TSV
                        </label>
                        <input type="file" wire:model="uploadedFile" accept=".csv,.tsv,.txt,.xlsx"
                            class="block w-full text-sm text-gray-500 dark:text-gray-400
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-semibold
                                file:bg-primary-50 file:text-primary-700
                                dark:file:bg-primary-500/10 dark:file:text-primary-400
                                hover:file:bg-primary-100" />

                        <div wire:loading wire:target="uploadedFile" class="mt-2 text-sm text-gray-500">
                            <x-filament::loading-indicator class="w-4 h-4 inline" /> Se încarcă fișierul...
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <x-filament::button wire:click="uploadAndPreview" icon="heroicon-m-eye"
                            wire:loading.attr="disabled" wire:target="uploadAndPreview,uploadedFile">
                            Previzualizare
                        </x-filament::button>

                        <x-filament::button wire:click="goBackToStage1" color="gray" icon="heroicon-m-arrow-left">
                            Înapoi
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            {{-- Preview Table --}}
            @if(!empty($csvPreview))
                <x-filament::section>
                    <x-slot name="heading">
                        Previzualizare date (primele {{ count($csvPreview) }} din {{ number_format($csvTotalRows) }} rânduri)
                    </x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-2">ID Comandă</th>
                                    <th class="px-3 py-2">Data</th>
                                    <th class="px-3 py-2">Client</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Tip Bilet</th>
                                    <th class="px-3 py-2">Loc</th>
                                    <th class="px-3 py-2">Preț</th>
                                    <th class="px-3 py-2">Cod Bare</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($csvPreview as $row)
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $row['order_id'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-xs">{{ $row['order_date'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['client_name'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-xs">{{ $row['email'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['ticket_type'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['seat'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono">{{ $row['price'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $row['barcode'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-xs">{{ $row['order_status'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

                {{-- Discovered Ticket Types --}}
                @if(!empty($discoveredTicketTypes))
                    <x-filament::section>
                        <x-slot name="heading">Tipuri bilete descoperite</x-slot>

                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($discoveredTicketTypes as $name => $info)
                                <div class="rounded-lg border dark:border-gray-700 p-4">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $name }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ number_format($info['count']) }} bilete &middot;
                                        {{ number_format($info['price'], 2) }} RON
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-500/10 rounded-lg text-sm text-blue-700 dark:text-blue-400">
                            <strong>Total:</strong> {{ number_format($csvTotalRows) }} bilete &middot;
                            {{ number_format($totalOrders) }} comenzi &middot;
                            {{ count($discoveredTicketTypes) }} tipuri bilete
                        </div>
                    </x-filament::section>

                    <div class="flex justify-end gap-3">
                        <x-filament::button wire:click="goBackToStage1" color="gray" icon="heroicon-m-arrow-left">
                            Modifică detalii eveniment
                        </x-filament::button>
                        <x-filament::button wire:click="startProcessing" color="success" icon="heroicon-m-play"
                            wire:loading.attr="disabled">
                            Procesează Import
                        </x-filament::button>
                    </div>
                @endif
            @endif
        @endif

        {{-- Stage 3: Processing --}}
        @if($stage === 3)
            <x-filament::section>
                <x-slot name="heading">Se procesează importul...</x-slot>

                <div class="space-y-4">
                    @if($isProcessing)
                        <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            <span>{{ $processingStatus }}</span>
                        </div>

                        <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                            <div class="bg-primary-600 h-3 rounded-full transition-all duration-300"
                                style="width: 100%"></div>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Se creează evenimente, comenzi, bilete și clienți...
                        </p>
                    @else
                        @if($processingStatus && str_starts_with($processingStatus, 'Eroare'))
                            <div class="p-4 bg-danger-50 dark:bg-danger-500/10 rounded-lg text-danger-700 dark:text-danger-400">
                                {{ $processingStatus }}
                            </div>
                            <x-filament::button wire:click="goBackToStage1" color="gray" icon="heroicon-m-arrow-left">
                                Înapoi la configurare
                            </x-filament::button>
                        @endif
                    @endif
                </div>
            </x-filament::section>
        @endif

        {{-- Stage 4: Results --}}
        @if($stage === 4 && $importResults)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-success-500" />
                        Import Finalizat
                    </span>
                </x-slot>

                {{-- Stats Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="rounded-lg bg-success-50 dark:bg-success-500/10 p-4 text-center">
                        <div class="text-2xl font-bold text-success-700 dark:text-success-400">
                            {{ number_format($importResults['total_tickets']) }}
                        </div>
                        <div class="text-xs text-success-600 dark:text-success-500 mt-1">Bilete importate</div>
                    </div>

                    <div class="rounded-lg bg-blue-50 dark:bg-blue-500/10 p-4 text-center">
                        <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">
                            {{ number_format($importResults['total_orders']) }}
                        </div>
                        <div class="text-xs text-blue-600 dark:text-blue-500 mt-1">Comenzi create</div>
                    </div>

                    <div class="rounded-lg bg-purple-50 dark:bg-purple-500/10 p-4 text-center">
                        <div class="text-2xl font-bold text-purple-700 dark:text-purple-400">
                            {{ number_format($importResults['customers_created']) }}
                        </div>
                        <div class="text-xs text-purple-600 dark:text-purple-500 mt-1">Clienți noi</div>
                    </div>

                    <div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 p-4 text-center">
                        <div class="text-2xl font-bold text-amber-700 dark:text-amber-400">
                            {{ number_format($importResults['customers_enriched']) }}
                        </div>
                        <div class="text-xs text-amber-600 dark:text-amber-500 mt-1">Clienți îmbogățiți</div>
                    </div>
                </div>

                {{-- Additional Stats --}}
                @if($importResults['anonymous_orders'] > 0)
                    <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-500/10 rounded-lg text-sm text-yellow-700 dark:text-yellow-400">
                        <x-heroicon-m-exclamation-triangle class="w-4 h-4 inline" />
                        {{ $importResults['anonymous_orders'] }} comenzi anonime (fără email client) au fost importate cu succes.
                    </div>
                @endif

                {{-- Ticket Types Summary --}}
                @if(!empty($importResults['ticket_types_summary']))
                    <div class="mt-4">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tipuri bilete create:</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2">Tip bilet</th>
                                        <th class="px-3 py-2 text-right">Cantitate</th>
                                        <th class="px-3 py-2 text-right">Preț</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($importResults['ticket_types_summary'] as $tt)
                                        <tr class="border-b dark:border-gray-700">
                                            <td class="px-3 py-2 font-medium">{{ $tt['name'] }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($tt['count']) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ number_format($tt['price'], 2) }} RON</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Errors --}}
                @if(!empty($importResults['errors']))
                    <div class="mt-4 p-3 bg-danger-50 dark:bg-danger-500/10 rounded-lg">
                        <h4 class="text-sm font-semibold text-danger-700 dark:text-danger-400 mb-2">
                            Erori ({{ count($importResults['errors']) }}):
                        </h4>
                        <ul class="list-disc list-inside text-xs text-danger-600 dark:text-danger-400 space-y-1">
                            @foreach(array_slice($importResults['errors'], 0, 20) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            @if(count($importResults['errors']) > 20)
                                <li>... și încă {{ count($importResults['errors']) - 20 }} erori</li>
                            @endif
                        </ul>
                    </div>
                @endif
            </x-filament::section>

            {{-- Actions --}}
            <div class="flex justify-between">
                <x-filament::button wire:click="resetImport" color="gray" icon="heroicon-m-arrow-path">
                    Import Nou
                </x-filament::button>

                @if($importResults['event_id'])
                    <x-filament::button
                        tag="a"
                        href="{{ url('/admin/events/' . $importResults['event_id'] . '/edit') }}"
                        icon="heroicon-m-arrow-top-right-on-square"
                        icon-position="after">
                        Vezi Evenimentul
                    </x-filament::button>
                @endif
            </div>
        @endif

    </div>
</x-filament-panels::page>
