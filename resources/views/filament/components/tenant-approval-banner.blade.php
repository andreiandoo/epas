@php($tenant = auth()->user()?->tenant)
@if ($tenant?->isAwaitingApproval())
    <div role="status" class="mb-4 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        <strong>Contul tău e în verificare.</strong>
        Poți pregăti evenimentele, logo-ul și culorile. Vânzarea și site-ul pornesc după aprobare; îți scriem pe {{ $tenant->contact_email }}.
    </div>
@endif
