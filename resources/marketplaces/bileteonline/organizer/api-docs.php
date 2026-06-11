<?php
/**
 * bilete.online — Organizator › Documentație API (v3).
 * Route: /organizator/apidoc
 *
 * RESTful API reference: API key box (load/regenerate/copy), auth, rate limits,
 * errors, activities/tickets/orders endpoints, webhooks. Ported from ambilet to
 * v3 + shell, wired to the organizer.api-key(.regenerate) / organizer.webhook
 * proxy actions.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Documentație API';
$currentPage = 'api-docs';
$apiBase     = 'https://api.' . SITE_NAME . '/v1';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<style>
    .code-block { background: #1B1714; border-radius: .75rem; overflow: hidden; }
    .code-header { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; background: #100d0b; }
    .code-content { padding: 1rem; overflow-x: auto; }
    .code-content pre { font-family: 'JetBrains Mono','Fira Code',monospace; font-size: .8125rem; line-height: 1.6; color: #E8DFCF; margin: 0; }
    .code-content .keyword { color: #DA9A33; }
    .code-content .string { color: #8FBF9F; }
    .code-content .number { color: #E0A84E; }
    .method-badge { padding: .125rem .5rem; border-radius: .25rem; font-size: .6875rem; font-weight: 700; font-family: 'JetBrains Mono',monospace; }
    .method-badge.get { background: rgba(30,74,61,.15); color: #1E4A3D; }
    .method-badge.post { background: rgba(44,95,138,.15); color: #2C5F8A; }
    .method-badge.put { background: rgba(218,154,51,.18); color: #8a6212; }
    .method-badge.delete { background: rgba(232,69,39,.15); color: #E84527; }
    .endpoint-path { font-family: 'JetBrains Mono',monospace; font-size: .875rem; }
    .param-name { font-family: 'JetBrains Mono',monospace; color: #E84527; font-weight: 500; }
    .param-type { font-family: 'JetBrains Mono',monospace; color: #5A4F46; font-size: .75rem; }
</style>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-8">
                <h1 class="font-display text-3xl font-bold leading-none">Documentație API</h1>
                <p class="mt-2 text-ink-soft">Integrează <?= htmlspecialchars(SITE_NAME) ?> în aplicația ta folosind API-ul nostru RESTful.</p>
            </div>

            <!-- API key box -->
            <div class="mb-8 rounded-2xl bg-ink p-6 text-paper">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 font-display font-bold"><svg class="h-5 w-5 text-ochre" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>Cheia ta API</h3>
                    <button onclick="ApiDocs.regenerateKey()" class="inline-flex items-center gap-1.5 rounded-lg border border-paper/20 bg-paper/10 px-3 py-1.5 text-xs font-bold transition hover:bg-paper/20"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Regenerează</button>
                </div>
                <div class="flex gap-3">
                    <div id="api-key-display" class="flex-1 rounded-lg bg-black/30 px-4 py-3 font-mono text-sm text-mint">Se încarcă…</div>
                    <button onclick="ApiDocs.copyApiKey()" class="grid place-items-center rounded-lg bg-vermilion px-3 transition hover:bg-vermilion-d"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                </div>
                <p class="mt-3 text-xs text-paper/50">Nu partaja niciodată cheia API. Dacă a fost compromisă, regenereaz-o imediat.</p>
            </div>

            <div class="flex gap-8">
                <nav class="hidden w-56 shrink-0 lg:block">
                    <div class="sticky top-24 space-y-6">
                        <div>
                            <h4 class="mb-2 font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Introducere</h4>
                            <ul class="space-y-1">
                                <li><a href="#overview" class="block rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink">Prezentare generală</a></li>
                                <li><a href="#authentication" class="block rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink">Autentificare</a></li>
                                <li><a href="#rate-limits" class="block rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink">Limite rate</a></li>
                                <li><a href="#errors" class="block rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink">Gestionare erori</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="mb-2 font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Activități</h4>
                            <ul class="space-y-1">
                                <li><a href="#list-events" class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink"><span class="method-badge get">GET</span> Listă</a></li>
                                <li><a href="#create-event" class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink"><span class="method-badge post">POST</span> Creează</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="mb-2 font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Bilete</h4>
                            <ul class="space-y-1"><li><a href="#validate-ticket" class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink"><span class="method-badge post">POST</span> Validare</a></li></ul>
                        </div>
                        <div>
                            <h4 class="mb-2 font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Webhooks</h4>
                            <ul class="space-y-1"><li><a href="#webhooks" class="block rounded-lg px-3 py-1.5 text-sm text-ink-soft transition hover:bg-paper-2 hover:text-ink">Configurare</a></li></ul>
                        </div>
                    </div>
                </nav>

                <div class="min-w-0 flex-1 space-y-12">
                    <section id="overview">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Prezentare generală</h2>
                        <p class="mb-4 text-ink-soft">API-ul <?= htmlspecialchars(SITE_NAME) ?> îți permite să integrezi funcționalitățile platformei direct în aplicația ta. Toate request-urile trebuie trimise către:</p>
                        <div class="code-block"><div class="code-header"><span class="text-xs font-medium text-paper/50">Base URL</span></div><div class="code-content"><pre><?= htmlspecialchars($apiBase) ?></pre></div></div>
                        <p class="mt-4 text-sm text-ink-soft">API-ul folosește format JSON. Setează header-ul <code class="rounded bg-paper-2 px-1.5 py-0.5 font-mono text-xs">Content-Type: application/json</code>.</p>
                    </section>

                    <section id="authentication">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Autentificare</h2>
                        <p class="mb-4 text-ink-soft">Toate request-urile trebuie să includă cheia API în header-ul <code class="rounded bg-paper-2 px-1.5 py-0.5 font-mono text-xs">Authorization</code>:</p>
                        <div class="code-block">
                            <div class="code-header"><span class="text-xs font-medium text-paper/50">cURL</span><button onclick="ApiDocs.copyCode(this)" class="inline-flex items-center gap-1 rounded border border-paper/20 px-2 py-1 text-xs text-paper/50 transition hover:bg-paper/10">Copiază</button></div>
                            <div class="code-content"><pre>curl <?= htmlspecialchars($apiBase) ?>/events \
  -H <span class="string">"Authorization: Bearer YOUR_API_KEY"</span> \
  -H <span class="string">"Content-Type: application/json"</span></pre></div>
                        </div>
                    </section>

                    <section id="rate-limits">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Limite rate</h2>
                        <div class="flex items-start gap-3 rounded-2xl border-2 border-ochre/30 bg-ochre/10 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-ochre" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div><p class="text-sm text-ink"><strong>Limită:</strong> 1000 request-uri / minut per cheie API.</p><p class="mt-1 text-sm text-ink-soft">Header-ele <code class="rounded bg-ochre/15 px-1 py-0.5 font-mono text-xs">X-RateLimit-Remaining</code> și <code class="rounded bg-ochre/15 px-1 py-0.5 font-mono text-xs">X-RateLimit-Reset</code> sunt incluse în fiecare response.</p></div>
                        </div>
                    </section>

                    <section id="errors">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Gestionare erori</h2>
                        <p class="mb-4 text-ink-soft">API-ul returnează coduri HTTP standard:</p>
                        <div class="overflow-hidden rounded-2xl border-2 border-ink/15">
                            <table class="w-full">
                                <thead><tr class="bg-paper-2 text-left"><th class="px-4 py-3 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Cod</th><th class="px-4 py-3 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Descriere</th></tr></thead>
                                <tbody class="divide-y divide-ink/10 text-sm">
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-forest">200</span></td><td class="px-4 py-3 text-ink-soft">Request reușit</td></tr>
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-forest">201</span></td><td class="px-4 py-3 text-ink-soft">Resursă creată cu succes</td></tr>
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-vermilion">400</span></td><td class="px-4 py-3 text-ink-soft">Request invalid — parametri lipsă sau incorecți</td></tr>
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-vermilion">401</span></td><td class="px-4 py-3 text-ink-soft">Neautorizat — cheie API invalidă</td></tr>
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-vermilion">404</span></td><td class="px-4 py-3 text-ink-soft">Resursă negăsită</td></tr>
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-vermilion">429</span></td><td class="px-4 py-3 text-ink-soft">Prea multe request-uri — limită rate depășită</td></tr>
                                    <tr><td class="px-4 py-3"><span class="font-mono font-bold text-vermilion">500</span></td><td class="px-4 py-3 text-ink-soft">Eroare server</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section id="list-events">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Activități</h2>
                        <div class="overflow-hidden rounded-2xl border-2 border-ink/15">
                            <div class="flex items-center gap-3 border-b-2 border-ink/10 bg-paper-2 p-4"><span class="method-badge get">GET</span><span class="endpoint-path">/events</span></div>
                            <div class="p-4">
                                <p class="mb-4 text-sm text-ink-soft">Returnează lista tuturor activităților tale.</p>
                                <h4 class="mb-2 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Parametri query</h4>
                                <div class="overflow-hidden rounded-lg border-2 border-ink/15">
                                    <table class="w-full">
                                        <thead><tr class="bg-paper-2 text-left text-xs font-bold text-ink-soft"><th class="px-3 py-2">Parametru</th><th class="px-3 py-2">Tip</th><th class="px-3 py-2">Oblig.</th><th class="px-3 py-2">Descriere</th></tr></thead>
                                        <tbody class="divide-y divide-ink/10 text-sm">
                                            <tr><td class="px-3 py-2"><span class="param-name">status</span></td><td class="px-3 py-2"><span class="param-type">string</span></td><td class="px-3 py-2"><span class="rounded bg-paper-2 px-1.5 py-0.5 text-xs font-medium text-ink-soft">Nu</span></td><td class="px-3 py-2 text-ink-soft">draft, published, cancelled</td></tr>
                                            <tr><td class="px-3 py-2"><span class="param-name">limit</span></td><td class="px-3 py-2"><span class="param-type">integer</span></td><td class="px-3 py-2"><span class="rounded bg-paper-2 px-1.5 py-0.5 text-xs font-medium text-ink-soft">Nu</span></td><td class="px-3 py-2 text-ink-soft">Număr rezultate (default 20, max 100)</td></tr>
                                            <tr><td class="px-3 py-2"><span class="param-name">offset</span></td><td class="px-3 py-2"><span class="param-type">integer</span></td><td class="px-3 py-2"><span class="rounded bg-paper-2 px-1.5 py-0.5 text-xs font-medium text-ink-soft">Nu</span></td><td class="px-3 py-2 text-ink-soft">Offset pentru paginare</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <h4 class="mb-2 mt-4 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Exemplu response</h4>
                                <div class="code-block">
                                    <div class="code-header"><span class="text-xs font-medium text-paper/50">JSON</span><button onclick="ApiDocs.copyCode(this)" class="inline-flex items-center gap-1 rounded border border-paper/20 px-2 py-1 text-xs text-paper/50 transition hover:bg-paper/10">Copiază</button></div>
                                    <div class="code-content"><pre>{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: [
    {
      <span class="string">"id"</span>: <span class="string">"evt_abc123"</span>,
      <span class="string">"name"</span>: <span class="string">"Atelier de olărit"</span>,
      <span class="string">"starts_at"</span>: <span class="string">"2026-06-15T18:00:00Z"</span>,
      <span class="string">"venue"</span>: <span class="string">"Studio Creativ"</span>,
      <span class="string">"status"</span>: <span class="string">"published"</span>,
      <span class="string">"tickets_sold"</span>: <span class="number">42</span>,
      <span class="string">"tickets_available"</span>: <span class="number">8</span>
    }
  ],
  <span class="string">"meta"</span>: { <span class="string">"total"</span>: <span class="number">15</span>, <span class="string">"limit"</span>: <span class="number">20</span>, <span class="string">"offset"</span>: <span class="number">0</span> }
}</pre></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="create-event">
                        <div class="overflow-hidden rounded-2xl border-2 border-ink/15">
                            <div class="flex items-center gap-3 border-b-2 border-ink/10 bg-paper-2 p-4"><span class="method-badge post">POST</span><span class="endpoint-path">/events</span></div>
                            <div class="p-4">
                                <p class="mb-4 text-sm text-ink-soft">Creează o activitate nouă.</p>
                                <div class="overflow-hidden rounded-lg border-2 border-ink/15">
                                    <table class="w-full">
                                        <thead><tr class="bg-paper-2 text-left text-xs font-bold text-ink-soft"><th class="px-3 py-2">Parametru</th><th class="px-3 py-2">Tip</th><th class="px-3 py-2">Oblig.</th><th class="px-3 py-2">Descriere</th></tr></thead>
                                        <tbody class="divide-y divide-ink/10 text-sm">
                                            <tr><td class="px-3 py-2"><span class="param-name">name</span></td><td class="px-3 py-2"><span class="param-type">string</span></td><td class="px-3 py-2"><span class="rounded bg-vermilion/15 px-1.5 py-0.5 text-xs font-bold text-vermilion">Da</span></td><td class="px-3 py-2 text-ink-soft">Numele activității</td></tr>
                                            <tr><td class="px-3 py-2"><span class="param-name">starts_at</span></td><td class="px-3 py-2"><span class="param-type">datetime</span></td><td class="px-3 py-2"><span class="rounded bg-vermilion/15 px-1.5 py-0.5 text-xs font-bold text-vermilion">Da</span></td><td class="px-3 py-2 text-ink-soft">Data și ora (ISO 8601)</td></tr>
                                            <tr><td class="px-3 py-2"><span class="param-name">venue_id</span></td><td class="px-3 py-2"><span class="param-type">string</span></td><td class="px-3 py-2"><span class="rounded bg-vermilion/15 px-1.5 py-0.5 text-xs font-bold text-vermilion">Da</span></td><td class="px-3 py-2 text-ink-soft">ID-ul locației</td></tr>
                                            <tr><td class="px-3 py-2"><span class="param-name">description</span></td><td class="px-3 py-2"><span class="param-type">string</span></td><td class="px-3 py-2"><span class="rounded bg-paper-2 px-1.5 py-0.5 text-xs font-medium text-ink-soft">Nu</span></td><td class="px-3 py-2 text-ink-soft">Descrierea activității</td></tr>
                                            <tr><td class="px-3 py-2"><span class="param-name">category_id</span></td><td class="px-3 py-2"><span class="param-type">string</span></td><td class="px-3 py-2"><span class="rounded bg-paper-2 px-1.5 py-0.5 text-xs font-medium text-ink-soft">Nu</span></td><td class="px-3 py-2 text-ink-soft">ID-ul categoriei</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="validate-ticket">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Validare bilet</h2>
                        <div class="overflow-hidden rounded-2xl border-2 border-ink/15">
                            <div class="flex items-center gap-3 border-b-2 border-ink/10 bg-paper-2 p-4"><span class="method-badge post">POST</span><span class="endpoint-path">/tickets/{ticket_id}/validate</span></div>
                            <div class="p-4">
                                <p class="mb-4 text-sm text-ink-soft">Validează un bilet la intrare (check-in). Biletul poate fi scanat o singură dată.</p>
                                <div class="code-block">
                                    <div class="code-header"><span class="text-xs font-medium text-paper/50">JSON</span></div>
                                    <div class="code-content"><pre>{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: {
    <span class="string">"ticket_id"</span>: <span class="string">"tkt_xyz789"</span>,
    <span class="string">"status"</span>: <span class="string">"validated"</span>,
    <span class="string">"validated_at"</span>: <span class="string">"2026-06-15T17:45:00Z"</span>,
    <span class="string">"holder_name"</span>: <span class="string">"Ion Popescu"</span>,
    <span class="string">"ticket_type"</span>: <span class="string">"Acces general"</span>
  }
}</pre></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="webhooks">
                        <h2 class="mb-4 border-b-2 border-ink/10 pb-3 font-display text-xl font-bold">Webhooks</h2>
                        <p class="mb-4 text-ink-soft">Configurează un webhook pentru a primi notificări în timp real despre evenimente importante.</p>
                        <div class="rounded-2xl border-2 border-ink/15 bg-paper-2 p-4">
                            <h4 class="mb-3 text-sm font-bold">URL-ul tău webhook</h4>
                            <div class="flex gap-2">
                                <input type="url" id="webhook-url" placeholder="https://example.com/webhook" class="flex-1 rounded-xl border-2 border-ink/15 bg-paper px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                                <button onclick="ApiDocs.saveWebhook()" class="rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează</button>
                            </div>
                        </div>
                        <div class="mt-6 overflow-hidden rounded-2xl border-2 border-ink/15">
                            <table class="w-full">
                                <thead><tr class="bg-paper-2 text-left"><th class="px-4 py-3 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Eveniment</th><th class="px-4 py-3 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Descriere</th></tr></thead>
                                <tbody class="divide-y divide-ink/10 text-sm">
                                    <tr><td class="px-4 py-3"><code class="rounded bg-paper-2 px-2 py-1 text-xs font-bold text-vermilion">order.created</code></td><td class="px-4 py-3 text-ink-soft">O comandă nouă a fost creată</td></tr>
                                    <tr><td class="px-4 py-3"><code class="rounded bg-paper-2 px-2 py-1 text-xs font-bold text-vermilion">order.completed</code></td><td class="px-4 py-3 text-ink-soft">O comandă a fost finalizată și plătită</td></tr>
                                    <tr><td class="px-4 py-3"><code class="rounded bg-paper-2 px-2 py-1 text-xs font-bold text-vermilion">order.refunded</code></td><td class="px-4 py-3 text-ink-soft">O comandă a fost returnată</td></tr>
                                    <tr><td class="px-4 py-3"><code class="rounded bg-paper-2 px-2 py-1 text-xs font-bold text-vermilion">ticket.validated</code></td><td class="px-4 py-3 text-ink-soft">Un bilet a fost validat (check-in)</td></tr>
                                    <tr><td class="px-4 py-3"><code class="rounded bg-paper-2 px-2 py-1 text-xs font-bold text-vermilion">event.soldout</code></td><td class="px-4 py-3 text-ink-soft">O activitate s-a vândut complet</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-2xl border-2 border-ink/15 bg-paper-2 p-6">
                        <h3 class="mb-2 font-display font-bold">Ai nevoie de ajutor?</h3>
                        <p class="mb-4 text-sm text-ink-soft">Contactează echipa noastră de suport pentru întrebări despre API.</p>
                        <a href="mailto:<?= htmlspecialchars(SUPPORT_EMAIL) ?>" class="inline-flex items-center gap-2 text-sm font-bold text-vermilion hover:underline"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><?= htmlspecialchars(SUPPORT_EMAIL) ?></a>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg); else if (type === 'success') {}
}

const ApiDocs = {
    apiKey: null,
    async init() {
        if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
        await this.loadApiKey();
        this.setupSmoothScroll();
    },
    async loadApiKey() {
        try {
            const r = await BileteOnlineAPI.get('/organizer/api-key');
            if (r && r.success) { this.apiKey = r.data.api_key; document.getElementById('api-key-display').textContent = this.apiKey || 'Nu există cheie API'; }
            else document.getElementById('api-key-display').textContent = 'Nu există cheie API';
        } catch (e) { document.getElementById('api-key-display').textContent = 'Eroare la încărcare'; }
    },
    async copyApiKey() {
        if (!this.apiKey) return;
        try { await navigator.clipboard.writeText(this.apiKey); orgNotify('Cheia API a fost copiată.', 'success'); }
        catch (e) { orgNotify('Nu s-a putut copia cheia.', 'error'); }
    },
    async regenerateKey() {
        if (!confirm('Ești sigur că vrei să regenerezi cheia API? Toate integrările existente vor înceta să funcționeze.')) return;
        try {
            const r = await BileteOnlineAPI.post('/organizer/api-key/regenerate');
            if (r && r.success) { this.apiKey = r.data.api_key; document.getElementById('api-key-display').textContent = this.apiKey; orgNotify('Cheia API a fost regenerată.', 'success'); }
            else orgNotify((r && r.message) || 'Eroare la regenerarea cheii.', 'error');
        } catch (e) { orgNotify('Eroare la regenerarea cheii API.', 'error'); }
    },
    async copyCode(button) {
        const code = button.closest('.code-block').querySelector('pre').textContent;
        try { await navigator.clipboard.writeText(code); const o = button.textContent; button.textContent = 'Copiat!'; setTimeout(() => button.textContent = o, 2000); } catch (e) {}
    },
    async saveWebhook() {
        const url = document.getElementById('webhook-url').value;
        if (!url) { orgNotify('Introdu URL-ul webhook.', 'error'); return; }
        try {
            const r = await BileteOnlineAPI.post('/organizer/webhook', { url });
            if (r && r.success) orgNotify('URL-ul webhook a fost salvat.', 'success');
            else orgNotify((r && r.message) || 'Eroare la salvarea webhook-ului.', 'error');
        } catch (e) { orgNotify('Eroare la salvarea webhook-ului.', 'error'); }
    },
    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', function (e) {
            e.preventDefault();
            const t = document.querySelector(this.getAttribute('href'));
            if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }));
    },
};
document.addEventListener('DOMContentLoaded', () => ApiDocs.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
