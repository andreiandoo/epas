<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Add the following code to the tenant's website to deploy the Tixello platform:
    </p>

    <div class="relative">
        <pre class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm overflow-x-auto"><code>{{ $package->getInstallationCode() }}</code></pre>

        <button
            type="button"
            onclick="navigator.clipboard.writeText(this.getAttribute('data-code')).then(() => alert('Copied to clipboard!'))"
            data-code="{{ $package->getInstallationCode() }}"
            class="absolute top-2 right-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            title="Copy to clipboard"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
        </button>
    </div>

    <div class="text-sm space-y-2">
        <p><strong>Package Hash:</strong> <code class="text-xs">{{ $package->package_hash }}</code></p>
        <p><strong>Integrity Hash:</strong> <code class="text-xs">{{ $package->integrity_hash }}</code></p>
        <p><strong>Version:</strong> {{ $package->version }}</p>
        <p><strong>Generated:</strong> {{ $package->generated_at?->format('Y-m-d H:i:s') }}</p>
    </div>
</div>
