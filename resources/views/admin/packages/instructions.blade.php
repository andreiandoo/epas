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
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Step 1: Download Package</h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Download the complete deployment package. This ZIP file contains everything you need - just upload and go!
                    </p>
                    <a
                        href="{{ route('admin.tenant.package.download-zip', ['tenant' => $tenant->id, 'domain' => $domain->id]) }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download ZIP Package
                    </a>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Step 2: Upload to Server</h2>
                    <ol class="list-decimal list-inside text-sm text-gray-600 space-y-3">
                        <li>Connect to your server via FTP or file manager</li>
                        <li>Navigate to the <strong>root directory</strong> of your domain (usually <code class="bg-gray-100 px-1 rounded">public_html</code> or <code class="bg-gray-100 px-1 rounded">www</code>)</li>
                        <li>Upload and extract the ZIP file contents directly into the root directory</li>
                        <li>Make sure <code class="bg-gray-100 px-1 rounded">index.html</code> is in the root directory, not in a subfolder</li>
                    </ol>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Step 3: Verify Installation</h2>
                    <p class="text-sm text-gray-600 mb-3">
                        After uploading, visit your domain to verify the installation:
                    </p>
                    <a
                        href="https://{{ $domain->domain }}"
                        target="_blank"
                        class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium"
                    >
                        https://{{ $domain->domain }}
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Package Contents</h2>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <strong>index.html</strong> - Main entry point
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <strong>tixello-loader.min.js</strong> - Application code
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <strong>.htaccess</strong> - Apache server configuration
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <strong>README.md</strong> - Documentation
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Server Requirements</h2>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-2">
                        <li><strong>HTTPS enabled</strong> - Required for security (most hosts provide free SSL)</li>
                        <li><strong>Apache server</strong> - The included .htaccess file handles URL routing</li>
                        <li><strong>mod_rewrite enabled</strong> - Required for single-page app routing</li>
                    </ul>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                    <h3 class="text-sm font-semibold text-blue-800 mb-2">Need Help?</h3>
                    <p class="text-sm text-blue-700">
                        If you encounter any issues during installation, contact your account manager or email
                        <a href="mailto:support@tixello.com" class="underline">support@tixello.com</a>
                    </p>
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
</body>
</html>
