<x-filament-panels::page>
    <div class="invoice-edit-layout">
        <div class="invoice-edit-form">
            <form wire:submit="create">
                {{ $this->form }}

                <div class="fi-form-actions">
                    {{ $this->getCreateFormAction() }}
                    {{ $this->getCancelFormAction() }}
                </div>
            </form>
        </div>

        <div class="invoice-edit-preview">
            <x-invoice-preview :invoice="null" />
        </div>
    </div>

    <style>
        .invoice-edit-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1400px) {
            .invoice-edit-layout {
                grid-template-columns: 1fr;
            }

            .invoice-edit-preview {
                margin-top: 2rem;
            }
        }

        .invoice-edit-form {
            min-width: 0;
        }

        .invoice-edit-preview {
            min-width: 0;
        }

        .fi-form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
    </style>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Live preview update functionality with Livewire integration
            let updateTimeout;

            // Listen to Livewire events for form changes
            window.addEventListener('livewire:initialized', function() {
                Livewire.hook('commit', ({ component, commit, respond }) => {
                    // Debounce updates
                    clearTimeout(updateTimeout);
                    updateTimeout = setTimeout(() => {
                        updatePreview();
                    }, 300);
                });
            });

            // Also listen to regular input events
            const formInputs = document.querySelectorAll('input, select, textarea');
            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    clearTimeout(updateTimeout);
                    updateTimeout = setTimeout(() => {
                        updatePreview();
                    }, 300);
                });
                input.addEventListener('input', function() {
                    clearTimeout(updateTimeout);
                    updateTimeout = setTimeout(() => {
                        updatePreview();
                    }, 500);
                });
            });

            function updatePreview() {
                // Get form values
                const tenantId = document.querySelector('[name="data.tenant_id"]')?.value;
                const number = document.querySelector('[name="data.number"]')?.value;
                const description = document.querySelector('[name="data.description"]')?.value;
                const issueDate = document.querySelector('[name="data.issue_date"]')?.value;
                const dueDate = document.querySelector('[name="data.due_date"]')?.value;
                const periodStart = document.querySelector('[name="data.period_start"]')?.value;
                const periodEnd = document.querySelector('[name="data.period_end"]')?.value;
                const subtotal = document.querySelector('[name="data.subtotal"]')?.value;
                const vatRate = document.querySelector('[name="data.vat_rate"]')?.value;
                const vatAmount = document.querySelector('[name="data.vat_amount"]')?.value;
                const amount = document.querySelector('[name="data.amount"]')?.value;
                const currency = document.querySelector('[name="data.currency"]')?.value;

                // Update invoice number
                if (number) {
                    const numberEl = document.querySelector('[data-preview="number"]');
                    if (numberEl) {
                        numberEl.innerHTML = '<strong>Invoice #:</strong> ' + number;
                    }
                }

                // Update description
                if (description) {
                    const descEl = document.querySelector('[data-preview="description"]');
                    if (descEl) {
                        descEl.innerHTML = '<strong>' + description + '</strong>';
                    }
                }

                // Update issue date
                if (issueDate) {
                    const issueDateEl = document.querySelector('[data-preview="issue_date"]');
                    if (issueDateEl) {
                        const date = new Date(issueDate);
                        issueDateEl.innerHTML = '<strong>Issue Date:</strong> ' + date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                }

                // Update due date
                if (dueDate) {
                    const dueDateEl = document.querySelector('[data-preview="due_date"]');
                    if (dueDateEl) {
                        const date = new Date(dueDate);
                        dueDateEl.innerHTML = '<strong>Due Date:</strong> ' + date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                }

                // Update subtotal
                if (subtotal && currency) {
                    const subtotalEl = document.querySelector('[data-preview="subtotal"]');
                    if (subtotalEl) {
                        subtotalEl.innerHTML = '<strong>' + parseFloat(subtotal).toFixed(2) + ' ' + currency + '</strong>';
                    }
                }

                // Update VAT amount
                if (vatAmount && currency) {
                    const vatAmountEl = document.querySelector('[data-preview="vat_amount"]');
                    if (vatAmountEl) {
                        vatAmountEl.innerHTML = '<strong>' + parseFloat(vatAmount).toFixed(2) + ' ' + currency + '</strong>';
                    }
                }

                // Update total
                if (amount && currency) {
                    const totalEl = document.querySelector('[data-preview="total"]');
                    if (totalEl) {
                        totalEl.innerHTML = '<strong>' + parseFloat(amount).toFixed(2) + ' ' + currency + '</strong>';
                    }
                }

                // Update tenant info if tenant changed
                if (tenantId && tenantId !== window.lastTenantId) {
                    window.lastTenantId = tenantId;
                    fetchTenantData(tenantId);
                }
            }

            // Fetch tenant data and update preview
            async function fetchTenantData(tenantId) {
                try {
                    const response = await fetch(`/api/tenants/${tenantId}`);
                    if (!response.ok) {
                        console.error('Failed to fetch tenant data');
                        return;
                    }

                    const tenant = await response.json();

                    // Update preview with tenant billing info
                    const tenantInfoEl = document.querySelector('[data-preview="tenant_info"]');
                    if (tenantInfoEl) {
                        let html = `<div class="tenant-name"><strong>${tenant.company_name || tenant.name}</strong></div>`;

                        if (tenant.cui) html += `<div>CUI: ${tenant.cui}</div>`;
                        if (tenant.reg_com) html += `<div>Reg. Com.: ${tenant.reg_com}</div>`;
                        if (tenant.bank_name) html += `<div>Bank: ${tenant.bank_name}</div>`;
                        if (tenant.bank_account) html += `<div>IBAN: ${tenant.bank_account}</div>`;
                        if (tenant.address) html += `<div>${tenant.address}</div>`;
                        if (tenant.city || tenant.state) {
                            html += `<div>${tenant.city || ''}${tenant.city && tenant.state ? ', ' : ''}${tenant.state || ''}</div>`;
                        }
                        if (tenant.country) html += `<div>${tenant.country}</div>`;

                        tenantInfoEl.innerHTML = html;
                    }
                } catch (error) {
                    console.error('Error fetching tenant data:', error);
                }
            }

            // Initial update
            setTimeout(updatePreview, 500);
        });
    </script>
    @endpush
</x-filament-panels::page>
