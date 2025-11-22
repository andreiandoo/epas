<x-filament-panels::page>
    @if($invoices->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-12 text-center">
            <x-heroicon-o-document-text class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No invoices yet</h3>
            <p class="text-gray-600">Your invoices will appear here after purchases.</p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Invoice #</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Date</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Description</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Amount</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Status</th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $invoice->number ?? "INV-{$invoice->id}" }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $invoice->issue_date?->format('M d, Y') ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                                {{ $invoice->description ?? '-' }}
                            </td>
                            <td class="px-6 py-4 font-medium">
                                {{ number_format($invoice->amount, 2) }} {{ $invoice->currency ?? 'EUR' }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColors = [
                                        'paid' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'overdue' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$invoice->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                    Download PDF
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
