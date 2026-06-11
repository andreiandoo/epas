@php
    use Illuminate\Support\Facades\Storage;

    /**
     * Lists EVERY document generated for this event, regardless of source:
     *   - EventGeneratedDocument (event-bound templates: cerere avizare, declaratie,
     *     pv distrugere etc.)
     *   - OrganizerDocument (decont PDFs created by the payout flow + any
     *     org-level docs that carry an event_id reference)
     *   - Invoice (per-payout invoices for the event's payouts)
     *
     * All three sources are normalized into a single chronological feed sorted
     * descending by creation date. Each card surfaces the relevant context
     * (payout reference, template name, status) and a download/view link
     * when a file_path exists.
     */

    $items = collect();

    // 1) EventGeneratedDocument — template() relation; type comes from the
    //    linked template (the model itself has no document_type column).
    foreach ($eventGeneratedDocs as $doc) {
        $template = $doc->template;
        $docType = $template?->type;
        $items->push([
            'source' => 'event_generated',
            'title' => $template?->name ?? ($docType ? ucfirst(str_replace('_', ' ', $docType)) : 'Document'),
            'type_label' => ($docType && isset(\App\Models\MarketplaceTaxTemplate::TYPES[$docType]))
                ? \App\Models\MarketplaceTaxTemplate::TYPES[$docType]
                : ($docType ? ucfirst(str_replace('_', ' ', $docType)) : 'Document'),
            'created_at' => $doc->created_at,
            'file_path' => $doc->file_path,
            'file_name' => $doc->filename ?? null,
            'file_size' => $doc->file_size ?? null,
            'context' => $template?->name ? "Template: {$template->name}" : null,
            'badge_color' => 'blue',
            'icon' => 'doc',
        ]);
    }

    // 2) OrganizerDocument — most likely decont PDFs; carry marketplace_payout_id
    foreach ($organizerDocs as $doc) {
        $payout = $doc->marketplace_payout_id
            ? \App\Models\MarketplacePayout::find($doc->marketplace_payout_id)
            : null;
        $title = $doc->title ?? ($doc->document_type ? ucfirst(str_replace('_', ' ', $doc->document_type)) : 'Document');
        $context = null;
        if ($payout) {
            $amount = number_format((float) $payout->amount, 2, ',', '.');
            $context = "Decont {$payout->reference} • {$amount} {$payout->currency} • {$payout->status}";
        }
        $items->push([
            'source' => 'organizer_doc',
            'title' => $title,
            'type_label' => \App\Models\MarketplaceTaxTemplate::TYPES[$doc->document_type] ?? ucfirst(str_replace('_', ' ', $doc->document_type ?? 'document')),
            'created_at' => $doc->created_at,
            'file_path' => $doc->file_path,
            'file_name' => $doc->file_name ?? null,
            'file_size' => $doc->file_size ?? null,
            'context' => $context,
            'badge_color' => 'emerald',
            'icon' => 'decont',
            'payout_id' => $payout?->id,
        ]);
    }

    // 3) Invoice — facturi de comision pe payouts ale acestui event
    foreach ($invoices as $invoice) {
        $payout = $invoice->payout;
        $amount = number_format((float) $invoice->amount, 2, ',', '.');
        $context = "Factură {$invoice->number} • {$amount} {$invoice->currency} • {$invoice->status}";
        if ($payout) {
            $context .= " • pentru decont {$payout->reference}";
        }
        $items->push([
            'source' => 'invoice',
            'title' => 'Factură ' . $invoice->number,
            'type_label' => $invoice->type === 'pos' ? 'Factură POS' : 'Factură',
            'created_at' => $invoice->created_at,
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'context' => $context,
            'badge_color' => 'amber',
            'icon' => 'invoice',
            'invoice_id' => $invoice->id,
        ]);
    }

    $items = $items->sortByDesc('created_at')->values();

    $formatSize = function (?int $bytes): ?string {
        if (!$bytes) return null;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 2) . ' MB';
    };
@endphp

<div class="space-y-2">
    @forelse($items as $item)
        @php
            $downloadUrl = $item['file_path']
                ? Storage::disk('public')->url($item['file_path'])
                : null;
            $invoiceUrl = ($item['source'] === 'invoice' && !empty($item['invoice_id']))
                ? \App\Filament\Marketplace\Resources\OrganizerInvoiceResource::getUrl('edit', ['record' => $item['invoice_id']])
                : null;
            $payoutUrl = !empty($item['payout_id'])
                ? \App\Filament\Marketplace\Resources\PayoutResource::getUrl('view', ['record' => $item['payout_id']])
                : null;
            $sizeLabel = $formatSize($item['file_size']);

            $badgeStyles = [
                'blue'    => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                'amber'   => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            ];
            $badgeClass = $badgeStyles[$item['badge_color']] ?? $badgeStyles['blue'];
        @endphp

        <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="flex-shrink-0 mt-0.5">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium border {{ $badgeClass }}">{{ $item['type_label'] }}</span>
                </div>

                @if($item['context'])
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['context'] }}</div>
                @endif

                <div class="flex items-center gap-3 mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $item['created_at']?->format('d.m.Y H:i') ?? '—' }}
                    </span>
                    @if($sizeLabel)
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h6m-3-3l3 3-3 3"/></svg>
                            {{ $sizeLabel }}
                        </span>
                    @endif
                    @if($item['file_name'])
                        <span class="truncate max-w-xs">{{ $item['file_name'] }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-1.5 flex-shrink-0">
                @if($downloadUrl)
                    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-primary-600 rounded hover:bg-primary-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarcă
                    </a>
                @endif
                @if($invoiceUrl)
                    <a href="{{ $invoiceUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Vezi factură
                    </a>
                @endif
                @if($payoutUrl)
                    <a href="{{ $payoutUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Vezi decont
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-6">
            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="mt-2 text-xs text-gray-400">Niciun document încă generat pentru acest eveniment.</p>
        </div>
    @endforelse
</div>
