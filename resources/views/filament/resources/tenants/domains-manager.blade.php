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
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700">
                        Domain
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700">
                        Verified
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700">
                        Primary
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700">
                        Active
                    </th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-700">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @foreach($domains as $domain)
                    <tr>
                        <td class="whitespace-nowrap px-6 py-4">
                            <a href="https://{{ $domain->domain }}" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                {{ $domain->domain }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if($domain->is_active)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Script Installed
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                    Not Installed
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if($domain->isVerified())
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Verified
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                            @if($domain->is_primary)
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                    Primary
                                </span>
                            @else
                                <span class="text-xs text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <button
                                type="button"
                                onclick="toggleDomainActive({{ $domain->id }}, {{ $domain->is_active ? 'false' : 'true' }})"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $domain->is_active ? 'bg-blue-600' : 'bg-gray-200' }}"
                                role="switch"
                                aria-checked="{{ $domain->is_active ? 'true' : 'false' }}"
                            >
                                <span class="sr-only">Toggle active status</span>
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $domain->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-2">
                            @if(!$domain->isVerified())
                                <button
                                    type="button"
                                    onclick="confirmDomain({{ $domain->id }})"
                                    class="inline-flex items-center rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                >
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Confirm
                                </button>
                            @endif
                            @if($domain->is_active)
                                <button
                                    type="button"
                                    onclick="suspendDomain({{ $domain->id }})"
                                    class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                >
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    Suspend
                                </button>
                            @endif
                            <a
                                href="{{ route('tenant.login-as-admin', ['tenantId' => $tenantId, 'domain' => $domain->domain]) }}"
                                target="_blank"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                                Login as Admin
                            </a>
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
            if (confirm('Are you sure you want to ' + (newStatus === 'true' ? 'activate' : 'suspend') + ' this domain?')) {
                fetch(`/admin/domains/${domainId}/toggle-active`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ is_active: newStatus === 'true' })
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
        }

        function confirmDomain(domainId) {
            if (confirm('Are you sure you want to manually confirm this domain? This will allow the tenant to generate deployment packages.')) {
                fetch(`/admin/domains/${domainId}/confirm`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to confirm domain'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while confirming the domain');
                });
            }
        }

        function suspendDomain(domainId) {
            if (confirm('Are you sure you want to suspend this domain? The tenant will lose access to this website.')) {
                fetch(`/admin/domains/${domainId}/toggle-active`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ is_active: false })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to suspend domain'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while suspending the domain');
                });
            }
        }
    </script>
@endif
