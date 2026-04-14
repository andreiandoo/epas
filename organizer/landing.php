<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Devino Organizator';
$bodyClass = 'min-h-screen bg-white';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
?>
    <div id="header-container"></div>

    <section class="relative bg-gradient-to-br from-primary via-primary-dark to-secondary py-20 lg:py-32 overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-20 left-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-accent/10 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-white">
                    <span class="inline-block px-4 py-2 bg-white/20 backdrop-blur rounded-full text-sm font-medium mb-6">Platforma #1 pentru organizatori de evenimente</span>
                    <h1 class="text-4xl lg:text-5xl font-extrabold mb-6">Vinde bilete si creste-ti audienta cu <?= SITE_NAME ?></h1>
                    <p class="text-lg text-white/80 mb-8">Gestioneaza-ti evenimentele, vinde bilete online si primeste platile direct in contul tau bancar. Fara costuri ascunse, comision mic.</p>
                    <div class="flex flex-wrap gap-4">
                        <a href="/organizator/register" class="btn bg-white text-primary hover:bg-white/90 text-lg px-8 py-3">Incepe gratuit</a>
                        <a href="#features" class="btn bg-white/20 text-white hover:bg-white/30 text-lg px-8 py-3">Afla mai multe</a>
                    </div>
                    <div class="flex items-center gap-8 mt-8 text-white/80">
                        <div><p class="text-2xl font-bold text-white">500+</p><p class="text-sm">Organizatori</p></div>
                        <div><p class="text-2xl font-bold text-white">10,000+</p><p class="text-sm">Evenimente</p></div>
                        <div><p class="text-2xl font-bold text-white">1M+</p><p class="text-sm">Bilete vandute</p></div>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <div class="relative">
                        <div class="bg-white/10 backdrop-blur rounded-3xl p-8">
                            <div class="bg-white rounded-2xl shadow-2xl p-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-success/10 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div><p class="font-semibold text-secondary">Vanzare confirmata!</p><p class="text-sm text-muted">3 bilete VIP pentru Concert Rock</p></div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between"><span class="text-muted">Vanzari azi</span><span class="font-bold text-secondary">12,450 RON</span></div>
                                    <div class="flex justify-between"><span class="text-muted">Bilete vandute</span><span class="font-bold text-secondary">156</span></div>
                                    <div class="h-2 bg-surface rounded-full overflow-hidden"><div class="h-full bg-primary rounded-full" style="width: 75%"></div></div>
                                    <p class="text-sm text-muted">75% din capacitate vanduta</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="py-20 bg-surface">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl lg:text-4xl font-bold text-secondary mb-4">Tot ce ai nevoie pentru a vinde bilete</h2>
                <p class="text-lg text-muted max-w-2xl mx-auto">Instrumente puternice si usor de folosit pentru organizatori de orice marime</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4"><svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Vanzare online 24/7</h3>
                    <p class="text-muted">Vinde bilete non-stop, direct de pe website-ul tau sau de pe pagina <?= SITE_NAME ?>.</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center mb-4"><svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Rapoarte in timp real</h3>
                    <p class="text-muted">Monitorizeaza vanzarile si analizeaza datele direct din dashboard.</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="w-12 h-12 bg-accent/10 rounded-xl flex items-center justify-center mb-4"><svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg></div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Check-in rapid</h3>
                    <p class="text-muted">Scaneaza codurile QR cu aplicatia mobila pentru acces instant.</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4"><svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Plati rapide</h3>
                    <p class="text-muted">Primesti banii in cont in maxim 5 zile lucratoare dupa eveniment.</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center mb-4"><svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg></div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Comunicare automata</h3>
                    <p class="text-muted">Trimite emailuri automate participantilor cu confirmari si remindere.</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="w-12 h-12 bg-accent/10 rounded-xl flex items-center justify-center mb-4"><svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div>
                    <h3 class="text-xl font-bold text-secondary mb-2">Coduri promotionale</h3>
                    <p class="text-muted">Creeaza discounturi si oferte speciale pentru a creste vanzarile.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl lg:text-4xl font-bold text-secondary mb-4">Preturi transparente</h2>
                <p class="text-lg text-muted max-w-2xl mx-auto">Fara costuri fixe, fara surprize. Platesti doar cand vinzi.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h3 class="text-xl font-bold text-secondary mb-2">Starter</h3>
                    <p class="text-muted mb-6">Pentru organizatori mici</p>
                    <div class="mb-6"><span class="text-4xl font-bold text-secondary">3%</span><span class="text-muted">/ bilet</span></div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Evenimente nelimitate</li>
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Rapoarte de baza</li>
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Suport email</li>
                    </ul>
                    <a href="/organizator/register" class="btn btn-secondary w-full">Incepe gratuit</a>
                </div>
                <div class="bg-primary rounded-2xl p-8 text-white relative">
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2"><span class="bg-accent text-white text-sm font-bold px-4 py-1 rounded-full">Popular</span></div>
                    <h3 class="text-xl font-bold mb-2">Professional</h3>
                    <p class="text-white/80 mb-6">Pentru organizatori activi</p>
                    <div class="mb-6"><span class="text-4xl font-bold">2%</span><span class="text-white/80">/ bilet</span></div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-white/90"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Tot din Starter +</li>
                        <li class="flex items-center gap-2 text-white/90"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Rapoarte avansate</li>
                        <li class="flex items-center gap-2 text-white/90"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Coduri promotionale</li>
                        <li class="flex items-center gap-2 text-white/90"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Suport prioritar</li>
                    </ul>
                    <a href="/organizator/register" class="btn bg-white text-primary hover:bg-white/90 w-full">Incepe gratuit</a>
                </div>
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h3 class="text-xl font-bold text-secondary mb-2">Enterprise</h3>
                    <p class="text-muted mb-6">Pentru organizatori mari</p>
                    <div class="mb-6"><span class="text-4xl font-bold text-secondary">1%</span><span class="text-muted">/ bilet</span></div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Tot din Professional +</li>
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>API access</li>
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Account manager dedicat</li>
                        <li class="flex items-center gap-2 text-muted"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Integrari custom</li>
                    </ul>
                    <a href="mailto:enterprise@<?= strtolower(SITE_NAME) ?>.ro" class="btn btn-secondary w-full">Contacteaza-ne</a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-secondary">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-6">Gata sa incepi?</h2>
            <p class="text-lg text-white/80 mb-8">Inregistreaza-te gratuit si creeaza primul tau eveniment in mai putin de 5 minute.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="/organizator/register" class="btn bg-primary text-white hover:bg-primary-dark text-lg px-8 py-3">Creeaza cont gratuit</a>
                <a href="/organizator/login" class="btn bg-white/10 text-white hover:bg-white/20 text-lg px-8 py-3">Am deja cont</a>
            </div>
        </div>
    </section>

    <div id="footer"></div>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
