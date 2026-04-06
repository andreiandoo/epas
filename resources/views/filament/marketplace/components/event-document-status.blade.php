@php
    use App\Models\MarketplaceTaxTemplate;
    use Illuminate\Support\Facades\Storage;

    $isEventFinished = $event->status === 'archived';
    $isEventPublished = (bool) $event->is_published;

    // Map trigger → human-readable condition
    $triggerLabels = [
        'after_event_published' => 'După publicare eveniment',
        'after_event_finished' => 'După finalizare eveniment',
        'after_payout_completed' => 'După aprobare decont',
        null => 'Manual',
    ];

    // Map trigger → can generate now?
    $triggerCanGenerate = [
        'after_event_published' => $isEventPublished,
        'after_event_finished' => $isEventFinished,
        'after_payout_completed' => true, // Always allow manual for payouts
        null => true,
    ];

    // Map trigger → reason if blocked
    $triggerBlockedReason = [
        'after_event_published' => 'Evenimentul nu e publicat',
        'after_event_finished' => 'Evenimentul nu e încheiat',
    ];

    // Build lookup of existing documents per template
    $existingByTemplate = collect();
    foreach ($generatedDocs as $doc) {
        $existingByTemplate[$doc->marketplace_tax_template_id] = $doc;
    }
    // Also check organizer docs by type
    $orgDocsByType = $organizerDocs->keyBy('document_type');
@endphp

<div class="space-y-2">
    @forelse($templates as $template)
        @php
            $typeLabel = MarketplaceTaxTemplate::TYPES[$template->type] ?? ucfirst($template->type);
            $trigger = $template->trigger;
            $canGenerate = $triggerCanGenerate[$trigger] ?? true;
            $blockedReason = !$canGenerate ? ($triggerBlockedReason[$trigger] ?? '') : '';
            $conditionLabel = $triggerLabels[$trigger] ?? 'Manual';

            // Check if already generated
            $existingDoc = $existingByTemplate[$template->id] ?? null;
            $orgDoc = $orgDocsByType[$template->type] ?? null;
            $hasDocument = $existingDoc || $orgDoc;
            $doc = $existingDoc ?? $orgDoc;
        @endphp

        <div class="flex items-start gap-3 p-2.5 rounded-lg border {{ $hasDocument ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/10' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50' }}">
            {{-- Status icon --}}
            <div class="flex-shrink-0 mt-0.5">
                @if($hasDocument)
                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                @else
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                @endif
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $template->name }}</span>
                </div>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $typeLabel }}</span>
                    <span class="text-[10px] text-gray-400">{{ $conditionLabel }}</span>
                </div>

                @if($hasDocument)
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-[10px] text-green-600 dark:text-green-400">
                            Generat {{ $doc->created_at?->format('d.m.Y H:i') }}
                        </span>
                        @php
                            $downloadUrl = $doc->file_path ? Storage::disk('public')->url($doc->file_path) : '#';
                        @endphp
                        <a href="{{ $downloadUrl }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarcă
                        </a>
                    </div>
                @else
                    <div class="mt-1.5">
                        @if($canGenerate)
                            <button
                                type="button"
                                x-data="{ loading: false }"
                                x-on:click="
                                    if (!confirm('Generează documentul {{ addslashes($template->name) }}?')) return;
                                    loading = true;
                                    fetch('/marketplace/api/events/{{ $event->id }}/generate-document', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        },
                                        body: JSON.stringify({ template_id: {{ $template->id }} })
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        loading = false;
                                        if (data.success) {
                                            window.location.reload();
                                        } else {
                                            alert(data.message || 'Eroare la generare');
                                        }
                                    })
                                    .catch(e => { loading = false; alert('Eroare: ' + e.message); })
                                "
                                x-bind:disabled="loading"
                                class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-white bg-primary-600 rounded hover:bg-primary-700 transition disabled:opacity-50"
                            >
                                <template x-if="loading">
                                    <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </template>
                                <svg x-show="!loading" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span x-text="loading ? 'Se generează...' : 'Generează'"></span>
                            </button>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-gray-400 bg-gray-100 dark:bg-gray-800 rounded cursor-not-allowed" title="{{ $blockedReason }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-6a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                {{ $blockedReason }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-4">
            <p class="text-xs text-gray-400">Nu există template-uri de documente configurate.</p>
        </div>
    @endforelse
</div>
