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
                <button wire:click="$set('showCreateModal', true)"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg shadow-sm transition-colors"
                        style="background-color: #4f46e5; color: white;">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Create Batch
                </button>
            </div>

            {{-- Workflow Steps --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Workflow</p>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold">1</span>
                        Create Batch
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                        <span class="w-5 h-5 rounded-full bg-purple-600 text-white text-xs flex items-center justify-center font-bold">2</span>
                        <span>Add Recipients</span>
                        <span class="text-xs text-purple-500 dark:text-purple-400">(Manual / CSV)</span>
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300">
                        <span class="w-5 h-5 rounded-full bg-orange-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                        Generate PDFs
                    </div>
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                        <span class="w-5 h-5 rounded-full bg-green-600 text-white text-xs flex items-center justify-center font-bold">4</span>
                        <span>Download / Send</span>
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
                                        <span class="text-sm font-medium">Step 2: Add recipients before generating PDFs</span>
                                    </div>
                                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 ml-7">
                                        Click "Add Recipient" to enter details manually, or "Import CSV" to bulk import
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
                            <div class="text-center p-3 rounded-lg" style="background-color: #f9fafb;">
                                <p class="text-xl font-bold" style="color: #111827;">{{ $batch->qty_planned }}</p>
                                <p class="text-xs" style="color: #6b7280;">Planned</p>
                            </div>
                            <div class="text-center p-3 rounded-lg" style="background-color: {{ $recipientCount > 0 ? '#faf5ff' : '#f9fafb' }};">
                                <p class="text-xl font-bold" style="color: {{ $recipientCount > 0 ? '#9333ea' : '#111827' }};">{{ $recipientCount }}</p>
                                <p class="text-xs" style="color: #6b7280;">Recipients</p>
                            </div>
                            <div class="text-center p-3 rounded-lg" style="background-color: #f9fafb;">
                                <p class="text-xl font-bold" style="color: #111827;">{{ $batch->qty_generated }}</p>
                                <p class="text-xs" style="color: #6b7280;">Generated</p>
                            </div>
                            <div class="text-center p-3 rounded-lg" style="background-color: {{ $batch->qty_rendered > 0 ? '#fff7ed' : '#f9fafb' }};">
                                <p class="text-xl font-bold" style="color: {{ $batch->qty_rendered > 0 ? '#ea580c' : '#111827' }};">{{ $batch->qty_rendered }}</p>
                                <p class="text-xs" style="color: #6b7280;">Rendered</p>
                            </div>
                            <div class="text-center p-3 rounded-lg" style="background-color: {{ $batch->qty_emailed > 0 ? '#f0fdf4' : '#f9fafb' }};">
                                <p class="text-xl font-bold" style="color: {{ $batch->qty_emailed > 0 ? '#16a34a' : '#111827' }};">{{ $batch->qty_emailed }}</p>
                                <p class="text-xs" style="color: #6b7280;">Emailed</p>
                            </div>
                            <div class="text-center p-3 rounded-lg" style="background-color: {{ $batch->qty_checked_in > 0 ? '#fffbeb' : '#f9fafb' }};">
                                <p class="text-xl font-bold" style="color: {{ $batch->qty_checked_in > 0 ? '#d97706' : '#111827' }};">{{ $batch->qty_checked_in }}</p>
                                <p class="text-xs" style="color: #6b7280;">Checked In</p>
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
                                {{-- Add Manual Recipient --}}
                                <button wire:click="openManualModal('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg shadow-sm"
                                        style="background-color: #2563eb; color: white;">
                                    <x-heroicon-o-user-plus class="w-4 h-4" />
                                    Add Recipient
                                </button>

                                {{-- Import CSV --}}
                                <button wire:click="openImportModal('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg shadow-sm"
                                        style="background-color: #9333ea; color: white;">
                                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                    Import CSV
                                </button>

                                {{-- Generate PDFs - Only if has recipients --}}
                                @if($canRender)
                                    <button wire:click="renderBatch('{{ $batch->id }}')"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg shadow-sm"
                                            style="background-color: #ea580c; color: white;">
                                        <x-heroicon-o-document class="w-4 h-4" />
                                        Generate PDFs ({{ $recipientCount }})
                                    </button>
                                @else
                                    <button disabled
                                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg cursor-not-allowed"
                                            style="background-color: #e5e7eb; color: #9ca3af;">
                                        <x-heroicon-o-document class="w-4 h-4" />
                                        Generate PDFs
                                    </button>
                                @endif
                            @endif

                            @if($batch->status === 'ready' || $batch->status === 'rendering' || $batch->qty_rendered > 0)
                                {{-- Download PDFs --}}
                                <button wire:click="downloadPdfs('{{ $batch->id }}')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg shadow-sm"
                                        style="background-color: #0891b2; color: white;">
                                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                    Download PDFs ({{ $batch->qty_rendered }})
                                </button>

                                {{-- Regenerate PDFs --}}
                                <button wire:click="regeneratePdfs('{{ $batch->id }}')"
                                        wire:confirm="This will regenerate all PDFs. Continue?"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg shadow-sm"
                                        style="background-color: #f59e0b; color: white;">
                                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    Regenerate PDFs
                                </button>

                                @if($batch->status === 'ready')
                                    <button wire:click="sendEmails('{{ $batch->id }}')"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg shadow-sm"
                                            style="background-color: #16a34a; color: white;">
                                        <x-heroicon-o-paper-airplane class="w-4 h-4" />
                                        Send Emails ({{ $batch->qty_rendered }})
                                    </button>
                                @endif
                            @endif

                            <button wire:click="downloadExport('{{ $batch->id }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg"
                                    style="background-color: #f3f4f6; color: #374151;">
                                <x-heroicon-o-table-cells class="w-4 h-4" />
                                Export CSV
                            </button>

                            {{-- Spacer --}}
                            <div class="flex-1"></div>

                            {{-- Cancel Batch (voids invitations) --}}
                            @if(!in_array($batch->status, ['cancelled', 'completed']))
                                <button wire:click="cancelBatch('{{ $batch->id }}')"
                                        wire:confirm="Are you sure you want to cancel this batch? All invitations will be voided."
                                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/20 transition-colors">
                                    <x-heroicon-o-x-circle class="w-4 h-4" />
                                    Cancel
                                </button>
                            @endif

                            {{-- Delete Batch (permanent deletion) --}}
                            <button wire:click="deleteBatch('{{ $batch->id }}')"
                                    wire:confirm="Are you sure you want to permanently delete this batch? This will remove all invitations and cannot be undone."
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors">
                                <x-heroicon-o-trash class="w-4 h-4" />
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Import Modal --}}
    @if($showImportModal && $selectedBatchId)
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0,0,0,0.5);" wire:click.self="$set('showImportModal', false)">
            <div class="rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden" style="background-color: white; border: 1px solid #e5e7eb;">
                <div class="px-6 py-4" style="background: linear-gradient(to right, #9333ea, #4f46e5); border-bottom: 1px solid #e5e7eb;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: white;">Import Recipients</h3>
                    <p style="font-size: 0.875rem; color: #e9d5ff; margin-top: 0.25rem;">Upload a CSV file with recipient data</p>
                </div>

                <form wire:submit="processImport" class="p-6 space-y-4">
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv"
                               style="width: 100%; font-size: 0.875rem; color: #6b7280;" />
                        <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem;">
                            <strong>Required columns:</strong> name, email<br>
                            <strong>Optional:</strong> phone, company, seat
                        </p>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: #f9fafb;">
                        <p style="font-size: 0.75rem; font-weight: 500; color: #4b5563; margin-bottom: 0.75rem;">Column Mapping (0-indexed)</p>
                        <div class="grid grid-cols-5 gap-2">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Name</label>
                                <input type="number" wire:model="colName" min="0"
                                       style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Email</label>
                                <input type="number" wire:model="colEmail" min="0"
                                       style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Phone</label>
                                <input type="number" wire:model="colPhone" min="0"
                                       style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Company</label>
                                <input type="number" wire:model="colCompany" min="0"
                                       style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Seat</label>
                                <input type="number" wire:model="colSeat" min="0"
                                       style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="skipHeader" id="skipHeader"
                               style="border-radius: 0.25rem; border: 1px solid #d1d5db;" checked />
                        <label for="skipHeader" style="margin-left: 0.5rem; font-size: 0.875rem; color: #4b5563;">Skip header row</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4" style="border-top: 1px solid #e5e7eb;">
                        <button type="button" wire:click="$set('showImportModal', false)"
                                style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #374151; background-color: #f3f4f6; border-radius: 0.5rem; border: none; cursor: pointer;">
                            Cancel
                        </button>
                        <button type="submit"
                                style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; background-color: #9333ea; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            Import Recipients
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Manual Entry Modal --}}
    @if($showManualModal && $manualBatchId)
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0,0,0,0.5);" wire:click.self="closeManualModal">
            <div class="rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" style="background-color: white; border: 1px solid #e5e7eb;">
                <div class="px-6 py-4" style="background: linear-gradient(to right, #2563eb, #0891b2); border-bottom: 1px solid #e5e7eb;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: white;">Add Recipient</h3>
                    <p style="font-size: 0.875rem; color: #bfdbfe; margin-top: 0.25rem;">Enter recipient details manually</p>
                </div>

                <form wire:submit="addManualRecipient" class="p-6 space-y-4">
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                            Name <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" wire:model="manualName" required
                               placeholder="John Doe"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                            Email <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="email" wire:model="manualEmail" required
                               placeholder="john@example.com"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Phone</label>
                            <input type="text" wire:model="manualPhone"
                                   placeholder="+40 700 000 000"
                                   style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Company</label>
                            <input type="text" wire:model="manualCompany"
                                   placeholder="ACME Inc."
                                   style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Seat Reference</label>
                        <input type="text" wire:model="manualSeat"
                               placeholder="A-12"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                        <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Optional: Assigned seat or table reference</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4" style="border-top: 1px solid #e5e7eb;">
                        <button type="button" wire:click="closeManualModal"
                                style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #374151; background-color: #f3f4f6; border-radius: 0.5rem; border: none; cursor: pointer;">
                            Done
                        </button>
                        <button type="submit"
                                style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; background-color: #2563eb; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            Add & Continue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Create Batch Modal --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center" style="background-color: rgba(0,0,0,0.5);" wire:click.self="$set('showCreateModal', false)">
            <div class="rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden" style="background-color: white; border: 1px solid #e5e7eb;">
                <div class="px-6 py-4" style="background: linear-gradient(to right, #4f46e5, #9333ea); border-bottom: 1px solid #e5e7eb;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: white;">Create Invitation Batch</h3>
                    <p style="font-size: 0.875rem; color: #e0e7ff; margin-top: 0.25rem;">Create a new batch for your event invitations</p>
                </div>

                <form wire:submit="submitCreateBatch" class="p-6 space-y-4">
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                            Event <span style="color: #ef4444;">*</span>
                        </label>
                        <select wire:model="batchData.event_ref" required
                                style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;">
                            <option value="">Select an event...</option>
                            @foreach($this->getEvents() as $id => $title)
                                <option value="{{ $id }}" @if($preselectedEventId == $id) selected @endif>{{ $title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                            Batch Name <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" wire:model="batchData.name" required
                               placeholder="e.g., VIP Guests - Opening Night"
                               style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                Planned Quantity <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="number" wire:model="batchData.qty_planned" required min="1" max="10000"
                                   placeholder="50"
                                   style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Ticket Template</label>
                            <select wire:model="batchData.template_id"
                                    style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;">
                                <option value="">None</option>
                                @foreach($this->getTemplates() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Watermark Text</label>
                            <input type="text" wire:model="batchData.watermark"
                                   placeholder="e.g., VIP INVITATION"
                                   style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;" />
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Seat Assignment</label>
                            <select wire:model="batchData.seat_mode"
                                    style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;">
                                <option value="none">No seat assignment</option>
                                <option value="manual">Manual assignment</option>
                                <option value="auto">Auto-assign</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Notes</label>
                        <textarea wire:model="batchData.notes" rows="2"
                                  placeholder="Internal notes about this batch"
                                  style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background-color: white;"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4" style="border-top: 1px solid #e5e7eb;">
                        <button type="button" wire:click="$set('showCreateModal', false)"
                                style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: #374151; background-color: #f3f4f6; border-radius: 0.5rem; border: none; cursor: pointer;">
                            Cancel
                        </button>
                        <button type="submit"
                                style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; background-color: #4f46e5; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            Create Batch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
