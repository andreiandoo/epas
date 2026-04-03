<div x-data="{
    loading: false,
    selectedDataset: '{{ $demoDataset ?? 'festival' }}',
    hasDemoData: {{ $hasDemoData ? 'true' : 'false' }},
    errorMessage: '',
    successMessage: '',
}" class="space-y-4">

    {{-- Status: No demo data --}}
    @if(!$hasDemoData)
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                <x-heroicon-o-beaker class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">No demo data</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Select a dataset and populate to start testing.</p>
            </div>
        </div>

        <div class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Dataset type</label>
                <select x-model="selectedDataset"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    @foreach($availableDatasets as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button
                x-on:click="
                    if (!confirm('This will create a shadow tenant with demo data. Continue?')) return;
                    loading = true;
                    errorMessage = '';
                    successMessage = '';
                    fetch('/admin/api/tenants/{{ $tenant->id }}/demo', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ dataset: selectedDataset })
                    })
                    .then(r => r.json())
                    .then(data => {
                        loading = false;
                        if (data.success) {
                            successMessage = data.message;
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            errorMessage = data.message || 'An error occurred';
                        }
                    })
                    .catch(e => {
                        loading = false;
                        errorMessage = 'Network error: ' + e.message;
                    })
                "
                x-bind:disabled="loading"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <template x-if="loading">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <x-heroicon-o-play class="w-4 h-4" x-show="!loading" />
                <span x-text="loading ? 'Populating...' : 'Populate Demo Data'"></span>
            </button>
        </div>
    </div>
    @endif

    {{-- Status: Has demo data --}}
    @if($hasDemoData && $demoShadow)
    <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Demo data active</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Dataset: <span class="font-medium">{{ $availableDatasets[$demoDataset] ?? $demoDataset }}</span>
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200">
                Active
            </span>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 mb-3">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Shadow Tenant</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">#{{ $demoShadow->id }} - {{ $demoShadow->public_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $demoShadow->created_at->format('d.m.Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex items-center gap-2">
            <a href="/admin/tenants/{{ $demoShadow->id }}/edit"
                class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition text-gray-700 dark:text-gray-200">
                <x-heroicon-o-eye class="w-4 h-4" />
                View Shadow Tenant
            </a>
            <button
                x-on:click="
                    if (!confirm('This will DELETE the shadow tenant and ALL demo data. This cannot be undone. Continue?')) return;
                    loading = true;
                    errorMessage = '';
                    successMessage = '';
                    fetch('/admin/api/tenants/{{ $tenant->id }}/demo', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        loading = false;
                        if (data.success) {
                            successMessage = data.message;
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            errorMessage = data.message || 'An error occurred';
                        }
                    })
                    .catch(e => {
                        loading = false;
                        errorMessage = 'Network error: ' + e.message;
                    })
                "
                x-bind:disabled="loading"
                class="inline-flex items-center gap-2 px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <template x-if="loading">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <x-heroicon-o-trash class="w-4 h-4" x-show="!loading" />
                <span x-text="loading ? 'Removing...' : 'Remove Demo Data'"></span>
            </button>
        </div>
    </div>
    @endif

    {{-- Messages --}}
    <template x-if="errorMessage">
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-sm text-red-700 dark:text-red-300" x-text="errorMessage"></div>
    </template>
    <template x-if="successMessage">
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3 text-sm text-green-700 dark:text-green-300" x-text="successMessage"></div>
    </template>
</div>
