<?php
/**
 * bilete.online — Organizer public profile (v3).
 * Route: /organizator/{slug}  (→ public.php?slug={slug})
 *
 * Public-facing organizer landing: hero, profile card (avatar, badges, stats,
 * socials), tabs (upcoming activities / past activities / about), sidebar with
 * about + quick facts + contact card, and a contact modal. All data is loaded
 * dynamically from the API. Activity-centric port of the ambilet organizer
 * public page, restyled to the bilete.online v3 design and wired to
 * BileteOnlineAPI / BileteOnlineEventCard.
 *
 * Uses the PUBLIC site shell (head + header + footer), NOT the organizer
 * dashboard shell — this is a visitor-facing page, mirroring the source.
 */

require_once __DIR__ . '/../includes/config.php';

// Page configuration — updated dynamically after API load
$pageTitle       = 'Organizator';
$pageDescription = 'Descoperă activitățile acestui organizator pe bilete.online.';
$bodyClass       = 'page-organizer';

// This page renders its own <main>, so tell the shell not to open/close one.
$skipMainTag = true;

// Needs the shared activity-card component to render upcoming/past activities.
$footerExtraJs = ['assets/js/components/event-card.js'];

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<section class="relative h-[320px] overflow-hidden bg-ink">
    <div id="heroImage" class="absolute inset-0"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-ink/30 to-ink/80"></div>
</section>

