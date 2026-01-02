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
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $batch->name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Created {{ $batch->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                            {{ ucfirst($batch->status) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
