<x-filament-panels::page>
    {{-- Header --}}
    <div class="mb-6">
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                    <x-heroicon-o-envelope class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Invitations Manager
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Create and manage invitation batches for VIP guests, press passes, and complimentary tickets.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @php
        $batches = $this->getBatches();
    @endphp

    @if($batches->isEmpty())
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                <x-heroicon-o-envelope-open class="w-8 h-8 text-indigo-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No invitation batches yet</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Create your first batch to start sending invitations.</p>
        </div>
    @else
        {{-- Statistics Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            @php
                $totalBatches = $batches->count();
                $totalInvites = $batches->sum('qty_generated');
                $totalEmailed = $batches->sum('qty_emailed');
                $totalCheckedIn = $batches->sum('qty_checked_in');
            @endphp

            <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-xl shadow-lg border border-white/20 dark:border-gray-700/50 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <x-heroicon-o-rectangle-stack class="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Batches</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $totalBatches }}</p>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-xl shadow-lg border border-white/20 dark:border-gray-700/50 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <x-heroicon-o-ticket class="w-5 h-5 text-purple-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Invitations</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($totalInvites) }}</p>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-xl shadow-lg border border-white/20 dark:border-gray-700/50 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                        <x-heroicon-o-paper-airplane class="w-5 h-5 text-green-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Emails Sent</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($totalEmailed) }}</p>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-xl shadow-lg border border-white/20 dark:border-gray-700/50 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <x-heroicon-o-check-badge class="w-5 h-5 text-amber-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Checked In</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($totalCheckedIn) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Batches List --}}
        <div class="space-y-4">
            @foreach($batches as $batch)
                <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $batch->name }}
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($this->getStatusColor($batch->status) === 'green') bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400
                                        @elseif($this->getStatusColor($batch->status) === 'blue') bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400
                                        @elseif($this->getStatusColor($batch->status) === 'yellow') bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-400
                                        @elseif($this->getStatusColor($batch->status) === 'red') bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400
                                        @elseif($this->getStatusColor($batch->status) === 'indigo') bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-400
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-500/20 dark:text-gray-400
                                        @endif
                                    ">
                                        {{ $this->getStatusLabel($batch->status) }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Created {{ $batch->created_at->diffForHumans() }}
                                    @if($batch->template)
                                        &bull; Template: {{ $batch->template->name }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Progress Stats --}}
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $batch->qty_planned }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Planned</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $batch->qty_generated }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Generated</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $batch->qty_rendered }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Rendered</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $batch->qty_emailed }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Emailed</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $batch->qty_checked_in }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Checked In</p>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        @if($batch->qty_planned > 0)
                            <div class="mb-4">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Progress</span>
                                    <span>{{ $batch->getEmailedPercentage() }}% emailed</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-2 rounded-full transition-all duration-300"
                                         style="width: {{ $batch->getEmailedPercentage() }}%"></div>
                                </div>
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200/50 dark:border-gray-700/50">
                            @if($batch->status === 'draft')
                                <button wire:click="openImportModal('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-colors">
                                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                    Import CSV
                                </button>
                                <button wire:click="renderBatch('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 hover:bg-purple-500/20 transition-colors">
                                    <x-heroicon-o-document class="w-4 h-4" />
                                    Render PDFs
                                </button>
                            @endif

                            @if($batch->status === 'ready')
                                <button wire:click="sendEmails('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-green-500/10 text-green-600 dark:text-green-400 hover:bg-green-500/20 transition-colors">
                                    <x-heroicon-o-paper-airplane class="w-4 h-4" />
                                    Send Emails
                                </button>
                            @endif

                            <button wire:click="downloadExport('{{ $batch->id }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-gray-500/10 text-gray-600 dark:text-gray-400 hover:bg-gray-500/20 transition-colors">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Export CSV
                            </button>

                            @if(!in_array($batch->status, ['cancelled', 'completed']))
                                <button wire:click="cancelBatch('{{ $batch->id }}')"
                                        wire:confirm="Are you sure you want to cancel this batch? All invitations will be voided."
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-colors ml-auto">
                                    <x-heroicon-o-x-circle class="w-4 h-4" />
                                    Cancel Batch
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Import Modal --}}
    @if($showImportModal && $selectedBatchId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showImportModal', false)">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Recipients</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Upload a CSV file with recipient data</p>
                </div>

                <form wire:submit="processImport" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 dark:file:bg-indigo-900/50 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100" />
                        <p class="text-xs text-gray-500 mt-1">Upload a CSV with columns: name, email, phone, company</p>
                    </div>

                    <div class="grid grid-cols-5 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Name Col</label>
                            <input type="number" wire:model="colName" min="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" value="0" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Email Col</label>
                            <input type="number" wire:model="colEmail" min="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" value="1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Phone Col</label>
                            <input type="number" wire:model="colPhone" min="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" value="2" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Company Col</label>
                            <input type="number" wire:model="colCompany" min="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" value="3" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Seat Col</label>
                            <input type="number" wire:model="colSeat" min="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" value="4" />
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="skipHeader" id="skipHeader" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600" checked />
                        <label for="skipHeader" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Skip header row</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" wire:click="$set('showImportModal', false)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