<!-- Profile Section -->
<section class="relative z-10 mx-auto -mt-24 max-w-7xl px-6">
    <div id="profileCard" class="flex flex-col items-start gap-8 rounded-2xl border-2 border-ink bg-paper p-8 shadow-xl md:flex-row">
        <!-- Skeleton -->
        <div class="h-[140px] w-[140px] flex-shrink-0 animate-pulse rounded-2xl border-4 border-paper bg-ink/10 shadow-lg"></div>
        <div class="flex-1">
            <div class="mb-3 h-6 w-32 animate-pulse rounded bg-ink/10"></div>
            <div class="mb-2 h-8 w-64 animate-pulse rounded bg-ink/10"></div>
            <div class="mb-4 h-4 w-full max-w-md animate-pulse rounded bg-ink/5"></div>
            <div class="mb-5 h-4 w-48 animate-pulse rounded bg-ink/5"></div>
            <div class="flex gap-8">
                <div class="h-12 w-16 animate-pulse rounded bg-ink/10"></div>
                <div class="h-12 w-16 animate-pulse rounded bg-ink/10"></div>
            </div>
        </div>
        <div class="flex w-full flex-col items-center gap-3 md:w-auto md:items-end">
            <div class="h-12 w-40 animate-pulse rounded-xl bg-ink/10"></div>
            <div class="flex gap-2">
                <div class="h-10 w-10 animate-pulse rounded-lg bg-ink/10"></div>
                <div class="h-10 w-10 animate-pulse rounded-lg bg-ink/10"></div>
                <div class="h-10 w-10 animate-pulse rounded-lg bg-ink/10"></div>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="mx-auto max-w-7xl px-6 py-10">
    <!-- Tabs -->
    <div class="mb-8 flex gap-1 rounded-2xl border-2 border-ink bg-paper p-1.5">
        <button class="tab-btn flex flex-1 items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-bold transition bg-vermilion text-paper" data-tab="events">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>Activități</span>
            <span id="tabCountEvents" class="rounded-full bg-paper/20 px-2 py-0.5 text-xs">0</span>
        </button>
        <button class="tab-btn flex flex-1 items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2 hover:text-ink" data-tab="past">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20V10"/>
                <path d="M18 20V4"/>
                <path d="M6 20v-4"/>
            </svg>
            <span>Trecut</span>
            <span id="tabCountPast" class="rounded-full bg-ink/10 px-2 py-0.5 text-xs">0</span>
        </button>
        <button class="tab-btn flex flex-1 items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2 hover:text-ink" data-tab="about">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span>Despre</span>
        </button>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_360px]">
        <!-- Main Content -->
        <div>
            <!-- Tab: Upcoming Activities -->
            <section id="tabContentEvents" class="tab-content">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="flex items-center gap-2.5 font-display text-xl font-bold">
                        <svg class="h-[22px] w-[22px] text-vermilion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Activități viitoare
                    </h2>
                </div>

                <div id="eventsGrid" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="overflow-hidden rounded-2xl border-2 border-ink/15 bg-paper">
                        <div class="relative aspect-video animate-pulse bg-ink/10">
                            <div class="absolute left-3 top-3 h-14 w-12 rounded-lg bg-paper"></div>
                        </div>
                        <div class="p-5">
                            <div class="mb-2 h-3 w-16 animate-pulse rounded bg-ink/10"></div>
                            <div class="mb-3 h-5 w-3/4 animate-pulse rounded bg-ink/10"></div>
                            <div class="mb-4 h-4 w-1/2 animate-pulse rounded bg-ink/5"></div>
                            <div class="flex justify-between border-t border-ink/10 pt-4">
                                <div class="h-5 w-20 animate-pulse rounded bg-ink/10"></div>
                                <div class="h-9 w-20 animate-pulse rounded-lg bg-ink/10"></div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </section>

            <!-- Tab: Past Activities -->
            <section id="tabContentPast" class="tab-content hidden">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="flex items-center gap-2.5 font-display text-xl font-bold">
                        <svg class="h-[22px] w-[22px] text-vermilion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20V10"/>
                            <path d="M18 20V4"/>
                            <path d="M6 20v-4"/>
                        </svg>
                        Activități trecute
                    </h2>
                </div>

                <div id="pastEventsGrid" class="flex flex-col gap-3">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="flex items-center gap-4 rounded-2xl border-2 border-ink/15 bg-paper p-4">
                        <div class="h-20 w-20 flex-shrink-0 animate-pulse rounded-xl bg-ink/10"></div>
                        <div class="flex-1">
                            <div class="mb-1 h-3 w-24 animate-pulse rounded bg-ink/10"></div>
                            <div class="mb-2 h-5 w-48 animate-pulse rounded bg-ink/10"></div>
                            <div class="h-4 w-36 animate-pulse rounded bg-ink/5"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </section>

            <!-- Tab: About -->
            <section id="tabContentAbout" class="tab-content hidden">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="flex items-center gap-2.5 font-display text-xl font-bold">
                        <svg class="h-[22px] w-[22px] text-vermilion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        Despre organizator
                    </h2>
                </div>

                <div class="mb-6 rounded-2xl border-2 border-ink/15 bg-paper p-6">
                    <p id="aboutTextFull" class="text-base leading-relaxed text-ink-soft">
                        <span class="mb-2 block h-4 w-full animate-pulse rounded bg-ink/10"></span>
                        <span class="mb-2 block h-4 w-5/6 animate-pulse rounded bg-ink/10"></span>
                        <span class="mb-2 block h-4 w-4/5 animate-pulse rounded bg-ink/10"></span>
                        <span class="block h-4 w-3/4 animate-pulse rounded bg-ink/10"></span>
                    </p>
                </div>

                <div id="quickFactsFull" class="mb-6 rounded-2xl border-2 border-ink/15 bg-paper p-6">
                    <h3 class="mb-5 font-display text-base font-bold">Informații despre organizator</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="flex items-center gap-3.5 rounded-xl bg-paper-2 p-4">
                            <div class="h-12 w-12 animate-pulse rounded-lg bg-ink/10"></div>
                            <div class="flex-1">
                                <div class="mb-1 h-3 w-16 animate-pulse rounded bg-ink/10"></div>
                                <div class="h-4 w-24 animate-pulse rounded bg-ink/5"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>
        </div>

        <!-- Sidebar -->
        <aside class="flex flex-col gap-6">
            <!-- About -->
            <div class="rounded-2xl border-2 border-ink/15 bg-paper p-6">
                <h3 class="mb-4 flex items-center gap-2 font-display text-base font-bold">
                    <svg class="h-5 w-5 text-vermilion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Despre organizator
                </h3>
                <p id="aboutText" class="text-sm leading-relaxed text-ink-soft">
                    <span class="mb-2 block h-4 w-full animate-pulse rounded bg-ink/10"></span>
                    <span class="mb-2 block h-4 w-5/6 animate-pulse rounded bg-ink/10"></span>
                    <span class="block h-4 w-4/5 animate-pulse rounded bg-ink/10"></span>
                </p>
            </div>

            <!-- Quick Facts -->
            <div id="quickFacts" class="rounded-2xl border-2 border-ink/15 bg-paper p-6">
                <h3 class="mb-4 font-display text-base font-bold">Informații rapide</h3>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="flex items-center gap-3.5 border-b border-ink/10 py-3 last:border-0">
                        <div class="h-10 w-10 animate-pulse rounded-lg bg-ink/10"></div>
                        <div class="flex-1">
                            <div class="mb-1 h-3 w-16 animate-pulse rounded bg-ink/10"></div>
                            <div class="h-4 w-24 animate-pulse rounded bg-ink/5"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Trust card (F6) -->
            <div class="rounded-2xl border-2 border-ink/15 bg-paper p-6">
                <h3 class="mb-4 font-display text-base font-bold">De ce bilete.online</h3>
                <ul class="space-y-2.5 text-sm font-bold">
                    <li class="flex items-center gap-2.5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-mint text-forest">✓</span>Confirmare instant</li>
                    <li class="flex items-center gap-2.5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-mint text-forest">⌗</span>Bilet digital cu cod QR</li>
                    <li class="flex items-center gap-2.5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-mint text-forest">🔒</span>Plată securizată</li>
                    <li class="flex items-center gap-2.5"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-mint text-forest">★</span>Puncte bonus la fiecare comandă</li>
                </ul>
            </div>

            <!-- Contact Card -->
            <div class="rounded-2xl border-2 border-ink bg-ink p-6 text-paper">
                <h3 class="mb-2 font-display text-base font-bold">Interesat de colaborare?</h3>
                <p class="mb-5 text-sm text-paper/85">Contactează organizatorul pentru activități private sau corporate.</p>
                <button onclick="var m=document.getElementById('contactModal');m.style.display='flex'" class="mb-3 flex w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-vermilion py-3.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Trimite mesaj
                </button>
                <a id="contactWebsite" href="#" target="_blank" class="hidden w-full items-center justify-center gap-2 rounded-full border-2 border-paper/30 py-3.5 text-sm font-bold text-paper transition hover:bg-paper/10">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    Website
                </a>
            </div>
        </aside>
    </div>
