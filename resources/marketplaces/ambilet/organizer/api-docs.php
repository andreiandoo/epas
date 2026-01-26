<?php
/**
 * Organizer API Documentation Page
 *
 * API documentation for organizers
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Documentație API';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'api-docs';

require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<style>
    .code-block {
        background: #1E293B;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .code-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 1rem;
        background: #0F172A;
    }
    .code-content {
        padding: 1rem;
        overflow-x: auto;
    }
    .code-content pre {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 0.8125rem;
        line-height: 1.6;
        color: #E2E8F0;
        margin: 0;
    }
    .code-content .keyword { color: #C084FC; }
    .code-content .string { color: #86EFAC; }
    .code-content .number { color: #FCD34D; }
    .code-content .comment { color: #64748B; }

    .method-badge {
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.6875rem;
        font-weight: 700;
        font-family: 'JetBrains Mono', monospace;
    }
    .method-badge.get { background: #D1FAE5; color: #065F46; }
    .method-badge.post { background: #DBEAFE; color: #1E40AF; }
    .method-badge.put { background: #FEF3C7; color: #92400E; }
    .method-badge.delete { background: #FEE2E2; color: #991B1B; }

    .endpoint-path {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.875rem;
    }

    .param-name {
        font-family: 'JetBrains Mono', monospace;
        color: #A51C30;
        font-weight: 500;
    }
    .param-type {
        font-family: 'JetBrains Mono', monospace;
        color: #64748B;
        font-size: 0.75rem;
    }
</style>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-6">
        <div class="max-w-4xl mx-auto">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-secondary">Documentație API</h1>
                <p class="mt-2 text-muted">Integrează <?= SITE_NAME ?> în aplicația ta folosind API-ul nostru RESTful</p>
            </div>

            <!-- API Key Box -->
            <div class="p-6 mb-8 text-white rounded-xl bg-gradient-to-br from-secondary to-slate-900">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="flex items-center gap-2 font-semibold">
                        <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        Cheia ta API
                    </h3>
                    <button onclick="ApiDocs.regenerateKey()" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Regenerează
                    </button>
                </div>
                <div class="flex gap-3">
                    <div id="api-key-display" class="flex-1 px-4 py-3 font-mono text-sm text-green-400 rounded-lg bg-black/30">
                        Se încarcă...
                    </div>
                    <button onclick="ApiDocs.copyApiKey()" class="p-3 transition-colors rounded-lg bg-primary hover:bg-primary-light">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>
                <p class="mt-3 text-xs text-slate-400">
                    <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Nu partaja niciodată cheia API. Dacă a fost compromisă, regenereaz-o imediat.
                </p>
            </div>

            <!-- Sidebar Navigation -->
            <div class="flex gap-8">
                <!-- Docs Sidebar -->
                <nav class="hidden w-56 shrink-0 lg:block">
                    <div class="sticky space-y-6 top-24">
                        <div>
                            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Introducere</h4>
                            <ul class="space-y-1">
                                <li><a href="#overview" class="block px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary">Prezentare generală</a></li>
                                <li><a href="#authentication" class="block px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary">Autentificare</a></li>
                                <li><a href="#rate-limits" class="block px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary">Limite rate</a></li>
                                <li><a href="#errors" class="block px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary">Gestionare erori</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Evenimente</h4>
                            <ul class="space-y-1">
                                <li><a href="#list-events" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge get">GET</span> Lista</a></li>
                                <li><a href="#get-event" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge get">GET</span> Detalii</a></li>
                                <li><a href="#create-event" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge post">POST</span> Crează</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Bilete</h4>
                            <ul class="space-y-1">
                                <li><a href="#list-tickets" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge get">GET</span> Lista</a></li>
                                <li><a href="#validate-ticket" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge post">POST</span> Validare</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Comenzi</h4>
                            <ul class="space-y-1">
                                <li><a href="#list-orders" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge get">GET</span> Lista</a></li>
                                <li><a href="#get-order" class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary"><span class="method-badge get">GET</span> Detalii</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Webhooks</h4>
                            <ul class="space-y-1">
                                <li><a href="#webhooks" class="block px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary">Configurare</a></li>
                                <li><a href="#webhook-events" class="block px-3 py-1.5 text-sm rounded-lg text-muted hover:bg-slate-100 hover:text-secondary">Evenimente</a></li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <!-- Documentation Content -->
                <div class="flex-1 min-w-0 space-y-12">
                    <!-- Overview -->
                    <section id="overview">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Prezentare generală</h2>
                        <p class="mb-4 text-muted">API-ul <?= SITE_NAME ?> îți permite să integrezi funcționalitățile platformei direct în aplicația ta. Toate request-urile trebuie trimise către:</p>

                        <div class="code-block">
                            <div class="code-header">
                                <span class="text-xs font-medium text-slate-400">Base URL</span>
                            </div>
                            <div class="code-content">
                                <pre>https://api.<?= strtolower(str_replace(' ', '', SITE_NAME)) ?>.ro/v1</pre>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-muted">API-ul folosește format JSON pentru toate request-urile și response-urile. Asigură-te că setezi header-ul <code class="px-1.5 py-0.5 bg-slate-100 rounded text-xs font-mono">Content-Type: application/json</code>.</p>
                    </section>

                    <!-- Authentication -->
                    <section id="authentication">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Autentificare</h2>
                        <p class="mb-4 text-muted">Toate request-urile trebuie să includă cheia API în header-ul <code class="px-1.5 py-0.5 bg-slate-100 rounded text-xs font-mono">Authorization</code>:</p>

                        <div class="code-block">
                            <div class="code-header">
                                <span class="text-xs font-medium text-slate-400">cURL</span>
                                <button onclick="ApiDocs.copyCode(this)" class="flex items-center gap-1 px-2 py-1 text-xs border rounded text-slate-400 border-white/20 hover:bg-white/10">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    Copiază
                                </button>
                            </div>
                            <div class="code-content">
                                <pre>curl https://api.<?= strtolower(str_replace(' ', '', SITE_NAME)) ?>.ro/v1/events \
  -H <span class="string">"Authorization: Bearer YOUR_API_KEY"</span> \
  -H <span class="string">"Content-Type: application/json"</span></pre>
                            </div>
                        </div>
                    </section>

                    <!-- Rate Limits -->
                    <section id="rate-limits">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Limite rate</h2>

                        <div class="flex items-start gap-3 p-4 border rounded-xl bg-yellow-50 border-yellow-200">
                            <svg class="w-5 h-5 mt-0.5 text-yellow-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <p class="text-sm text-yellow-800"><strong>Limită:</strong> 1000 request-uri / minut per cheie API.</p>
                                <p class="mt-1 text-sm text-yellow-700">Header-ele <code class="px-1 py-0.5 bg-yellow-100 rounded text-xs font-mono">X-RateLimit-Remaining</code> și <code class="px-1 py-0.5 bg-yellow-100 rounded text-xs font-mono">X-RateLimit-Reset</code> sunt incluse în fiecare response.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Errors -->
                    <section id="errors">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Gestionare erori</h2>
                        <p class="mb-4 text-muted">API-ul returnează coduri HTTP standard pentru a indica succesul sau eșecul request-urilor:</p>

                        <div class="overflow-hidden border rounded-xl border-border">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-4 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Cod</th>
                                        <th class="px-4 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Descriere</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-green-600">200</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Request reușit</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-green-600">201</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Resursă creată cu succes</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-red-600">400</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Request invalid - parametri lipsă sau incorecți</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-red-600">401</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Neautorizat - cheie API invalidă</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-red-600">404</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Resursă negăsită</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-red-600">429</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Prea multe request-uri - limită rate depășită</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-red-600">500</span></td>
                                        <td class="px-4 py-3 text-sm text-muted">Eroare server</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- List Events -->
                    <section id="list-events">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Evenimente</h2>

                        <div class="overflow-hidden border rounded-xl border-border">
                            <div class="flex items-center gap-3 p-4 border-b bg-slate-50 border-border">
                                <span class="method-badge get">GET</span>
                                <span class="endpoint-path text-secondary">/events</span>
                            </div>
                            <div class="p-4">
                                <p class="mb-4 text-sm text-muted">Returnează lista tuturor evenimentelor tale</p>

                                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Parametri query</h4>
                                <div class="overflow-hidden border rounded-lg border-border">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="bg-slate-50">
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Parametru</th>
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Tip</th>
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Obligatoriu</th>
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Descriere</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-sm divide-y divide-border">
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">status</span></td>
                                                <td class="px-3 py-2"><span class="param-type">string</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded">Nu</span></td>
                                                <td class="px-3 py-2 text-muted">Filtrează după status: draft, published, cancelled</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">limit</span></td>
                                                <td class="px-3 py-2"><span class="param-type">integer</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded">Nu</span></td>
                                                <td class="px-3 py-2 text-muted">Număr rezultate (default: 20, max: 100)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">offset</span></td>
                                                <td class="px-3 py-2"><span class="param-type">integer</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded">Nu</span></td>
                                                <td class="px-3 py-2 text-muted">Offset pentru paginare</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <h4 class="mt-4 mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Exemplu response</h4>
                                <div class="code-block">
                                    <div class="code-header">
                                        <span class="text-xs font-medium text-slate-400">JSON</span>
                                        <button onclick="ApiDocs.copyCode(this)" class="flex items-center gap-1 px-2 py-1 text-xs border rounded text-slate-400 border-white/20 hover:bg-white/10">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            Copiază
                                        </button>
                                    </div>
                                    <div class="code-content">
                                        <pre>{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: [
    {
      <span class="string">"id"</span>: <span class="string">"evt_abc123"</span>,
      <span class="string">"name"</span>: <span class="string">"Concert Coldplay"</span>,
      <span class="string">"starts_at"</span>: <span class="string">"2025-06-15T20:00:00Z"</span>,
      <span class="string">"venue"</span>: <span class="string">"Arena Națională"</span>,
      <span class="string">"status"</span>: <span class="string">"published"</span>,
      <span class="string">"tickets_sold"</span>: <span class="number">4520</span>,
      <span class="string">"tickets_available"</span>: <span class="number">480</span>
    }
  ],
  <span class="string">"meta"</span>: {
    <span class="string">"total"</span>: <span class="number">15</span>,
    <span class="string">"limit"</span>: <span class="number">20</span>,
    <span class="string">"offset"</span>: <span class="number">0</span>
  }
}</pre>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 mt-4">
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-green-50 text-green-700"><span class="font-mono">200</span> Success</span>
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700"><span class="font-mono">401</span> Unauthorized</span>
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700"><span class="font-mono">429</span> Rate Limited</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Create Event -->
                    <section id="create-event">
                        <div class="overflow-hidden border rounded-xl border-border">
                            <div class="flex items-center gap-3 p-4 border-b bg-slate-50 border-border">
                                <span class="method-badge post">POST</span>
                                <span class="endpoint-path text-secondary">/events</span>
                            </div>
                            <div class="p-4">
                                <p class="mb-4 text-sm text-muted">Creează un eveniment nou</p>

                                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Body parametri</h4>
                                <div class="overflow-hidden border rounded-lg border-border">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="bg-slate-50">
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Parametru</th>
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Tip</th>
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Obligatoriu</th>
                                                <th class="px-3 py-2 text-xs font-semibold text-left text-muted">Descriere</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-sm divide-y divide-border">
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">name</span></td>
                                                <td class="px-3 py-2"><span class="param-type">string</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">Da</span></td>
                                                <td class="px-3 py-2 text-muted">Numele evenimentului</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">starts_at</span></td>
                                                <td class="px-3 py-2"><span class="param-type">datetime</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">Da</span></td>
                                                <td class="px-3 py-2 text-muted">Data și ora evenimentului (ISO 8601)</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">venue_id</span></td>
                                                <td class="px-3 py-2"><span class="param-type">string</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">Da</span></td>
                                                <td class="px-3 py-2 text-muted">ID-ul locației</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">description</span></td>
                                                <td class="px-3 py-2"><span class="param-type">string</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded">Nu</span></td>
                                                <td class="px-3 py-2 text-muted">Descrierea evenimentului</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2"><span class="param-name">category_id</span></td>
                                                <td class="px-3 py-2"><span class="param-type">string</span></td>
                                                <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded">Nu</span></td>
                                                <td class="px-3 py-2 text-muted">ID-ul categoriei</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex flex-wrap gap-2 mt-4">
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-green-50 text-green-700"><span class="font-mono">201</span> Created</span>
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700"><span class="font-mono">400</span> Bad Request</span>
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700"><span class="font-mono">401</span> Unauthorized</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Validate Ticket -->
                    <section id="validate-ticket">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Validare bilet</h2>

                        <div class="overflow-hidden border rounded-xl border-border">
                            <div class="flex items-center gap-3 p-4 border-b bg-slate-50 border-border">
                                <span class="method-badge post">POST</span>
                                <span class="endpoint-path text-secondary">/tickets/{ticket_id}/validate</span>
                            </div>
                            <div class="p-4">
                                <p class="mb-4 text-sm text-muted">Validează un bilet la intrare (check-in). Biletul poate fi scanat o singură dată.</p>

                                <div class="code-block">
                                    <div class="code-header">
                                        <span class="text-xs font-medium text-slate-400">Exemplu request</span>
                                    </div>
                                    <div class="code-content">
                                        <pre>curl -X POST https://api.<?= strtolower(str_replace(' ', '', SITE_NAME)) ?>.ro/v1/tickets/tkt_xyz789/validate \
  -H <span class="string">"Authorization: Bearer YOUR_API_KEY"</span> \
  -H <span class="string">"Content-Type: application/json"</span></pre>
                                    </div>
                                </div>

                                <h4 class="mt-4 mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Response succes</h4>
                                <div class="code-block">
                                    <div class="code-header">
                                        <span class="text-xs font-medium text-slate-400">JSON</span>
                                    </div>
                                    <div class="code-content">
                                        <pre>{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: {
    <span class="string">"ticket_id"</span>: <span class="string">"tkt_xyz789"</span>,
    <span class="string">"status"</span>: <span class="string">"validated"</span>,
    <span class="string">"validated_at"</span>: <span class="string">"2025-06-15T19:45:00Z"</span>,
    <span class="string">"holder_name"</span>: <span class="string">"Ion Popescu"</span>,
    <span class="string">"ticket_type"</span>: <span class="string">"VIP"</span>,
    <span class="string">"event"</span>: {
      <span class="string">"name"</span>: <span class="string">"Concert Coldplay"</span>,
      <span class="string">"starts_at"</span>: <span class="string">"2025-06-15T20:00:00Z"</span>
    }
  }
}</pre>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 mt-4">
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-green-50 text-green-700"><span class="font-mono">200</span> Valid</span>
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700"><span class="font-mono">400</span> Already Used</span>
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-700"><span class="font-mono">404</span> Not Found</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Webhooks -->
                    <section id="webhooks">
                        <h2 class="pb-3 mb-4 text-xl font-bold border-b text-secondary border-border">Webhooks</h2>
                        <p class="mb-4 text-muted">Configurează webhooks pentru a primi notificări în timp real despre evenimente importante.</p>

                        <div class="p-4 border rounded-xl border-border bg-slate-50">
                            <h4 class="mb-3 text-sm font-semibold text-secondary">URL-ul tău webhook</h4>
                            <div class="flex gap-2">
                                <input type="url" id="webhook-url" placeholder="https://example.com/webhook" class="flex-1 px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                <button onclick="ApiDocs.saveWebhook()" class="px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">
                                    Salvează
                                </button>
                            </div>
                        </div>

                        <h3 id="webhook-events" class="mt-6 mb-3 text-lg font-semibold text-secondary">Evenimente disponibile</h3>
                        <div class="overflow-hidden border rounded-xl border-border">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-4 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Eveniment</th>
                                        <th class="px-4 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Descriere</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="px-4 py-3"><code class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 text-primary">order.created</code></td>
                                        <td class="px-4 py-3 text-sm text-muted">O comandă nouă a fost creată</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><code class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 text-primary">order.completed</code></td>
                                        <td class="px-4 py-3 text-sm text-muted">O comandă a fost finalizată și plătită</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><code class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 text-primary">order.refunded</code></td>
                                        <td class="px-4 py-3 text-sm text-muted">O comandă a fost returnată</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><code class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 text-primary">ticket.validated</code></td>
                                        <td class="px-4 py-3 text-sm text-muted">Un bilet a fost validat (check-in)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3"><code class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 text-primary">event.soldout</code></td>
                                        <td class="px-4 py-3 text-sm text-muted">Un eveniment s-a vândut complet</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Support -->
                    <section class="p-6 border rounded-xl bg-slate-50 border-border">
                        <h3 class="mb-2 font-semibold text-secondary">Ai nevoie de ajutor?</h3>
                        <p class="mb-4 text-sm text-muted">Contactează echipa noastră de suport pentru întrebări despre API.</p>
                        <a href="mailto:<?= SUPPORT_EMAIL ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <?= SUPPORT_EMAIL ?>
                        </a>
                    </section>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
/**
 * API Documentation Module
 */
