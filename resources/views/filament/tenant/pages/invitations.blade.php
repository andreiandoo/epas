<x-filament-panels::page>
    {{-- Header with Workflow Guide --}}
    <div class="mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                    <x-heroicon-o-envelope class="w-6 h-6 text-white" />
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

            {{-- Workflow Steps --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Workflow</p>
                <div class="flex items-center gap-2 text-sm">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold">1</span>
                        Create Batch
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                        <span class="w-5 h-5 rounded-full bg-purple-600 text-white text-xs flex items-center justify-center font-bold">2</span>
                        Import Recipients
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300">
                        <span class="w-5 h-5 rounded-full bg-orange-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                        Generate PDFs
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                        <span class="w-5 h-5 rounded-full bg-green-600 text-white text-xs flex items-center justify-center font-bold">4</span>
                        Send Emails
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $batches = $this->getBatches();
    @endphp

    @if($batches->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/50 dark:to-purple-900/50 flex items-center justify-center">
                <x-heroicon-o-envelope-open class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No invitation batches yet</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Click "Create Batch" above to start sending invitations.</p>
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

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                        <x-heroicon-o-rectangle-stack class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Batches</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $totalBatches }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center">
                        <x-heroicon-o-ticket class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Invitations</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($totalInvites) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/50 flex items-center justify-center">
                        <x-heroicon-o-paper-airplane class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Emails Sent</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($totalEmailed) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                        <x-heroicon-o-check-badge class="w-5 h-5 text-amber-600 dark:text-amber-400" />
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
                @php
                    $recipientCount = $this->getRecipientCount($batch->id);
                    $canRender = $recipientCount > 0 && $batch->status === 'draft';
                    $needsRecipients = $recipientCount === 0 && $batch->status === 'draft';
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $batch->name }}
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($batch->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400
                                        @elseif($batch->status === 'ready') bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400
                                        @elseif($batch->status === 'sending') bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-400
                                        @elseif($batch->status === 'rendering') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-400
                                        @elseif($batch->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
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

                        {{-- Current Step Indicator --}}
                        @if($batch->status === 'draft')
                            @if($needsRecipients)
                                <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                    <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300">
                                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                        <span class="text-sm font-medium">Step 2: Import recipients before generating PDFs</span>
                                    </div>
                                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 ml-7">
                                        Upload a CSV file with recipient data (name, email, phone, company)
                                    </p>
                                </div>
                            @else
                                <div class="mb-4 p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                                    <div class="flex items-center gap-2 text-purple-800 dark:text-purple-300">
                                        <x-heroicon-o-check-circle class="w-5 h-5" />
                                        <span class="text-sm font-medium">{{ $recipientCount }} recipients imported - Ready to generate PDFs</span>
                                    </div>
                                </div>
                            @endif
                        @elseif($batch->status === 'ready')
                            <div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                <div class="flex items-center gap-2 text-green-800 dark:text-green-300">
                                    <x-heroicon-o-check-circle class="w-5 h-5" />
                                    <span class="text-sm font-medium">{{ $batch->qty_rendered }} PDFs generated - Ready to send emails</span>
                                </div>
                            </div>
                        @endif

                        {{-- Progress Stats --}}
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $batch->qty_planned }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Planned</p>
                            </div>
                            <div class="text-center p-3 rounded-lg {{ $recipientCount > 0 ? 'bg-purple-50 dark:bg-purple-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                                <p class="text-xl font-bold {{ $recipientCount > 0 ? 'text-purple-600 dark:text-purple-400' : 'text-gray-900 dark:text-white' }}">{{ $recipientCount }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Recipients</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $batch->qty_generated }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Generated</p>
                            </div>
                            <div class="text-center p-3 rounded-lg {{ $batch->qty_rendered > 0 ? 'bg-orange-50 dark:bg-orange-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                                <p class="text-xl font-bold {{ $batch->qty_rendered > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white' }}">{{ $batch->qty_rendered }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Rendered</p>
                            </div>
                            <div class="text-center p-3 rounded-lg {{ $batch->qty_emailed > 0 ? 'bg-green-50 dark:bg-green-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                                <p class="text-xl font-bold {{ $batch->qty_emailed > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">{{ $batch->qty_emailed }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Emailed</p>
                            </div>
                            <div class="text-center p-3 rounded-lg {{ $batch->qty_checked_in > 0 ? 'bg-amber-50 dark:bg-amber-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                                <p class="text-xl font-bold {{ $batch->qty_checked_in > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $batch->qty_checked_in }}</p>
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
                                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-2 rounded-full transition-all duration-300"
                                         style="width: {{ $batch->getEmailedPercentage() }}%"></div>
                                </div>
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            @if($batch->status === 'draft')
                                {{-- Import CSV - Always show for draft --}}
                                <button wire:click="openImportModal('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition-colors shadow-sm">
                                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                    Import Recipients (CSV)
                                </button>

                                {{-- Generate PDFs - Only if has recipients --}}
                                @if($canRender)
                                    <button wire:click="renderBatch('{{ $batch->id }}')"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-orange-600 text-white hover:bg-orange-700 transition-colors shadow-sm">
                                        <x-heroicon-o-document class="w-4 h-4" />
                                        Generate PDFs ({{ $recipientCount }})
                                    </button>
                                @else
                                    <button disabled
                                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed">
                                        <x-heroicon-o-document class="w-4 h-4" />
                                        Generate PDFs
                                    </button>
                                @endif
                            @endif

                            @if($batch->status === 'ready')
                                <button wire:click="sendEmails('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors shadow-sm">
                                    <x-heroicon-o-paper-airplane class="w-4 h-4" />
                                    Send Emails ({{ $batch->qty_rendered }})
                                </button>
                            @endif

                            <button wire:click="downloadExport('{{ $batch->id }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Export
                            </button>

                            @if(!in_array($batch->status, ['cancelled', 'completed']))
                                <button wire:click="cancelBatch('{{ $batch->id }}')"
                                        wire:confirm="Are you sure you want to cancel this batch? All invitations will be voided."
                                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors ml-auto">
                                    <x-heroicon-o-x-circle class="w-4 h-4" />
                                    Cancel
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showImportModal', false)">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-purple-600 to-indigo-600">
                    <h3 class="text-lg font-semibold text-white">Import Recipients</h3>
                    <p class="text-sm text-purple-100 mt-1">Upload a CSV file with recipient data</p>
                </div>

                <form wire:submit="processImport" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv"
                               class="block w-full text-sm text-gray-500 dark:text-gray-400
                                      file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                      file:text-sm file:font-medium file:bg-purple-50 dark:file:bg-purple-900/50
                                      file:text-purple-700 dark:file:text-purple-300 hover:file:bg-purple-100
                                      dark:hover:file:bg-purple-900/70 cursor-pointer" />
                        <p class="text-xs text-gray-500 mt-2">
                            <strong>Required columns:</strong> name, email<br>
                            <strong>Optional:</strong> phone, company, seat
                        </p>
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-3">Column Mapping (0-indexed)</p>
                        <div class="grid grid-cols-5 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Name</label>
                                <input type="number" wire:model="colName" min="0"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1.5" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Email</label>
                                <input type="number" wire:model="colEmail" min="0"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1.5" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Phone</label>
                                <input type="number" wire:model="colPhone" min="0"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1.5" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Company</label>
                                <input type="number" wire:model="colCompany" min="0"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1.5" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Seat</label>
                                <input type="number" wire:model="colSeat" min="0"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1.5" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="skipHeader" id="skipHeader"
                               class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500" checked />
                        <label for="skipHeader" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Skip header row</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" wire:click="$set('showImportModal', false)"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors shadow-sm">
                            Import Recipients
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