</main>

<!-- Contact Modal -->
<div id="contactModal" class="fixed inset-0 z-50 items-center justify-center bg-ink/50 backdrop-blur-sm" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="mx-4 w-full max-w-md rounded-2xl border-2 border-ink bg-paper shadow-2xl">
        <div class="flex items-center justify-between border-b-2 border-dashed border-ink/15 p-5">
            <h3 class="font-display text-lg font-bold">Trimite mesaj</h3>
            <button onclick="document.getElementById('contactModal').style.display='none'" class="p-1 text-ink-soft transition hover:text-ink">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="contactForm" class="space-y-4 p-5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink-soft">Prenume *</label>
                    <input type="text" name="first_name" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Prenumele tău">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-bold text-ink-soft">Nume *</label>
                    <input type="text" name="last_name" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Numele tău">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold text-ink-soft">Email *</label>
                <input type="email" name="email" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="email@exemplu.ro">
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold text-ink-soft">Telefon</label>
                <input type="tel" name="phone" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="07xx xxx xxx">
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold text-ink-soft">Mesaj *</label>
                <textarea name="message" required rows="4" class="w-full resize-none rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="Scrie mesajul tău aici..."></textarea>
            </div>
            <div id="contactFormMessage" class="hidden rounded-lg px-4 py-3 text-sm"></div>
            <button type="submit" id="contactSubmitBtn" class="w-full rounded-full bg-vermilion py-3 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                Trimite mesajul
            </button>
        </form>
    </div>
</div>