const ApiDocs = {
    apiKey: null,

    /**
     * Initialize
     */
    async init() {
        await this.loadApiKey();
        this.setupSmoothScroll();
    },

    /**
     * Load API key
     */
    async loadApiKey() {
        try {
            const response = await window.api.fetch('/organizer/api-key');
            if (response.success) {
                this.apiKey = response.data.api_key;
                document.getElementById('api-key-display').textContent = this.apiKey || 'Nu există cheie API';
            }
        } catch (error) {
            console.error('Error loading API key:', error);
            document.getElementById('api-key-display').textContent = 'Eroare la încărcare';
        }
    },

    /**
     * Copy API key to clipboard
     */
    async copyApiKey() {
        if (!this.apiKey) return;

        try {
            await navigator.clipboard.writeText(this.apiKey);
            this.showSuccess('Cheia API a fost copiată');
        } catch (error) {
            console.error('Error copying:', error);
            this.showError('Nu s-a putut copia cheia');
        }
    },

    /**
     * Regenerate API key
     */
    async regenerateKey() {
        if (!confirm('Ești sigur că vrei să regenerezi cheia API? Toate integrările existente vor înceta să funcționeze.')) {
            return;
        }

        try {
            const response = await window.api.fetch('/organizer/api-key/regenerate', {
                method: 'POST'
            });

            if (response.success) {
                this.apiKey = response.data.api_key;
                document.getElementById('api-key-display').textContent = this.apiKey;
                this.showSuccess('Cheia API a fost regenerată');
            } else {
                this.showError(response.message || 'Eroare la regenerarea cheii');
            }
        } catch (error) {
            console.error('Error regenerating key:', error);
            this.showError('Eroare la regenerarea cheii API');
        }
    },

    /**
     * Copy code block content
     */
    async copyCode(button) {
        const codeBlock = button.closest('.code-block');
        const code = codeBlock.querySelector('pre').textContent;

        try {
            await navigator.clipboard.writeText(code);

            // Visual feedback
            const originalText = button.innerHTML;
            button.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copiat!';
            button.classList.add('text-green-400');

            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('text-green-400');
            }, 2000);
        } catch (error) {
            console.error('Error copying:', error);
        }
    },

    /**
     * Save webhook URL
     */
    async saveWebhook() {
        const url = document.getElementById('webhook-url').value;

        if (!url) {
            this.showError('Introdu URL-ul webhook');
            return;
        }

        try {
            const response = await window.api.fetch('/organizer/webhook', {
                method: 'POST',
                body: JSON.stringify({ url })
            });

            if (response.success) {
                this.showSuccess('URL-ul webhook a fost salvat');
            } else {
                this.showError(response.message || 'Eroare la salvarea webhook-ului');
            }
        } catch (error) {
            console.error('Error saving webhook:', error);
            this.showError('Eroare la salvarea webhook-ului');
        }
    },

    /**
     * Setup smooth scrolling for anchor links
     */
    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    },

    /**
     * Show notifications
     */
    showSuccess(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.success(message);
        } else {
            alert(message);
        }
    },

    showError(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.error(message);
        } else {
            alert(message);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => ApiDocs.init());
</script>
