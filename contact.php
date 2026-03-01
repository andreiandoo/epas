<?php
/**
 * Contact Page - Ambilet Marketplace
 * Contact form, contact options, and FAQ section
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Contact — Ambilet";
$pageDescription = "Contactează-ne pentru întrebări despre bilete, evenimente sau colaborări. Suntem aici să te ajutăm.";
$bodyClass = 'page-contact';
$transparentHeader = false;

// Include head
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="bg-gradient-to-br from-primary to-[#7f1627] py-16 text-center relative overflow-hidden pt-40 mobile:pt-24">
    <div class="absolute -top-24 -right-24 w-[400px] h-[400px] bg-gradient-radial from-white/10 to-transparent rounded-full"></div>
    <div class="max-w-[700px] mx-auto px-6 relative z-10">
        <h1 class="text-[42px] font-extrabold text-white mb-4">Contactează-ne</h1>
        <p class="text-lg leading-relaxed text-white/85">Suntem aici să te ajutăm. Alege metoda preferată de contact sau trimite-ne un mesaj direct.</p>
    </div>
</section>

<!-- Main Content -->
<main class="px-6 py-12 mx-auto max-w-7xl">
    <!-- Contact Options -->
    <div class="relative z-10 grid grid-cols-1 gap-5 mb-12 -mt-20 sm:grid-cols-2 lg:grid-cols-4">
        <a href="mailto:support@ambilet.ro" class="text-center transition-all bg-white border border-gray-200 shadow-lg rounded-2xl p-7 hover:-translate-y-1 hover:shadow-xl hover:border-primary group">
            <div class="w-14 h-14 bg-gradient-to-br from-primary to-primary-light rounded-[14px] flex items-center justify-center mx-auto mb-4 text-white">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1.5">Email</h3>
            <p class="mb-3 text-sm text-gray-500">Răspundem în 24h</p>
            <span class="text-sm font-semibold text-primary">support@ambilet.ro</span>
        </a>

        <a href="tel:+40312345678" class="text-center transition-all bg-white border border-gray-200 shadow-lg rounded-2xl p-7 hover:-translate-y-1 hover:shadow-xl hover:border-primary group">
            <div class="w-14 h-14 bg-gradient-to-br from-primary to-primary-light rounded-[14px] flex items-center justify-center mx-auto mb-4 text-white">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1.5">Telefon</h3>
            <p class="mb-3 text-sm text-gray-500">Luni - Vineri, 9-18</p>
            <span class="text-sm font-semibold text-primary">+40 31 234 5678</span>
        </a>

        <a href="#" class="text-center transition-all bg-white border border-gray-200 shadow-lg rounded-2xl p-7 hover:-translate-y-1 hover:shadow-xl hover:border-primary group">
            <div class="w-14 h-14 bg-gradient-to-br from-primary to-primary-light rounded-[14px] flex items-center justify-center mx-auto mb-4 text-white">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1.5">Live Chat</h3>
            <p class="mb-3 text-sm text-gray-500">Disponibil acum</p>
            <span class="text-sm font-semibold text-primary">Începe conversația →</span>
        </a>

        <a href="/ajutor" class="text-center transition-all bg-white border border-gray-200 shadow-lg rounded-2xl p-7 hover:-translate-y-1 hover:shadow-xl hover:border-primary group">
            <div class="w-14 h-14 bg-gradient-to-br from-primary to-primary-light rounded-[14px] flex items-center justify-center mx-auto mb-4 text-white">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1.5">Centru de ajutor</h3>
            <p class="mb-3 text-sm text-gray-500">Răspunsuri rapide</p>
            <span class="text-sm font-semibold text-primary">Vizitează FAQ →</span>
        </a>
    </div>

    <!-- Contact Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_420px] gap-8">
        <!-- Contact Form -->
        <div class="bg-white rounded-[20px] p-10 border border-gray-200">
            <h2 class="mb-2 text-2xl font-bold text-gray-900">Trimite-ne un mesaj</h2>
            <p class="text-[15px] text-gray-500 mb-8">Completează formularul de mai jos și te vom contacta în cel mai scurt timp.</p>

            <form id="contactForm">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="form-group">
                        <label class="block mb-2 text-sm font-semibold text-gray-900">Nume <span class="text-primary">*</span></label>
                        <input type="text" name="lastName" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all" placeholder="Numele tău" required>
                    </div>
                    <div class="form-group">
                        <label class="block mb-2 text-sm font-semibold text-gray-900">Prenume <span class="text-primary">*</span></label>
                        <input type="text" name="firstName" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all" placeholder="Prenumele tău" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 mt-6 md:grid-cols-2">
                    <div class="form-group">
                        <label class="block mb-2 text-sm font-semibold text-gray-900">Email <span class="text-primary">*</span></label>
                        <input type="email" name="email" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all" placeholder="email@exemplu.ro" required>
                    </div>
                    <div class="form-group">
                        <label class="block mb-2 text-sm font-semibold text-gray-900">Telefon</label>
                        <input type="tel" name="phone" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all" placeholder="+40 7XX XXX XXX">
                    </div>
                </div>

                <div class="mt-6 form-group">
                    <label class="block mb-2 text-sm font-semibold text-gray-900">Subiect <span class="text-primary">*</span></label>
                    <select name="subject" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[center_right_16px] cursor-pointer" required>
                        <option value="">Selectează un subiect</option>
                        <option value="bilete">Întrebări despre bilete</option>
                        <option value="plati">Probleme cu plata</option>
                        <option value="rambursare">Solicitare rambursare</option>
                        <option value="cont">Probleme cu contul</option>
                        <option value="organizator">Devino organizator</option>
                        <option value="parteneriat">Propunere parteneriat</option>
                        <option value="altele">Altele</option>
                    </select>
                </div>

                <div class="mt-6 form-group">
                    <label class="block mb-2 text-sm font-semibold text-gray-900">Număr comandă (dacă este cazul)</label>
                    <input type="text" name="orderId" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all" placeholder="Ex: AMB-2024-123456">
                </div>

                <div class="mt-6 form-group">
                    <label class="block mb-2 text-sm font-semibold text-gray-900">Mesaj <span class="text-primary">*</span></label>
                    <textarea name="message" rows="5" class="w-full px-[18px] py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 focus:outline-none focus:border-primary focus:bg-white focus:ring-[3px] focus:ring-primary/10 transition-all resize-y min-h-[140px]" placeholder="Descrie problema sau întrebarea ta în detaliu..." required></textarea>
                </div>

                <div class="flex items-start gap-3 mt-6">
                    <input type="checkbox" name="privacy" id="privacy" class="w-5 h-5 border-2 border-gray-200 rounded-md cursor-pointer accent-primary flex-shrink-0 mt-0.5" required>
                    <label for="privacy" class="text-sm leading-relaxed text-gray-500">
                        Sunt de acord cu <a href="/confidentialitate" class="text-primary">Politica de confidențialitate</a> și înțeleg că datele mele vor fi procesate pentru a răspunde solicitării mele.
                    </label>
                </div>

                <button type="submit" class="w-full mt-6 px-8 py-4 bg-gradient-to-r from-primary to-primary-light rounded-xl text-white text-base font-semibold flex items-center justify-center gap-2.5 shadow-lg shadow-primary/25 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/35 transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Trimite mesajul
                </button>
            </form>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-6">
            <!-- Contact Info -->
            <div class="bg-white border border-gray-200 rounded-2xl p-7">
                <h3 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2.5">
                    <svg class="w-[22px] h-[22px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Informații de contact
                </h3>

                <div class="space-y-0">
                    <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100">
                        <div class="flex items-center justify-center flex-shrink-0 text-gray-500 w-11 h-11 bg-gray-50 rounded-xl">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <div>
                            <div class="mb-1 text-xs tracking-wide text-gray-400 uppercase">Email general</div>
                            <a href="mailto:contact@ambilet.ro" class="text-[15px] font-semibold text-primary">contact@ambilet.ro</a>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100">
                        <div class="flex items-center justify-center flex-shrink-0 text-gray-500 w-11 h-11 bg-gray-50 rounded-xl">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/>
                            </svg>
                        </div>
                        <div>
                            <div class="mb-1 text-xs tracking-wide text-gray-400 uppercase">Telefon</div>
                            <a href="tel:+40312345678" class="text-[15px] font-semibold text-primary">+40 31 234 5678</a>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100">
                        <div class="flex items-center justify-center flex-shrink-0 text-gray-500 w-11 h-11 bg-gray-50 rounded-xl">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div>
                            <div class="mb-1 text-xs tracking-wide text-gray-400 uppercase">Pentru organizatori</div>
                            <a href="mailto:organizatori@ambilet.ro" class="text-[15px] font-semibold text-primary">organizatori@ambilet.ro</a>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 py-3.5">
                        <div class="flex items-center justify-center flex-shrink-0 text-gray-500 w-11 h-11 bg-gray-50 rounded-xl">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div>
                            <div class="mb-1 text-xs tracking-wide text-gray-400 uppercase">Presă</div>
                            <a href="mailto:press@ambilet.ro" class="text-[15px] font-semibold text-primary">press@ambilet.ro</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-700 rounded-2xl p-7">
                <h3 class="text-lg font-bold text-white mb-5 flex items-center gap-2.5">
                    <svg class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Program suport
                </h3>

                <div class="space-y-0">
                    <div class="flex justify-between py-3 border-b border-white/10">
                        <span class="text-sm text-white/90">Luni - Vineri</span>
                        <span class="text-sm font-semibold text-white">09:00 - 18:00</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-white/10">
                        <span class="text-sm text-white/90">Sâmbătă</span>
                        <span class="text-sm font-semibold text-white">10:00 - 14:00</span>
                    </div>
                    <div class="flex justify-between py-3">
                        <span class="text-sm text-white/90">Duminică</span>
                        <span class="text-sm font-semibold text-red-400">Închis</span>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-white/10 rounded-lg text-[13px] text-white/90 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    În zilele de eveniment, suportul este disponibil până la terminarea evenimentului.
                </div>
            </div>

            <!-- Map -->
            <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
                <div class="h-[180px] bg-gray-50 flex flex-col items-center justify-center gap-2.5 text-gray-400">
                    <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <p class="text-[13px]">Hartă interactivă</p>
                </div>
                <div class="p-5">
                    <p class="mb-4 text-sm leading-relaxed text-gray-600">
                        <strong>AMBILET SRL</strong><br>
                        Str. Exemplu nr. 123, Etaj 4<br>
                        Sector 1, București 010101<br>
                        România
                    </p>
                    <a href="https://maps.google.com" target="_blank" class="flex items-center justify-center w-full gap-2 py-3 text-sm font-semibold text-gray-600 transition-all border border-gray-200 rounded-lg bg-gray-50 hover:bg-primary hover:border-primary hover:text-white">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                        </svg>
                        Deschide în Google Maps
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <section class="mt-16">
        <div class="mb-10 text-center">
            <h2 class="text-[28px] font-bold text-gray-900 mb-3">Întrebări frecvente</h2>
            <p class="text-base text-gray-500">Răspunsuri rapide la cele mai comune întrebări</p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="bg-white rounded-[14px] p-6 border border-gray-200 hover:border-primary hover:shadow-md transition-all">
                <h3 class="text-base font-bold text-gray-900 mb-2.5 flex items-start gap-2.5">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Cum pot solicita o rambursare?
                </h3>
                <p class="text-sm leading-relaxed text-gray-500 pl-[30px]">Pentru a solicita o rambursare, accesează secțiunea "Comenzile mele" din cont, selectează comanda și apasă pe "Solicită rambursare". Verifică <a href="/termeni" class="text-primary">politica de rambursare</a> pentru detalii.</p>
            </div>

            <div class="bg-white rounded-[14px] p-6 border border-gray-200 hover:border-primary hover:shadow-md transition-all">
                <h3 class="text-base font-bold text-gray-900 mb-2.5 flex items-start gap-2.5">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Nu am primit biletul pe email. Ce fac?
                </h3>
                <p class="text-sm leading-relaxed text-gray-500 pl-[30px]">Verifică folderul Spam/Junk. Dacă nu găsești emailul, autentifică-te în cont și descarcă biletele din "Comenzile mele". Dacă problema persistă, contactează-ne.</p>
            </div>

            <div class="bg-white rounded-[14px] p-6 border border-gray-200 hover:border-primary hover:shadow-md transition-all">
                <h3 class="text-base font-bold text-gray-900 mb-2.5 flex items-start gap-2.5">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Pot transfera biletul altei persoane?
                </h3>
                <p class="text-sm leading-relaxed text-gray-500 pl-[30px]">Da, biletele pot fi transferate. Accesează comanda în cont și selectează opțiunea "Transferă bilet". Persoana va primi biletul pe emailul specificat.</p>
            </div>

            <div class="bg-white rounded-[14px] p-6 border border-gray-200 hover:border-primary hover:shadow-md transition-all">
                <h3 class="text-base font-bold text-gray-900 mb-2.5 flex items-start gap-2.5">
                    <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Cum devin organizator pe Ambilet?
                </h3>
                <p class="text-sm leading-relaxed text-gray-500 pl-[30px]">Pentru a deveni organizator, creează un cont și accesează <a href="/organizator/register" class="text-primary">"Devino organizator"</a>. Completează formularul și echipa noastră te va contacta pentru verificare.</p>
            </div>
        </div>
    </section>
</main>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';

// Page-specific scripts
$scriptsExtra = <<<'SCRIPTS'
<script>
const ContactPage = {
    init() {
        this.initForm();
    },

    initForm() {
        const form = document.getElementById('contactForm');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Validate
            if (!data.lastName || !data.firstName || !data.email || !data.subject || !data.message) {
                AmbiletUtils.showToast('Te rugăm să completezi toate câmpurile obligatorii', 'error');
                return;
            }

            if (!form.querySelector('[name="privacy"]').checked) {
                AmbiletUtils.showToast('Te rugăm să accepți politica de confidențialitate', 'error');
                return;
            }

            // Submit
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                </svg>
                Se trimite...
            `;
            submitBtn.disabled = true;

            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1500));

            // Success
            AmbiletUtils.showToast('Mesajul a fost trimis cu succes! Te vom contacta în curând.', 'success');
            form.reset();
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
};

document.addEventListener('DOMContentLoaded', () => ContactPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