<script>
const OrganizerPage = {
    init() {
        this.loadOrganizerData();
        this.initTabs();
    },

    getSlug() {
        const params = new URLSearchParams(window.location.search);
        const fromQuery = params.get('slug');
        if (fromQuery) return fromQuery;
        return window.location.pathname.split('/').filter(Boolean).pop();
    },

    async loadOrganizerData() {
        try {
            const slug = this.getSlug();
            const response = await BileteOnlineAPI.get('/marketplace-events/organizers/' + slug);
            if (response.success && response.data) {
                this.renderOrganizer(response.data);
                return;
            }
        } catch (e) {
            console.error('Failed to load organizer data:', e);
        }
        this.renderEmptyState();
    },

    renderEmptyState() {
        document.getElementById('profileCard').innerHTML = `
            <div class="col-span-full py-12 text-center">
                <p class="text-lg font-bold text-ink-soft">Datele organizatorului nu sunt disponibile momentan.</p>
            </div>
        `;
        document.getElementById('eventsGrid').innerHTML = '<p class="col-span-2 py-8 text-center text-ink-soft">Nu sunt activități disponibile.</p>';
        document.getElementById('pastEventsGrid').innerHTML = '';
    },

    renderOrganizer(data) {
        document.title = data.name + ' — bilete.online';

        if (data.cover_image) {
            document.getElementById('heroImage').innerHTML = `<img src="${data.cover_image}" alt="${data.name}" class="absolute inset-0 h-full w-full object-cover object-center">`;
        } else if (data.upcomingEvents && data.upcomingEvents.length > 0 && data.upcomingEvents[0].image) {
            document.getElementById('heroImage').innerHTML = `<img src="${data.upcomingEvents[0].image}" alt="${data.name}" class="absolute inset-0 h-full w-full object-cover object-center">`;
        }

        document.getElementById('profileCard').innerHTML = `
            <div class="h-[140px] w-[140px] flex-shrink-0 overflow-hidden rounded-2xl border-4 border-paper shadow-lg ${data.avatar ? '' : 'flex items-center justify-center bg-vermilion'}">
                ${data.avatar
                    ? `<img src="${data.avatar}" alt="${data.name}" class="h-full w-full object-cover">`
                    : `<span class="text-5xl font-bold text-paper">${data.name.charAt(0).toUpperCase()}</span>`
                }
            </div>
            <div class="flex-1">
                <div class="mb-3 flex gap-2">
                    ${data.verified ? `
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky/15 px-3 py-1.5 text-xs font-bold text-sky">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                            Verificat
                        </span>
                    ` : ''}
                    ${data.pro ? `<span class="rounded-full bg-vermilion px-3 py-1.5 text-xs font-bold text-paper">PRO</span>` : ''}
                </div>
                <h1 class="mb-2 font-display text-[32px] font-bold">${data.name}</h1>
                ${data.tagline ? `<p class="mb-4 text-base text-ink-soft">${data.tagline}</p>` : ''}
                <div class="mb-5 flex items-center gap-1.5 text-sm text-ink-soft">
                    <svg class="h-[18px] w-[18px] text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    ${data.location || 'România'}
                </div>
                <div class="flex gap-8">
                    ${data.stats.followers !== '0' ? `
                    <div class="text-center">
                        <div class="font-display text-2xl font-bold">${data.stats.followers}</div>
                        <div class="text-[13px] text-ink-soft">Urmăritori</div>
                    </div>` : ''}
                    ${data.stats.rating !== '-' ? `
                    <div class="text-center">
                        <div class="font-display text-2xl font-bold">${data.stats.rating}</div>
                        <div class="text-[13px] text-ink-soft">Rating</div>
                    </div>` : ''}
                </div>
            </div>
            <div class="flex w-full flex-col items-center gap-3 md:w-auto md:items-end">
                <div class="flex gap-2">
                    ${data.social.facebook ? `
                    <a href="${data.social.facebook}" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-lg bg-paper-2 text-ink-soft transition hover:bg-ink hover:text-paper">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>` : ''}
                    ${data.social.instagram ? `
                    <a href="${data.social.instagram}" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-lg bg-paper-2 text-ink-soft transition hover:bg-ink hover:text-paper">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>` : ''}
                    ${data.social.website ? `
                    <a href="${data.social.website}" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-lg bg-paper-2 text-ink-soft transition hover:bg-ink hover:text-paper">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                    </a>` : ''}
                </div>
            </div>
        `;

        // Upcoming Activities — shared vertical card component (3 per row)
        if (data.upcomingEvents && data.upcomingEvents.length > 0) {
            document.getElementById('eventsGrid').innerHTML = BileteOnlineEventCard.renderMany(data.upcomingEvents, { columns: 3 });
        } else {
            document.getElementById('eventsGrid').innerHTML = '<p class="col-span-3 py-8 text-center text-ink-soft">Nu sunt activități viitoare momentan.</p>';
        }

        // Past Activities — horizontal cards, no link
        if (data.pastEvents && data.pastEvents.length > 0) {
            document.getElementById('pastEventsGrid').innerHTML = BileteOnlineEventCard.renderManyHorizontal(data.pastEvents, { showBuyButton: false, showPrice: false, showTime: false });
        } else {
            document.getElementById('pastEventsGrid').innerHTML = '<p class="py-8 text-center text-ink-soft">Nu sunt activități trecute.</p>';
        }

        const upCount = data.upcomingEvents?.length || 0;
        const pastCount = data.pastEvents?.length || 0;
        document.getElementById('tabCountEvents').textContent = upCount;
        document.getElementById('tabCountPast').textContent = pastCount;

        document.getElementById('aboutText').textContent = data.about || 'Informații indisponibile momentan.';
        document.getElementById('aboutTextFull').textContent = data.about || 'Informații indisponibile momentan.';

        const iconMap = {
            calendar: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            location: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            star: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            shield: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
        };
        const iconMapLg = {
            calendar: '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            location: '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            star: '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            shield: '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
        };

        if (data.facts && data.facts.length > 0) {
            document.getElementById('quickFacts').innerHTML = `
                <h3 class="mb-4 font-display text-base font-bold">Informații rapide</h3>
                ${data.facts.map(fact => `
                    <div class="flex items-center gap-3.5 border-b border-ink/10 py-3 last:border-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-paper-2 text-ink-soft">
                            ${iconMap[fact.icon] || ''}
                        </div>
                        <div class="flex-1">
                            <div class="mb-0.5 text-xs text-ink-soft">${fact.label}</div>
                            <div class="text-sm font-bold">${fact.value}</div>
                        </div>
                    </div>
                `).join('')}
            `;

            document.getElementById('quickFactsFull').innerHTML = `
                <h3 class="mb-5 font-display text-base font-bold">Informații despre organizator</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    ${data.facts.map(fact => `
                        <div class="flex items-center gap-3.5 rounded-xl bg-paper-2 p-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-vermilion/10 text-vermilion">
                                ${iconMapLg[fact.icon] || ''}
                            </div>
                            <div class="flex-1">
                                <div class="mb-0.5 text-xs text-ink-soft">${fact.label}</div>
                                <div class="text-sm font-bold">${fact.value}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        document.getElementById('tabCountEvents').textContent = data.upcomingEvents ? data.upcomingEvents.length : 0;
        document.getElementById('tabCountPast').textContent = data.pastEvents ? data.pastEvents.length : 0;

        if (data.social && data.social.website) {
            var websiteBtn = document.getElementById('contactWebsite');
            websiteBtn.href = data.social.website;
            websiteBtn.classList.remove('hidden');
            websiteBtn.classList.add('flex');
        }
    },

    initTabs() {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                tabs.forEach(t => {
                    t.classList.remove('bg-vermilion', 'text-paper');
                    t.classList.add('text-ink-soft');
                    const badge = t.querySelector('span[id^="tabCount"]');
                    if (badge) {
                        badge.classList.remove('bg-paper/20');
                        badge.classList.add('bg-ink/10');
                    }
                });
                tab.classList.remove('text-ink-soft');
                tab.classList.add('bg-vermilion', 'text-paper');
                const activeBadge = tab.querySelector('span[id^="tabCount"]');
                if (activeBadge) {
                    activeBadge.classList.remove('bg-ink/10');
                    activeBadge.classList.add('bg-paper/20');
                }

                contents.forEach(content => content.classList.add('hidden'));
                const targetContent = document.getElementById(`tabContent${targetTab.charAt(0).toUpperCase() + targetTab.slice(1)}`);
                if (targetContent) {
                    targetContent.classList.remove('hidden');
                }
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    OrganizerPage.init();

    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('contactSubmitBtn');
            const msg = document.getElementById('contactFormMessage');
            const slug = OrganizerPage.getSlug();

            btn.disabled = true;
            btn.textContent = 'Se trimite...';
            msg.className = 'hidden';

            try {
                const fd = new FormData(contactForm);
                const payload = Object.fromEntries(fd.entries());
                await BileteOnlineAPI.post('/marketplace-events/organizers/' + slug + '/contact', payload);

                msg.textContent = 'Mesajul a fost trimis cu succes! Organizatorul va reveni cu un răspuns.';
                msg.className = 'rounded-lg bg-forest/10 px-4 py-3 text-sm text-forest';
                contactForm.reset();
                setTimeout(() => { document.getElementById('contactModal').style.display = 'none'; msg.className = 'hidden'; }, 3000);
            } catch (err) {
                msg.textContent = err.message || 'A apărut o eroare. Încearcă din nou.';
                msg.className = 'rounded-lg bg-vermilion/10 px-4 py-3 text-sm text-vermilion';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Trimite mesajul';
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
