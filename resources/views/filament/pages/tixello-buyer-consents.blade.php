@php
    $types = ['account' => 'Cont cu bilete', 'marketing' => 'Recomandări'];
    $sources = ['checkout' => 'bifă la plată', 'portal' => '/contul-meu', 'unsubscribe' => 'link de dezabonare', 'deletion' => 'ștergerea contului'];
@endphp
<div class="space-y-3 text-sm">
    @forelse ($consents as $c)
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3">
            <p class="font-semibold">
                {{ $types[$c->type] ?? $c->type }}:
                <span class="{{ $c->action === 'granted' ? 'text-success-600' : 'text-danger-600' }}">{{ $c->action === 'granted' ? 'acordat' : 'retras' }}</span>
                <span class="text-gray-500 font-normal">· {{ $c->created_at?->format('d.m.Y H:i:s') }} · {{ $sources[$c->source] ?? $c->source }}</span>
            </p>
            @if ($c->text)<p class="mt-1 text-gray-600 dark:text-gray-300">„{{ $c->text }}” <span class="text-gray-400">(versiunea {{ $c->text_version }})</span></p>@endif
            <p class="mt-1 text-gray-500">
                @if ($c->tenant_id) tenant #{{ $c->tenant_id }} @endif
                @if ($c->order_id) · comanda #{{ $c->order_id }} @endif
                @if ($c->ip) · IP {{ $c->ip }} @endif
            </p>
            @if ($c->user_agent)<p class="mt-1 text-xs text-gray-400 break-all">{{ $c->user_agent }}</p>@endif
        </div>
    @empty
        <p class="text-gray-500">Nicio înregistrare.</p>
    @endforelse
</div>
