<div class="space-y-4">
    @if($tenant->domains->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No domains configured for this tenant.
        </div>
    @else
        @foreach($tenant->domains as $domain)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0">
                            @if($domain->is_primary)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                                    Primary
                                </span>
                            @endif
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $domain->domain }}
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($domain->is_active)
                                    <span class="text-green-600 dark:text-green-400">● Active</span>
                                @else
                                    <span class="text-yellow-600 dark:text-yellow-400">● Pending Verification</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                @php
                    $package = $domain->packages()->where('status', 'ready')->latest()->first();
                @endphp

                @if($package)
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mb-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Version</span>
                                <p class="font-mono font-medium text-gray-900 dark:text-white">{{ $package->version }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Size</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $package->getFileSizeFormatted() }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Generated</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $package->generated_at?->diffForHumans() ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Downloads</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $package->download_count }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                            Installation Code
                        </label>
                        <div class="relative">
                            <pre class="bg-gray-900 text-green-400 text-xs p-3 rounded-lg overflow-x-auto"><code>{{ $package->getInstallationCode() }}</code></pre>
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText(this.dataset.code); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);"
                                data-code="{{ $package->getInstallationCode() }}"
                                class="absolute top-2 right-2 px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded"
                            >
                                Copy
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('admin.tenant.package.download', ['tenant' => $tenant->id, 'domain' => $domain->id]) }}"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download Package
                        </a>

                        <button
                            type="button"
                            onclick="regeneratePackage({{ $tenant->id }}, {{ $domain->id }})"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Regenerate
                        </button>

                        <a
                            href="{{ route('admin.tenant.package.instructions', ['tenant' => $tenant->id, 'domain' => $domain->id]) }}"
                            target="_blank"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Instructions
                        </a>
                    </div>
                @else
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 mb-3">
                        <p class="text-xs text-yellow-800 dark:text-yellow-200">
                            No deployment package generated yet for this domain.
                        </p>
                    </div>

                    <button
                        type="button"
                        onclick="generatePackage({{ $tenant->id }}, {{ $domain->id }})"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Generate Package
                    </button>
                @endif

                @if($package)
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <strong>Enabled Modules:</strong>
                            {{ implode(', ', $package->enabled_modules ?? []) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <strong>Integrity Hash:</strong>
                            <code class="font-mono text-xs">{{ Str::limit($package->integrity_hash, 30) }}</code>
                        </p>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>

<script>
    function generatePackage(tenantId, domainId) {
        fetch(`/admin/tenants/${tenantId}/domains/${domainId}/package/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Package generated successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to generate package'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while generating the package');
        });
    }

    function regeneratePackage(tenantId, domainId) {
        if (!confirm('Are you sure you want to regenerate this package?')) {
            return;
        }

        fetch(`/admin/tenants/${tenantId}/domains/${domainId}/package/regenerate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Package regenerated successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to regenerate package'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while regenerating the package');
        });
    }
</script>
