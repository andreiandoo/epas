<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Existing external tickets count --}}
        @php
            $existingCount = \App\Models\ExternalTicket::where('event_id', $this->record->id)->count();
            $checkedInCount = \App\Models\ExternalTicket::where('event_id', $this->record->id)->whereNotNull('checked_in_at')->count();
        @endphp

        @if($existingCount > 0)
        <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-950 border border-primary-200 dark:border-primary-800">
            <div class="flex items-center gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $existingCount }}</div>
                    <div class="text-xs text-gray-500">Bilete externe</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $checkedInCount }}</div>
                    <div class="text-xs text-gray-500">Scanate</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $existingCount - $checkedInCount }}</div>
                    <div class="text-xs text-gray-500">Rămase</div>
                </div>
            </div>
        </div>
        @endif

        {{-- Step 1: Upload CSV --}}
        <x-filament::section>
            <x-slot name="heading">1. Încarcă fișier CSV</x-slot>
            <x-slot name="description">Selectează fișierul CSV cu biletele de la operatorul extern. Separatorul (virgulă sau punct-virgulă) este detectat automat.</x-slot>

            <div class="mb-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Nu știi cum să formatezi fișierul? Descarcă modelul CSV cu coloanele recunoscute automat.
                    </div>
                    <x-filament::button wire:click="downloadTemplate" color="gray" size="sm" icon="heroicon-o-arrow-down-tray">
                        Descarcă model CSV
                    </x-filament::button>
                </div>
            </div>

            <form wire:submit="uploadCsv" class="space-y-4">
                <div>
                    {{ $this->form }}
                </div>

                <div>
                    <x-filament::input.wrapper>
                        <input type="file" accept=".csv,.txt" wire:model="csv_file"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950 dark:file:text-primary-400">
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="uploadCsv">Citește CSV</span>
                    <span wire:loading wire:target="uploadCsv">Se citește...</span>
                </x-filament::button>
            </form>
        </x-filament::section>

        {{-- Step 2: Map columns --}}
        @if(!empty($this->csvHeaders))
        <x-filament::section>
            <x-slot name="heading">2. Mapare coloane ({{ $this->csvTotalRows }} rânduri detectate)</x-slot>
            <x-slot name="description">Verifică și ajustează maparea coloanelor. Barcode este obligatoriu.</x-slot>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @php
                    $headerOptions = array_combine($this->csvHeaders, $this->csvHeaders);
                    $headerOptions = ['' => '— Nu mapa —'] + $headerOptions;
                @endphp

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Cod bilet / Barcode <span class="text-danger-500">*</span>
                    </label>
                    <select wire:model="col_barcode" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($headerOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prenume</label>
                    <select wire:model="col_first_name" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($headerOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nume</label>
                    <select wire:model="col_last_name" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($headerOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <select wire:model="col_email" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($headerOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tip bilet</label>
                    <select wire:model="col_ticket_type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($headerOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ID extern</label>
                    <select wire:model="col_original_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($headerOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Preview table --}}
            @if(!empty($this->csvPreview))
            <div class="mt-4">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Preview (primele {{ count($this->csvPreview) }} rânduri):</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                @foreach($this->csvHeaders as $header)
                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->csvPreview as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                @foreach($this->csvHeaders as $header)
                                    <td class="px-2 py-1 text-gray-700 dark:text-gray-300">{{ $row[$header] ?? '' }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="mt-4">
                <x-filament::button wire:click="runImport" color="success" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="runImport">Importă {{ $this->csvTotalRows }} bilete</span>
                    <span wire:loading wire:target="runImport">Se importă...</span>
                </x-filament::button>
            </div>
        </x-filament::section>
        @endif

        {{-- Import result --}}
        @if($this->importResult)
        <x-filament::section>
            <div class="p-4 rounded-lg bg-success-50 dark:bg-success-950 text-success-700 dark:text-success-300 font-medium">
                {{ $this->importResult }}
            </div>
        </x-filament::section>
        @endif

        {{-- Existing external tickets table --}}
        @if($existingCount > 0)
        <x-filament::section>
            <x-slot name="heading">Bilete externe importate</x-slot>

            @php
                $tickets = \App\Models\ExternalTicket::where('event_id', $this->record->id)
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get();
            @endphp

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Barcode</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Nume</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Email</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Tip bilet</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Check-in</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tickets as $ticket)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="px-3 py-2 font-mono text-xs">{{ $ticket->barcode }}</td>
                            <td class="px-3 py-2">{{ $ticket->attendee_name }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $ticket->attendee_email ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $ticket->ticket_type_name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                @if($ticket->status === 'valid')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">Valid</span>
                                @elseif($ticket->status === 'used')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200">Folosit</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">{{ ucfirst($ticket->status) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                @if($ticket->checked_in_at)
                                    {{ $ticket->checked_in_at->format('d.m.Y H:i') }}
                                    <br><span class="text-gray-400">{{ $ticket->checked_in_by }}</span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($existingCount > 100)
                <p class="mt-2 text-xs text-gray-400">Se afișează primele 100 din {{ $existingCount }} bilete.</p>
            @endif
        </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
