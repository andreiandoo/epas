<?php
/**
 * TICS.ro - Privacy Policy
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Politica de confidențialitate';
$pageDescription = 'Află cum colectăm și protejăm datele tale personale pe TICS.ro.';

$headExtra = <<<HTML
<style>
    html { scroll-behavior: smooth; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; }
    .toc-link { transition: all 0.2s ease; }
    .toc-link:hover { color: #4f46e5; padding-left: 8px; }
    .content-section { scroll-margin-top: 100px; }
</style>
HTML;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div class="bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 py-16">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full text-white text-sm mb-4 animate-fadeInUp">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            GDPR Compliant
        </div>
        <h1 class="text-4xl font-bold text-white mb-3 animate-fadeInUp" style="animation-delay: 0.1s">Politica de confidențialitate</h1>
        <p class="text-white/80 text-lg animate-fadeInUp" style="animation-delay: 0.2s">Ultima actualizare: 15 Ianuarie <?= date('Y') ?></p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 lg:px-8 py-12">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- TOC Sidebar -->
        <aside class="lg:w-64 flex-shrink-0">
            <div class="lg:sticky lg:top-24">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-4">Cuprins</h3>
                    <nav class="space-y-2">
                        <a href="#introducere" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">1. Introducere</a>
                        <a href="#date-colectate" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">2. Date colectate</a>
                        <a href="#utilizare" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">3. Utilizarea datelor</a>
                        <a href="#partajare" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">4. Partajarea datelor</a>
                        <a href="#cookies" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">5. Cookies</a>
                        <a href="#drepturi" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">6. Drepturile tale</a>
                        <a href="#securitate" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">7. Securitate</a>
                        <a href="#contact" class="toc-link block text-sm text-gray-600 py-1 border-l-2 border-transparent pl-3">8. Contact</a>
                    </nav>
                </div>

                <div class="mt-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-5">
                    <h4 class="font-semibold text-gray-900 mb-3">Acțiuni rapide</h4>
                    <div class="space-y-2">
                        <a href="#" class="flex items-center gap-2 text-sm text-indigo-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarcă datele mele
                        </a>
                        <a href="#" class="flex items-center gap-2 text-sm text-indigo-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Șterge contul
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 min-w-0">
            <div class="bg-white rounded-2xl border border-gray-200 p-8 lg:p-10">
                <section id="introducere" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Introducere</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Bine ai venit la TICS.ro. Această Politică de Confidențialitate explică modul în care colectăm, utilizăm, stocăm și protejăm informațiile dumneavoastră personale atunci când utilizați platforma noastră de ticketing.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Ne angajăm să protejăm confidențialitatea și securitatea datelor dumneavoastră în conformitate cu Regulamentul General privind Protecția Datelor (GDPR) și legislația românească în vigoare.
                    </p>
                </section>

                <section id="date-colectate" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">2. Datele pe care le colectăm</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">Colectăm următoarele categorii de date personale:</p>
                    <div class="space-y-4">
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h4 class="font-semibold text-gray-900 mb-2">📋 Date de identificare</h4>
                            <p class="text-sm text-gray-600">Nume, prenume, adresă de email, număr de telefon, adresă de facturare</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h4 class="font-semibold text-gray-900 mb-2">💳 Date de plată</h4>
                            <p class="text-sm text-gray-600">Informații despre cardul de plată (procesate securizat prin Stripe/PayU), istoricul tranzacțiilor</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h4 class="font-semibold text-gray-900 mb-2">📊 Date de utilizare</h4>
                            <p class="text-sm text-gray-600">Adresa IP, tipul de browser, paginile vizitate, timpul petrecut pe platformă</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h4 class="font-semibold text-gray-900 mb-2">🎫 Date despre achiziții</h4>
                            <p class="text-sm text-gray-600">Biletele achiziționate, evenimentele la care ați participat, istoricul comenzilor</p>
                        </div>
                    </div>
                </section>

                <section id="utilizare" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Cum utilizăm datele</h2>
                    <ul class="space-y-3 text-gray-600">
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span>Procesarea și livrarea biletelor achiziționate</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span>Comunicarea cu dumneavoastră despre comenzi și evenimente</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span>Personalizarea recomandărilor de evenimente</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span>Îmbunătățirea serviciilor noastre</span>
                        </li>
                    </ul>
                </section>

                <section id="partajare" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Partajarea datelor</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">Nu vindem datele dumneavoastră personale către terți. Putem partaja date cu:</p>
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold text-gray-900">Partener</th>
                                    <th class="text-left px-4 py-3 font-semibold text-gray-900">Scop</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr><td class="px-4 py-3 text-gray-700">Organizatori</td><td class="px-4 py-3 text-gray-600">Validarea accesului</td></tr>
                                <tr><td class="px-4 py-3 text-gray-700">Procesatori de plăți</td><td class="px-4 py-3 text-gray-600">Tranzacții securizate</td></tr>
                                <tr><td class="px-4 py-3 text-gray-700">Furnizori servicii</td><td class="px-4 py-3 text-gray-600">Hosting, email</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="cookies" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Cookie-uri</h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                                <h4 class="font-semibold text-gray-900">Esențiale</h4>
                            </div>
                            <p class="text-sm text-gray-600">Necesare pentru funcționare</p>
                        </div>
                        <div class="p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                                <h4 class="font-semibold text-gray-900">Funcționale</h4>
                            </div>
                            <p class="text-sm text-gray-600">Rețin preferințele</p>
                        </div>
                        <div class="p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 bg-amber-500 rounded-full"></span>
                                <h4 class="font-semibold text-gray-900">Analitice</h4>
                            </div>
                            <p class="text-sm text-gray-600">Statistici de utilizare</p>
                        </div>
                        <div class="p-4 border border-gray-200 rounded-xl">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 bg-purple-500 rounded-full"></span>
                                <h4 class="font-semibold text-gray-900">Marketing</h4>
                            </div>
                            <p class="text-sm text-gray-600">Reclame personalizate</p>
                        </div>
                    </div>
                </section>

                <section id="drepturi" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Drepturile dumneavoastră</h2>
                    <p class="text-gray-600 leading-relaxed mb-4">Conform GDPR, aveți următoarele drepturi:</p>
                    <div class="space-y-3">
                        <div class="flex items-start gap-3 p-4 bg-indigo-50 rounded-xl">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">Dreptul de acces</h4>
                                <p class="text-sm text-gray-600">Solicită o copie a datelor personale</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 bg-indigo-50 rounded-xl">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">Dreptul la rectificare</h4>
                                <p class="text-sm text-gray-600">Corectează datele inexacte</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-4 bg-indigo-50 rounded-xl">
                            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">Dreptul la ștergere</h4>
                                <p class="text-sm text-gray-600">„Dreptul de a fi uitat"</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="securitate" class="content-section mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Securitatea datelor</h2>
                    <div class="flex flex-wrap gap-3">
                        <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">🔒 Criptare SSL/TLS</span>
                        <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">🛡️ Firewall avansat</span>
                        <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">🔐 Autentificare 2FA</span>
                        <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">📊 Monitorizare 24/7</span>
                    </div>
                </section>

                <section id="contact" class="content-section">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Contact</h2>
                    <div class="bg-gray-50 rounded-xl p-5">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span class="text-gray-700">privacy@tics.ro</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-8 flex flex-wrap gap-4">
                <a href="/termeni" class="flex-1 min-w-[200px] p-5 bg-white rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all">
                    <h4 class="font-semibold text-gray-900 mb-1">Termeni și condiții →</h4>
                    <p class="text-sm text-gray-500">Citește regulile de utilizare</p>
                </a>
                <a href="/rambursari" class="flex-1 min-w-[200px] p-5 bg-white rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all">
                    <h4 class="font-semibold text-gray-900 mb-1">Politica de rambursare →</h4>
                    <p class="text-sm text-gray-500">Află despre returnări</p>
                </a>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
