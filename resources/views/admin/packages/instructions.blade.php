<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Instructions - {{ $domain->domain }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Installation Instructions</h1>
                <p class="text-gray-600 mt-2">Deploy the Tixello Event Platform on {{ $domain->domain }}</p>
            </div>

            @if($package)
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Package Information</h2>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Tenant</dt>
                                <dd class="font-medium text-gray-900">{{ $tenant->public_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Domain</dt>
                                <dd class="font-medium text-gray-900">{{ $domain->domain }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Version</dt>
                                <dd class="font-medium text-gray-900">{{ $package->version }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Generated</dt>
                                <dd class="font-medium text-gray-900">{{ $package->generated_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Installation Code</h2>
                    <p class="text-sm text-gray-600 mb-3">Add this code to your website's HTML, just before the closing <code class="bg-gray-100 px-1 rounded">&lt;/body&gt;</code> tag:</p>
                    <div class="relative">
                        <pre class="bg-gray-900 text-green-400 text-sm p-4 rounded-lg overflow-x-auto"><code>{{ $package->getInstallationCode() }}</code></pre>
                        <button
                            onclick="copyCode(this)"
                            data-code="{{ $package->getInstallationCode() }}"
                            class="absolute top-2 right-2 px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Server Requirements</h2>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-2">
                        <li>HTTPS enabled (required for security)</li>
                        <li>Ability to serve static files</li>
                        <li>Rewrite rules for SPA routing</li>
                    </ul>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Apache Configuration</h2>
                    <p class="text-sm text-gray-600 mb-3">Add this to your <code class="bg-gray-100 px-1 rounded">.htaccess</code> file:</p>
                    <pre class="bg-gray-900 text-green-400 text-sm p-4 rounded-lg overflow-x-auto"><code>RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /index.html [L]</code></pre>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Nginx Configuration</h2>
                    <p class="text-sm text-gray-600 mb-3">Add this to your server block:</p>
                    <pre class="bg-gray-900 text-green-400 text-sm p-4 rounded-lg overflow-x-auto"><code>location / {
    try_files $uri $uri/ /index.html;
}</code></pre>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-yellow-800 mb-2">Security Note</h3>
                    <p class="text-sm text-yellow-700">
                        This package is domain-locked to <strong>{{ $domain->domain }}</strong> and will not function on other domains.
                        The code is obfuscated and protected. Do not attempt to modify the JavaScript files.
                    </p>
                </div>
            @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">
                        No deployment package has been generated for this domain yet.
                        Please generate a package first.
                    </p>
                </div>
            @endif

            <div class="mt-8 pt-8 border-t border-gray-200">
                <a href="{{ url()->previous() }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    &larr; Back
                </a>
            </div>
        </div>
    </div>

    <script>
        function copyCode(button) {
            const code = button.dataset.code;
            navigator.clipboard.writeText(code).then(() => {
                button.textContent = 'Copied!';
                setTimeout(() => {
                    button.textContent = 'Copy';
                }, 2000);
            });
        }
    </script>
</body>
</html>
