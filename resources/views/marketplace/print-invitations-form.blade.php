<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Printează invitații — {{ is_array($event->title) ? ($event->title['ro'] ?? '') : $event->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none}</style>
</head>
<body class="min-h-screen bg-gray-100 py-10">
    <div class="mx-auto max-w-2xl px-4">
        <div class="mb-6">
            <a href="{{ url('/marketplace/events/' . $event->id . '/edit?tab=vanzari') }}" class="text-sm text-indigo-600 hover:underline">← Înapoi la eveniment</a>
        </div>

        <div class="bg-white rounded-2xl shadow p-8">
            <h1 class="text-2xl font-bold text-gray-900">Printează invitații</h1>
            <p class="mt-2 text-sm text-gray-600">
                {{ is_array($event->title) ? ($event->title['ro'] ?? '') : $event->title }}
            </p>
            <div class="mt-4 flex items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-purple-50 text-purple-700 text-sm font-semibold">
                    {{ $inviteCount }} invitații emise
                </span>
                @if($inviteCount === 0)
                    <span class="text-sm text-red-600">Nu ai ce printa — nu există invitații pentru acest eveniment.</span>
                @endif
            </div>

            <form method="GET" action="{{ url('/marketplace/events/' . $event->id . '/print-invitations') }}" class="mt-8 space-y-5">
                {{-- Paper size --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Format hârtie</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach(['A3', 'A4', 'A5'] as $p)
                            <label class="cursor-pointer">
                                <input type="radio" name="paper" value="{{ $p }}" {{ $p === 'A4' ? 'checked' : '' }} class="peer sr-only" required>
                                <div class="rounded-lg border-2 border-gray-200 py-3 px-4 text-center text-sm font-semibold peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                    {{ $p }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Orientation --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Orientare</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach([['portrait','Portret'], ['landscape','Landscape']] as [$val, $label])
                            <label class="cursor-pointer">
                                <input type="radio" name="orientation" value="{{ $val }}" {{ $val === 'portrait' ? 'checked' : '' }} class="peer sr-only" required>
                                <div class="rounded-lg border-2 border-gray-200 py-3 px-4 text-center text-sm font-semibold peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                    {{ $label }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Per page --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Invitații pe pagină</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach($layouts as $n)
                            <label class="cursor-pointer">
                                <input type="radio" name="per_page" value="{{ $n }}" {{ $n === 4 ? 'checked' : '' }} class="peer sr-only" required>
                                <div class="rounded-lg border-2 border-gray-200 py-3 px-4 text-center text-sm font-semibold peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                    {{ $n }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">1 pe pagină folosește template-ul complet al invitației. 2+ pe pagină folosește layout compact (titlu + cod + QR) pentru batch print.</p>
                </div>

                {{-- Bleed (split X / Y) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Bleed (mm)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="bleed_x_mm" class="block text-xs text-gray-500 mb-1">Stânga-dreapta</label>
                            <input type="number" name="bleed_x_mm" id="bleed_x_mm" value="0" min="0" max="20" step="0.5" required
                                   class="w-full rounded-lg border border-gray-300 py-2.5 px-4 text-sm focus:border-indigo-600 focus:ring-2 focus:ring-indigo-200">
                        </div>
                        <div>
                            <label for="bleed_y_mm" class="block text-xs text-gray-500 mb-1">Sus-jos</label>
                            <input type="number" name="bleed_y_mm" id="bleed_y_mm" value="0" min="0" max="20" step="0.5" required
                                   class="w-full rounded-lg border border-gray-300 py-2.5 px-4 text-sm focus:border-indigo-600 focus:ring-2 focus:ring-indigo-200">
                        </div>
                    </div>
                </div>

                {{-- Estimate --}}
                @if($inviteCount > 0)
                    <div class="rounded-lg bg-gray-50 p-4 text-xs text-gray-600">
                        <div class="font-semibold text-gray-800 mb-1">Estimare pagini generate</div>
                        <div id="page-estimate">Alege câte pe pagină ca să vezi estimarea.</div>
                    </div>
                @endif

                <button type="submit" @if($inviteCount === 0) disabled @endif
                        class="w-full rounded-lg bg-indigo-600 py-3 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                    Generează PDF
                </button>
            </form>
        </div>
    </div>

    <script>
        // Live page-count estimate as the operator picks per_page.
        const totalInvites = {{ $inviteCount }};
        const estimateEl = document.getElementById('page-estimate');
        function updateEstimate() {
            if (!estimateEl) return;
            const perPage = parseInt(document.querySelector('input[name="per_page"]:checked')?.value ?? 0, 10);
            if (!perPage) return;
            const pages = Math.ceil(totalInvites / perPage);
            const lastCount = totalInvites % perPage;
            const suffix = lastCount === 0
                ? ''
                : ` (ultima pagină conține doar ${lastCount} invitații)`;
            estimateEl.textContent = `${pages} pagini × ${perPage} pe pagină = ${totalInvites} invitații${suffix}`;
        }
        document.querySelectorAll('input[name="per_page"]').forEach(el => el.addEventListener('change', updateEstimate));
        updateEstimate();
    </script>
</body>
</html>
