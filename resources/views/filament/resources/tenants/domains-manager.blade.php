<!-- Add New Domain Form -->
<div class="mb-4 rounded-lg border border-gray-300 bg-white p-4">
    <h3 class="mb-3 text-sm font-semibold text-gray-700">Add New Domain</h3>
    <form id="add-domain-form" class="flex items-end gap-3">
        <div class="flex-1">
            <label for="new-domain" class="block text-xs font-medium text-gray-700 mb-1">Domain Name</label>
            <input
                type="text"
                id="new-domain"
                name="domain"
                placeholder="example.com"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                required
            />
        </div>
        <div>
            <label class="flex items-center text-xs text-gray-700">
                <input type="checkbox" id="is-primary" name="is_primary" class="mr-2 rounded border-gray-300">
                Set as Primary
            </label>
        </div>
        <button
            type="submit"
            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Domain
        </button>
    </form>
</div>

@if($domains->isEmpty())
    <div class="rounded-lg border border-gray-300 bg-gray-50 p-6 text-center">
        <p class="text-sm text-gray-600">No domains configured yet. Add your first domain above.</p>
    </div>
@else
    <div class="overflow-hidden rounded-lg border border-gray-300">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700">
                        Domain
                    </th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-700">
                        Active
                    </th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-700">
                        Confirmed
                    </th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-700">
                        Suspended
                    </th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-700">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @foreach($domains as $domain)
                    <tr>
                        <!-- Domain Name with Link -->
                        <td class="whitespace-nowrap px-4 py-4">
                            <div class="flex items-center">
                                <a href="https://{{ $domain->domain }}" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ $domain->domain }}
                                </a>
                                @if($domain->is_primary)
                                    <span class="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                        Primary
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Active Toggle -->
                        <td class="whitespace-nowrap px-4 py-4 text-center">
                            <button
                                type="button"
                                onclick="toggleDomainActive({{ $domain->id }}, {{ $domain->is_active ? 'false' : 'true' }})"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $domain->is_active ? 'bg-green-500' : 'bg-gray-200' }}"
                                role="switch"
                                aria-checked="{{ $domain->is_active ? 'true' : 'false' }}"
                                title="{{ $domain->is_active ? 'Click to deactivate' : 'Click to activate' }}"
                            >
                                <span class="sr-only">Toggle active status</span>
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $domain->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </td>

                        <!-- Confirmed Toggle -->
                        <td class="whitespace-nowrap px-4 py-4 text-center">
                            <button
                                type="button"
                                onclick="toggleDomainConfirmed({{ $domain->id }}, {{ $domain->isVerified() ? 'false' : 'true' }})"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $domain->isVerified() ? 'bg-green-500' : 'bg-gray-200' }}"
                                role="switch"
                                aria-checked="{{ $domain->isVerified() ? 'true' : 'false' }}"
                                title="{{ $domain->isVerified() ? 'Domain verified' : 'Click to confirm manually' }}"
                            >
                                <span class="sr-only">Toggle confirmed status</span>
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $domain->isVerified() ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </td>

                        <!-- Suspended Toggle -->
                        <td class="whitespace-nowrap px-4 py-4 text-center">
                            <button
                                type="button"
                                onclick="toggleDomainSuspended({{ $domain->id }}, {{ $domain->is_suspended ? 'false' : 'true' }})"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 {{ $domain->is_suspended ? 'bg-red-500' : 'bg-gray-200' }}"
                                role="switch"
                                aria-checked="{{ $domain->is_suspended ? 'true' : 'false' }}"
                                title="{{ $domain->is_suspended ? 'Click to unsuspend' : 'Click to suspend' }}"
                            >
                                <span class="sr-only">Toggle suspended status</span>
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $domain->is_suspended ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </td>

                        <!-- Actions -->
                        <td class="whitespace-nowrap px-4 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onclick="showVerificationCode({{ $domain->id }})"
                                    class="inline-flex items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                                    title="Show verification code"
                                >
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                    </svg>
                                    Code
                                </button>
                                <a
                                    href="{{ route('tenant.login-as-admin', ['tenantId' => $tenantId, 'domain' => $domain->domain]) }}"
                                    target="_blank"
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                    </svg>
                                    Login
                                </a>
                            </div>
                        </td>
                    </tr>
                    <!-- Verification Code Row (hidden by default) -->
                    <tr id="verification-row-{{ $domain->id }}" class="hidden bg-gray-50">
                        <td colspan="5" class="px-4 py-4">
                            @php
                                $verification = $domain->verifications()->latest()->first();
                                // Create verification entry for old domains that don't have one
                                if (!$verification) {
                                    $verification = $domain->verifications()->create([
                                        'tenant_id' => $domain->tenant_id,
                                        'verification_method' => 'dns_txt',
                                        'status' => 'pending',
                                    ]);
                                }
                            @endphp
                            @if($verification)
                                <div class="space-y-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Verification Code for {{ $domain->domain }}</h4>
                                    <p class="text-xs text-gray-600">Choose one of the following methods to verify domain ownership:</p>

                                    <!-- DNS TXT Record -->
                                    <div class="rounded border border-gray-200 bg-white p-3">
                                        <h5 class="text-xs font-medium text-gray-700 mb-2">Method 1: DNS TXT Record</h5>
                                        <p class="text-xs text-gray-500 mb-2">Add a TXT record to your DNS:</p>
                                        <div class="bg-gray-100 rounded p-2 font-mono text-xs">
                                            <div><strong>Name:</strong> {{ $verification->getDnsRecordName() }}</div>
                                            <div><strong>Value:</strong> {{ $verification->getDnsRecordValue() }}</div>
                                        </div>
                                    </div>

                                    <!-- Meta Tag -->
                                    <div class="rounded border border-gray-200 bg-white p-3">
                                        <h5 class="text-xs font-medium text-gray-700 mb-2">Method 2: Meta Tag</h5>
                                        <p class="text-xs text-gray-500 mb-2">Add this meta tag to your homepage &lt;head&gt;:</p>
                                        <div class="bg-gray-100 rounded p-2 font-mono text-xs overflow-x-auto">
                                            {{ $verification->getMetaTagHtml() }}
                                        </div>
                                    </div>

                                    <!-- File Upload -->
                                    <div class="rounded border border-gray-200 bg-white p-3">
                                        <h5 class="text-xs font-medium text-gray-700 mb-2">Method 3: File Upload</h5>
                                        <p class="text-xs text-gray-500 mb-2">Create a file at <code class="bg-gray-100 px-1 rounded">{{ $verification->getFileUploadPath() }}</code> with content:</p>
                                        <div class="bg-gray-100 rounded p-2 font-mono text-xs">
                                            {{ $verification->getFileUploadContent() }}
                                        </div>
                                    </div>

                                    <p class="text-xs text-gray-500">
                                        <strong>Status:</strong>
                                        @if($verification->isVerified())
                                            <span class="text-green-600">Verified</span>
                                        @elseif($verification->isExpired())
                                            <span class="text-red-600">Expired</span>
                                        @else
                                            <span class="text-yellow-600">Pending</span>
                                            (expires {{ $verification->expires_at->diffForHumans() }})
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script>
        // Add Domain Form Handler
        document.getElementById('add-domain-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const domainInput = document.getElementById('new-domain');
            const isPrimaryCheckbox = document.getElementById('is-primary');
            const domain = domainInput.value.trim();

            if (!domain) {
                alert('Please enter a domain name');
                return;
            }

            fetch(`/admin/tenants/{{ $tenantId }}/domains`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    domain: domain,
                    is_primary: isPrimaryCheckbox.checked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to add domain'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the domain');
            });
        });

        function toggleDomainActive(domainId, newStatus) {
            fetch(`/admin/domains/${domainId}/toggle-active`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ is_active: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update domain status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the domain status');
            });
        }

        function toggleDomainConfirmed(domainId, newStatus) {
            fetch(`/admin/domains/${domainId}/toggle-confirmed`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ is_verified: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update confirmation status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the confirmation status');
            });
        }

        function toggleDomainSuspended(domainId, newStatus) {
            fetch(`/admin/domains/${domainId}/toggle-suspended`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ is_suspended: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update suspended status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the suspended status');
            });
        }

        function showVerificationCode(domainId) {
            const row = document.getElementById(`verification-row-${domainId}`);
            if (row) {
                row.classList.toggle('hidden');
            }
        }
    </script>
@endif
