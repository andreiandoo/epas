<?php
require_once __DIR__ . '/includes/config.php';

$eventSlug = $_GET['slug'] ?? '';
$pageTitle = 'Eveniment';
$pageDescription = 'Detalii eveniment si cumparare bilete';
$bodyClass = 'bg-surface';

require_once __DIR__ . '/includes/head.php';
?>
    <style>
        .date-badge { background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%); }
        .btn-primary { background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(165, 28, 48, 0.3); }

        .ticket-card { transition: all 0.3s ease; }
        .ticket-card:hover { border-color: #A51C30; }
        .ticket-card.selected { border-color: #A51C30; background-color: rgba(165, 28, 48, 0.05); }

        .tooltip {
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            transform: translateY(5px);
        }
        .tooltip-trigger:hover .tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .event-card { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
        .event-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(165, 28, 48, 0.2); }
        .event-card:hover .event-image { transform: scale(1.08); }
        .event-image { transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }

        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .points-counter { animation: pointsPulse 0.3s ease; }
        @keyframes pointsPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }

        .sticky-cart { position: sticky; top: 88px; }

        .gallery-thumb { transition: all 0.2s ease; }
        .gallery-thumb:hover, .gallery-thumb.active { border-color: #A51C30; opacity: 1; }
    </style>

<?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Breadcrumb -->
    <div class="bg-white border-b border-border mt-28">
        <div class="px-4 py-3 mx-auto max-w-7xl">
            <nav class="flex items-center gap-2 text-sm" id="breadcrumb">
                <a href="/" class="transition-colors text-muted hover:text-primary">Acasa</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-medium text-secondary" id="breadcrumb-title">Se incarca...</span>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 py-8 mx-auto max-w-7xl" id="main-content">
        <!-- Loading State -->
        <div id="loading-state" class="flex flex-col gap-8 lg:flex-row">
            <div class="lg:w-2/3">
                <div class="mb-8 overflow-hidden bg-white border rounded-3xl border-border">
                    <div class="bg-gray-200 animate-pulse h-72 md:h-96"></div>
                    <div class="p-6 md:p-8">
                        <div class="w-3/4 h-10 mb-4 bg-gray-200 rounded animate-pulse"></div>
                        <div class="grid gap-4 mb-6 sm:grid-cols-2">
                            <div class="h-24 bg-gray-100 animate-pulse rounded-xl"></div>
                            <div class="h-24 bg-gray-100 animate-pulse rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:w-1/3">
                <div class="bg-gray-100 animate-pulse h-96 rounded-3xl"></div>
            </div>
        </div>

        <!-- Event Content (hidden until loaded) -->
        <div id="event-content" class="flex flex-col hidden gap-8 lg:flex-row">
            <!-- Left Column - Event Details -->
            <div class="lg:w-2/3">
                <!-- Event Header -->
                <div class="mb-8 overflow-hidden bg-white border rounded-3xl border-border">
                    <!-- Main Image -->
                    <div class="relative overflow-hidden h-72 md:h-96">
                        <img id="mainImage" src="" alt="" class="object-cover w-full h-full">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <div class="absolute flex gap-2 top-4 left-4" id="event-badges"></div>
                        <div class="absolute flex gap-2 top-4 left-4">
                            <span class="px-3 py-1.5 bg-accent text-white text-xs font-bold rounded-lg uppercase">Rock</span>
                            <span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">🔥 Popular</span>
                        </div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <div class="flex gap-2" id="gallery-thumbs"></div>
                        </div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <div class="flex gap-2">
                                <button onclick="changeImage(0)" class="w-16 h-12 overflow-hidden border-2 rounded-lg gallery-thumb active border-white/50 opacity-80">
                                    <img src="https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200" class="object-cover w-full h-full">
                                </button>
                                <button onclick="changeImage(1)" class="w-16 h-12 overflow-hidden border-2 rounded-lg gallery-thumb border-white/50 opacity-80">
                                    <img src="https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200" class="object-cover w-full h-full">
                                </button>
                                <button onclick="changeImage(2)" class="w-16 h-12 overflow-hidden border-2 rounded-lg gallery-thumb border-white/50 opacity-80">
                                    <img src="https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=200" class="object-cover w-full h-full">
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Event Info -->
                    <div class="p-6 md:p-8">
                        <h1 id="event-title" class="mb-4 text-3xl font-extrabold md:text-4xl text-secondary"></h1>

                        <!-- Key Details -->
                        <div class="grid gap-4 mb-6 sm:grid-cols-2">
                            <div class="flex items-start gap-3 p-4 bg-surface rounded-xl">
                                <div class="flex-shrink-0 px-3 py-2 text-center text-white date-badge rounded-xl">
                                    <span id="event-day" class="block text-xl font-bold leading-none">--</span>
                                    <span id="event-month" class="block text-[10px] uppercase tracking-wide mt-0.5">---</span>
                                </div>
                                <div>
                                    <p id="event-weekday" class="font-semibold text-secondary"></p>
                                    <p id="event-date-full" class="text-sm text-muted"></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-4 bg-surface rounded-xl">
                                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl">
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p id="event-time" class="font-semibold text-secondary"></p>
                                    <p id="event-doors" class="text-sm text-muted"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex items-center gap-3 p-4 mb-6 bg-surface rounded-xl">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl">
                                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="flex-1">
                                <p id="venue-name" class="font-semibold text-secondary"></p>
                                <p id="venue-address" class="text-sm text-muted"></p>
                            </div>
                            <a href="#venue" class="text-sm font-semibold text-primary hover:underline">Vezi locatia &rarr;</a>
                        </div>

                        <!-- Social Stats -->
                        <div class="flex flex-wrap gap-4">
                            <span class="flex items-center gap-2 text-sm text-muted">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <span id="event-interested">0 interesati</span>
                            </span>
                            <span class="flex items-center gap-2 text-sm text-muted">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span id="event-views">0 vizualizari</span>
                            </span>
                            <button class="flex items-center gap-2 text-sm transition-colors text-muted hover:text-primary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                <span>Distribuie</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Event Description -->
                <div class="p-6 mb-8 bg-white border rounded-3xl border-border md:p-8">
                    <h2 class="mb-4 text-xl font-bold text-secondary">Despre eveniment</h2>
                    <div id="event-description" class="prose prose-slate max-w-none text-muted"></div>
                    <div class="prose prose-slate max-w-none">
                        <p class="mb-4 leading-relaxed text-muted">
                            Pregătește-te pentru cel mai nebun concert de Crăciun! <strong>Dirty Shirt</strong>, formația care a revoluționat scena rock românească cu mixul lor unic de metal și muzică tradițională, te invită să petreci sărbătorile într-un mod cu totul special.
                        </p>
                        <p class="mb-4 leading-relaxed text-muted">
                            <strong>"Moș Crăciun e Rocker"</strong> este un show conceput special pentru această perioadă magică a anului, unde energia brută a rock-ului se îmbină cu spiritul sărbătorilor de iarnă. Vei auzi cele mai tari hituri ale trupei, dar și surprize speciale pregătite doar pentru această ocazie.
                        </p>
                        <p class="mb-4 leading-relaxed text-muted">
                            Fie că ești fan înrăit sau doar vrei să încerci ceva nou în această perioadă, concertul promite o experiență de neuitat. Adu-ți prietenii, familia (da, și bunicii pot veni!) și pregătește-te să cânți, să dansezi și să simți magia Crăciunului în cel mai rock mod posibil!
                        </p>
                        <div class="p-4 mt-6 bg-surface rounded-xl">
                            <h3 class="mb-2 font-semibold text-secondary">📋 Ce include biletul:</h3>
                            <ul class="space-y-1 text-sm text-muted">
                                <li>• Acces la concert (ambele zile pentru biletele VIP)</li>
                                <li>• Parcare gratuită</li>
                                <li>• Zonă food & drinks</li>
                                <li>• Photobooth tematic</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Artist Section -->
                <div class="p-6 mb-8 bg-white border rounded-3xl border-border md:p-8" id="artist-section" style="display:none;">
                    <h2 class="mb-6 text-xl font-bold text-secondary">Despre artist</h2>
                    <div id="artist-content"></div>
                    <div class="flex flex-col gap-6 md:flex-row">
                        <div class="md:w-1/3">
                            <img src="https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400" alt="Dirty Shirt" class="object-cover w-full aspect-square rounded-2xl">
                        </div>
                        <div class="md:w-2/3">
                            <div class="flex items-center gap-3 mb-4">
                                <h3 class="text-2xl font-bold text-secondary">Dirty Shirt</h3>
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-primary/10 text-primary">Verified</span>
                            </div>
                            <p class="mb-4 leading-relaxed text-muted">
                                Dirty Shirt este o formație rock/metal din Satu Mare, România, fondată în 2007. Sunt cunoscuți pentru stilul lor unic care combină heavy metal cu elemente de muzică tradițională românească și instrumentație folk.
                            </p>
                            <p class="mb-4 leading-relaxed text-muted">
                                Trupa a câștigat recunoaștere internațională, participând la festivaluri majore din întreaga Europă, inclusiv Wacken Open Air și Metaldays. Albumul lor "Dirtylicious" a fost aclamat de critici pentru originalitatea și energia debordantă.
                            </p>
                            <div class="flex flex-wrap gap-4 mt-4">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-secondary">17</p>
                                    <p class="text-xs text-muted">Ani de activitate</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-secondary">6</p>
                                    <p class="text-xs text-muted">Albume</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-secondary">500+</p>
                                    <p class="text-xs text-muted">Concerte</p>
                                </div>
                            </div>
                            <div class="flex gap-3 mt-6">
                                <a href="#" class="flex items-center justify-center w-10 h-10 transition-colors bg-surface rounded-xl hover:bg-primary hover:text-white">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </a>
                                <a href="#" class="flex items-center justify-center w-10 h-10 transition-colors bg-surface rounded-xl hover:bg-primary hover:text-white">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                </a>
                                <a href="#" class="flex items-center justify-center w-10 h-10 transition-colors bg-surface rounded-xl hover:bg-primary hover:text-white">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                                </a>
                                <a href="#" class="flex items-center justify-center w-10 h-10 transition-colors bg-surface rounded-xl hover:bg-primary hover:text-white">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Venue Section -->
                <div class="p-6 mb-8 bg-white border rounded-3xl border-border md:p-8" id="venue">
                    <h2 class="mb-6 text-xl font-bold text-secondary">Despre locatie</h2>
                    <div id="venue-content"></div>
                    <div class="flex flex-col gap-6 md:flex-row">
                        <div class="md:w-1/2">
                            <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600" alt="Grand Gala" class="object-cover w-full h-64 mb-4 rounded-2xl">
                            <div class="p-4 bg-surface rounded-xl">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2700.1234567890123!2d23.5678901234567!3d47.6543210987654!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDfCsDM5JzE1LjYiTiAyM8KwMzQnMDQuNCJF!5e0!3m2!1sro!2sro!4v1234567890123" width="100%" height="200" style="border:0; border-radius: 12px;" allowfullscreen="" loading="lazy"></iframe>
                            </div>
                        </div>
                        <div class="md:w-1/2">
                            <h3 class="mb-2 text-xl font-bold text-secondary">Grand Gala Event Hall</h3>
                            <p class="mb-4 text-muted">Str. Culturii Nr. 12, Baia Mare, Maramureș</p>
                            
                            <p class="mb-4 leading-relaxed text-muted">
                                Grand Gala Event Hall este una dintre cele mai moderne săli de evenimente din Maramureș, cu o capacitate de până la 1.500 de persoane. Sala dispune de un sistem de sunet profesional și iluminat de scenă de ultimă generație.
                            </p>
                            
                            <div class="mb-6 space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="text-sm text-secondary">Parcare gratuită (200 locuri)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="text-sm text-secondary">Accesibil pentru persoane cu dizabilități</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="text-sm text-secondary">Garderobă</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="text-sm text-secondary">Bar & Food Corner</span>
                                </div>
                            </div>

                            <a href="#" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                Deschide în Google Maps
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Ticket Selection -->
            <div class="lg:w-1/3">
                <div class="sticky-cart">
                    <div class="overflow-hidden bg-white border rounded-3xl border-border">
                        <div class="p-6 border-b border-border">
                            <h2 class="mb-2 text-xl font-bold text-secondary">Selecteaza bilete</h2>
                            <p class="text-sm text-muted">Alege tipul de bilet si cantitatea</p>
                        </div>

                        <!-- Ticket Types -->
                        <div class="p-6 space-y-4" id="ticket-types"></div>

                        <!-- Cart Summary -->
                        <div id="cartSummary" class="hidden border-t border-border">
                            <div class="p-6 bg-surface/50">
                                <!-- Points Earned -->
                                <div class="flex items-center justify-between p-3 mb-4 bg-accent/10 rounded-xl">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">🎁</span>
                                        <span class="text-sm font-medium text-secondary">Puncte castigate:</span>
                                    </div>
                                    <span id="pointsEarned" class="text-lg font-bold text-accent points-counter">0</span>
                                </div>

                                <!-- Summary -->
                                <div class="mb-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted">Subtotal:</span>
                                        <span id="subtotal" class="font-medium">0 lei</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted">Taxa Crucea Rosie (1%):</span>
                                        <span id="taxRedCross" class="font-medium">0 lei</span>
                                    </div>
                                    <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                                        <span>Total:</span>
                                        <span id="totalPrice" class="text-primary">0 lei</span>
                                    </div>
                                </div>

                                <button id="checkoutBtn" onclick="EventPage.addToCart()" class="flex items-center justify-center w-full gap-2 py-4 text-lg font-bold text-white btn-primary rounded-xl">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                    Cumpara bilete
                                </button>

                                <p class="mt-3 text-xs text-center text-muted">
                                    Plata securizata prin card sau transfer bancar
                                </p>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div id="emptyCart" class="p-6 text-center border-t border-border">
                            <p class="text-sm text-muted">Selecteaza cel putin un bilet pentru a continua</p>
                        </div>
                    </div>

                    <!-- Trust Badges -->
                    <div class="p-4 mt-4 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-center gap-6">
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Plata securizata
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Livrare instant
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Events -->
        <section class="mt-16" id="related-events-section" style="display:none;">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-secondary">Alte evenimente care ti-ar putea placea</h2>
                    <p class="mt-1 text-muted" id="related-category-text">Evenimente similare</p>
                </div>
                <a href="/genre/rock" class="items-center hidden gap-2 font-semibold md:flex text-primary hover:underline">
                    Vezi toate
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" id="related-events"></div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Event Card 1 -->
                <a href="#" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">
                    <div class="relative overflow-hidden h-44">
                        <img src="https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600" alt="Concert" class="object-cover w-full h-full event-image">
                        <div class="absolute top-3 left-3">
                            <div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl">
                                <span class="block text-lg font-bold leading-none">26</span>
                                <span class="block text-[10px] uppercase tracking-wide mt-0.5">Dec</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">Concert Cargo @Club Flex</h3>
                        <p class="text-sm text-muted mt-2 flex items-center gap-1.5">
                            <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            Club Flex, Arad
                        </p>
                        <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
                            <span class="font-bold text-primary">de la 60 lei</span>
                        </div>
                    </div>
                </a>

                <!-- Event Card 2 -->
                <a href="#" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">
                    <div class="relative overflow-hidden h-44">
                        <img src="https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600" alt="Concert" class="object-cover w-full h-full event-image">
                        <div class="absolute top-3 left-3">
                            <div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl">
                                <span class="block text-lg font-bold leading-none">12</span>
                                <span class="block text-[10px] uppercase tracking-wide mt-0.5">Feb</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">Dirty Shirt – Dirtylicious Decade Tour</h3>
                        <p class="text-sm text-muted mt-2 flex items-center gap-1.5">
                            <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            Lokal, Oradea
                        </p>
                        <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
                            <span class="font-bold text-primary">de la 70 lei</span>
                        </div>
                    </div>
                </a>

                <!-- Event Card 3 -->
                <a href="#" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">
                    <div class="relative overflow-hidden h-44">
                        <img src="https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=600" alt="Concert" class="object-cover w-full h-full event-image">
                        <div class="absolute top-3 left-3">
                            <div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl">
                                <span class="block text-lg font-bold leading-none">8</span>
                                <span class="block text-[10px] uppercase tracking-wide mt-0.5">Mar</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">Trooper – Strigăt În Noapte Tour</h3>
                        <p class="text-sm text-muted mt-2 flex items-center gap-1.5">
                            <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            Arenele Romane, București
                        </p>
                        <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
                            <span class="font-bold text-primary">de la 85 lei</span>
                        </div>
                    </div>
                </a>

                <!-- Event Card 4 -->
                <a href="#" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">
                    <div class="relative overflow-hidden h-44">
                        <img src="https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600" alt="Festival" class="object-cover w-full h-full event-image">
                        <div class="absolute top-3 left-3">
                            <div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl">
                                <span class="block text-lg font-bold leading-none">18-21</span>
                                <span class="block text-[10px] uppercase tracking-wide mt-0.5">Iun</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">Bikers For Humanity Rock Fest V</h3>
                        <p class="text-sm text-muted mt-2 flex items-center gap-1.5">
                            <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            Summer Camp, Brezoi
                        </p>
                        <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
                            <span class="font-bold text-primary">de la 100 lei</span>
                        </div>
                    </div>
                </a>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const EventPage = {
    slug: new URLSearchParams(window.location.search).get('slug') || window.location.pathname.split('/bilete/')[1]?.split('?')[0] || '',
    event: null,
    quantities: {},
    ticketTypes: [],
    galleryImages: [],

    async init() {
        if (!this.slug) {
            window.location.href = '/';
            return;
        }
        await this.loadEvent();
        this.updateHeaderCart();
    },

    async loadEvent() {
        try {
            const response = await AmbiletAPI.getEvent(this.slug);
            if (response.success && response.data) {
                this.event = response.data;
                this.render();
            } else {
                this.showError('Eveniment negasit');
            }
        } catch (error) {
            console.error('Failed to load event:', error);
            this.showError('Eroare la incarcarea evenimentului');
        }
    },

    showError(message) {
        document.getElementById('loading-state').innerHTML = `
            <div class="w-full py-16 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h1 class="mb-4 text-2xl font-bold text-secondary">${message}</h1>
                <a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Inapoi acasa
                </a>
            </div>
        `;
    },

    render() {
        const e = this.event;

        // Update page title
        document.title = `${e.title} — ${AMBILET_CONFIG.SITE_NAME}`;

        // Update breadcrumb
        document.getElementById('breadcrumb-title').textContent = e.title;

        // Show content, hide loading
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('event-content').classList.remove('hidden');

        // Main image
        const mainImg = e.image || e.images?.[0] || '/assets/images/placeholder-event.jpg';
        document.getElementById('mainImage').src = mainImg;
        document.getElementById('mainImage').alt = e.title;

        // Gallery
        this.galleryImages = e.images?.length ? e.images : [mainImg];
        this.renderGallery();

        // Badges
        const badgesHtml = [];
        if (e.category) badgesHtml.push(`<span class="px-3 py-1.5 bg-accent text-white text-xs font-bold rounded-lg uppercase">${e.category}</span>`);
        if (e.is_popular) badgesHtml.push(`<span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">🔥 Popular</span>`);
        document.getElementById('event-badges').innerHTML = badgesHtml.join('');

        // Title
        document.getElementById('event-title').textContent = e.title;

        // Date
        const eventDate = new Date(e.start_date || e.date);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const weekdays = ['Duminica', 'Luni', 'Marti', 'Miercuri', 'Joi', 'Vineri', 'Sambata'];

        document.getElementById('event-day').textContent = eventDate.getDate();
        document.getElementById('event-month').textContent = months[eventDate.getMonth()];
        document.getElementById('event-weekday').textContent = weekdays[eventDate.getDay()];
        document.getElementById('event-date-full').textContent = `${eventDate.getDate()} ${months[eventDate.getMonth()]} ${eventDate.getFullYear()}`;

        // Time
        document.getElementById('event-time').textContent = `Ora inceperii: ${e.start_time || '20:00'}`;
        document.getElementById('event-doors').textContent = `Deschidere usi: ${e.doors_time || '19:00'}`;

        // Venue
        document.getElementById('venue-name').textContent = e.venue?.name || e.location || 'Locatie TBA';
        document.getElementById('venue-address').textContent = e.venue?.address || '';

        // Stats
        document.getElementById('event-interested').textContent = `${e.interested || Math.floor(Math.random() * 500) + 100} interesati`;
        document.getElementById('event-views').textContent = `${e.views || (Math.random() * 3 + 0.5).toFixed(1)}k vizualizari`;

        // Description
        document.getElementById('event-description').innerHTML = this.formatDescription(e.description || e.content || 'Descriere indisponibila');

        // Artist section
        if (e.artist || e.artists?.length) {
            this.renderArtist(e.artist || e.artists[0]);
        }

        // Venue section
        this.renderVenue(e.venue || { name: e.location || 'Locatie TBA' });

        // Ticket types
        this.ticketTypes = e.ticket_types || this.getDefaultTicketTypes();
        this.renderTicketTypes();

        // Related events
        this.loadRelatedEvents();
    },

    formatDescription(desc) {
        // Convert plain text to paragraphs if needed
        if (!desc.includes('<p>') && !desc.includes('<div>')) {
            return desc.split('\n\n').map(p => `<p class="mb-4 leading-relaxed text-muted">${p}</p>`).join('');
        }
        return desc;
    },

    renderGallery() {
        const container = document.getElementById('gallery-thumbs');
        if (this.galleryImages.length <= 1) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = this.galleryImages.slice(0, 4).map((img, i) => `
            <button onclick="EventPage.changeImage(${i})" class="gallery-thumb ${i === 0 ? 'active' : ''} w-16 h-12 rounded-lg overflow-hidden border-2 border-white/50 opacity-80">
                <img src="${img}" class="object-cover w-full h-full">
            </button>
        `).join('');
    },

    changeImage(index) {
        document.getElementById('mainImage').src = this.galleryImages[index];
        document.querySelectorAll('.gallery-thumb').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    },

    renderArtist(artist) {
        if (!artist) return;
        document.getElementById('artist-section').style.display = 'block';
        document.getElementById('artist-content').innerHTML = `
            <div class="flex flex-col gap-6 md:flex-row">
                <div class="md:w-1/3">
                    <img src="${artist.image || '/assets/images/placeholder-artist.jpg'}" alt="${artist.name}" class="object-cover w-full aspect-square rounded-2xl">
                </div>
                <div class="md:w-2/3">
                    <div class="flex items-center gap-3 mb-4">
                        <h3 class="text-2xl font-bold text-secondary">${artist.name}</h3>
                        ${artist.verified ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-primary/10 text-primary">Verified</span>' : ''}
                    </div>
                    <p class="mb-4 leading-relaxed text-muted">${artist.description || artist.bio || ''}</p>
                    ${artist.stats ? `
                    <div class="flex flex-wrap gap-4 mt-4">
                        ${artist.stats.years ? `<div class="text-center"><p class="text-2xl font-bold text-secondary">${artist.stats.years}</p><p class="text-xs text-muted">Ani de activitate</p></div>` : ''}
                        ${artist.stats.albums ? `<div class="text-center"><p class="text-2xl font-bold text-secondary">${artist.stats.albums}</p><p class="text-xs text-muted">Albume</p></div>` : ''}
                        ${artist.stats.concerts ? `<div class="text-center"><p class="text-2xl font-bold text-secondary">${artist.stats.concerts}</p><p class="text-xs text-muted">Concerte</p></div>` : ''}
                    </div>
                    ` : ''}
                    <div class="flex gap-3 mt-6">
                        ${artist.facebook ? `<a href="${artist.facebook}" target="_blank" class="flex items-center justify-center w-10 h-10 transition-colors bg-surface rounded-xl hover:bg-primary hover:text-white"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>` : ''}
                        ${artist.instagram ? `<a href="${artist.instagram}" target="_blank" class="flex items-center justify-center w-10 h-10 transition-colors bg-surface rounded-xl hover:bg-primary hover:text-white"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>` : ''}
                    </div>
                </div>
            </div>
        `;
    },

    renderVenue(venue) {
        document.getElementById('venue-content').innerHTML = `
            <div class="flex flex-col gap-6 md:flex-row">
                <div class="md:w-1/2">
                    <img src="${venue.image || '/assets/images/placeholder-venue.jpg'}" alt="${venue.name}" class="object-cover w-full h-64 mb-4 rounded-2xl">
                    ${venue.map_url ? `
                    <div class="p-4 bg-surface rounded-xl">
                        <iframe src="${venue.map_url}" width="100%" height="200" style="border:0; border-radius: 12px;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                    ` : ''}
                </div>
                <div class="md:w-1/2">
                    <h3 class="mb-2 text-xl font-bold text-secondary">${venue.name}</h3>
                    <p class="mb-4 text-muted">${venue.address || ''}</p>
                    <p class="mb-4 leading-relaxed text-muted">${venue.description || ''}</p>

                    ${venue.amenities?.length ? `
                    <div class="mb-6 space-y-3">
                        ${venue.amenities.map(a => `
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">
                                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-sm text-secondary">${a}</span>
                            </div>
                        `).join('')}
                    </div>
                    ` : ''}

                    ${venue.google_maps_url ? `
                    <a href="${venue.google_maps_url}" target="_blank" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        Deschide in Google Maps
                    </a>
                    ` : ''}
                </div>
            </div>
        `;
    },

    getDefaultTicketTypes() {
        return [
            { id: 'early', name: 'Early Bird 🐦', price: 65, original_price: 80, available: 23, description: 'Acces general o zi • Primii 100 de cumparatori' },
            { id: 'standard', name: 'Standard', price: 80, available: 245, description: 'Acces general o zi • Standing area' },
            { id: 'vip', name: 'VIP ⭐', price: 150, available: 12, description: 'Acces ambele zile • Loc rezervat • Meet & Greet' },
            { id: 'premium', name: 'Premium 👑', price: 250, available: 5, description: 'Toate beneficiile VIP + Backstage Access + Merch exclusiv' }
        ];
    },

    renderTicketTypes() {
        const container = document.getElementById('ticket-types');
        container.innerHTML = this.ticketTypes.map(tt => {
            this.quantities[tt.id] = 0;
            const hasDiscount = tt.original_price && tt.original_price > tt.price;
            const discountPercent = hasDiscount ? Math.round((1 - tt.price / tt.original_price) * 100) : 0;
            const availabilityClass = tt.available <= 5 ? 'text-primary' : tt.available <= 20 ? 'text-accent' : 'text-success';
            const availabilityIcon = tt.available <= 5 ? '🔥' : tt.available <= 20 ? '⚡' : '✓';

            return `
                <div class="relative z-10 p-4 border-2 cursor-pointer ticket-card border-border rounded-2xl hover:z-20" data-ticket="${tt.id}" data-price="${tt.price}">
                    ${hasDiscount ? `<div class="absolute top-0 right-0"><div class="discount-badge text-white text-[10px] font-bold px-3 py-1 rounded-bl-lg">-${discountPercent}%</div></div>` : ''}
                    <div class="flex items-start justify-between mb-3">
                        <div class="relative tooltip-trigger">
                            <h3 class="font-bold border-b border-dashed text-secondary cursor-help border-muted">${tt.name}</h3>
                            <!-- Price Breakdown Tooltip -->
                            <div class="absolute left-0 z-10 w-64 p-4 mt-2 text-white shadow-xl tooltip top-full bg-secondary rounded-xl">
                                <p class="mb-2 text-sm font-semibold">Detalii pret bilet:</p>
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-white/70">Pret bilet:</span>
                                        <span>${(tt.price / 1.05).toFixed(2)} lei</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-white/70">Comision platforma (5%):</span>
                                        <span>${(tt.price - tt.price / 1.05).toFixed(2)} lei</span>
                                    </div>
                                    <div class="flex justify-between pt-1 mt-1 border-t border-white/20">
                                        <span class="font-semibold">Total:</span>
                                        <span class="font-semibold">${tt.price.toFixed(2)} lei</span>
                                    </div>
                                    <div class="flex justify-between text-white/50 text-[10px] mt-2">
                                        <span>Taxa Crucea Rosie (1%):</span>
                                        <span>+${(tt.price * 0.01).toFixed(2)} lei</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            ${hasDiscount ? `<span class="text-sm line-through text-muted">${tt.original_price} lei</span>` : ''}
                            <span class="block text-xl font-bold text-primary">${tt.price} lei</span>
                        </div>
                    </div>
                    <p class="mb-3 text-sm text-muted">${tt.description || ''}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs ${availabilityClass} font-semibold">${availabilityIcon} ${tt.available <= 20 ? `Doar ${tt.available} disponibile` : `${tt.available} disponibile`}</span>
                        <div class="flex items-center gap-2">
                            <button onclick="EventPage.updateQuantity('${tt.id}', -1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">-</button>
                            <span id="qty-${tt.id}" class="w-8 font-bold text-center">0</span>
                            <button onclick="EventPage.updateQuantity('${tt.id}', 1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">+</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    updateQuantity(ticketId, delta) {
        // Handle both string and number IDs with loose comparison
        const tt = this.ticketTypes.find(t => String(t.id) === String(ticketId));
        if (!tt) return;

        const newQty = (this.quantities[ticketId] || 0) + delta;
        if (newQty >= 0 && newQty <= tt.available) {
            this.quantities[ticketId] = newQty;
            document.getElementById(`qty-${ticketId}`).textContent = newQty;

            // Update ticket card selection
            const card = document.querySelector(`[data-ticket="${ticketId}"]`);
            if (card) card.classList.toggle('selected', newQty > 0);

            this.updateCart();
        }
    },

    updateCart() {
        const totalTickets = Object.values(this.quantities).reduce((a, b) => a + b, 0);
        let subtotal = 0;

        for (const [ticketId, qty] of Object.entries(this.quantities)) {
            const tt = this.ticketTypes.find(t => String(t.id) === String(ticketId));
            if (tt) subtotal += qty * tt.price;
        }

        const taxRedCross = subtotal * 0.01;
        const total = subtotal + taxRedCross;
        const points = Math.floor(subtotal / 10);

        // Update header cart count
        this.updateHeaderCart();

        // Show/hide cart summary
        const cartSummary = document.getElementById('cartSummary');
        const emptyCart = document.getElementById('emptyCart');

        if (totalTickets > 0) {
            cartSummary.classList.remove('hidden');
            emptyCart.classList.add('hidden');

            document.getElementById('subtotal').textContent = `${subtotal.toFixed(2)} lei`;
            document.getElementById('taxRedCross').textContent = `${taxRedCross.toFixed(2)} lei`;
            document.getElementById('totalPrice').textContent = `${total.toFixed(2)} lei`;

            const pointsEl = document.getElementById('pointsEarned');
            pointsEl.textContent = points;
            pointsEl.classList.remove('points-counter');
            void pointsEl.offsetWidth;
            pointsEl.classList.add('points-counter');
        } else {
            cartSummary.classList.add('hidden');
            emptyCart.classList.remove('hidden');
        }
    },

    addToCart() {
        for (const [ticketId, qty] of Object.entries(this.quantities)) {
            if (qty > 0) {
                const tt = this.ticketTypes.find(t => String(t.id) === String(ticketId));
                if (tt) {
                    AmbiletCart.addItem({
                        event_id: this.event.id,
                        event_title: this.event.title,
                        event_date: this.event.start_date || this.event.date,
                        event_image: this.event.image,
                        ticket_type_id: tt.id,
                        ticket_type_name: tt.name,
                        price: tt.price,
                        quantity: qty
                    });
                }
            }
        }
        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.success('Biletele au fost adaugate in cos!');
        }
        setTimeout(() => window.location.href = '/cart', 1000);
    },

    async loadRelatedEvents() {
        try {
            const response = await AmbiletAPI.get('/events', { limit: 4, exclude: this.event.id });
            if (response.success && response.data?.length) {
                this.renderRelatedEvents(response.data);
            }
        } catch (e) {
            // Show demo related events
            this.renderRelatedEvents([
                { slug: 'concert-1', title: 'Concert Cargo @Club Flex', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600', start_date: '2025-01-26', location: 'Club Flex, Arad', price: 60 },
                { slug: 'concert-2', title: 'Dirty Shirt – Dirtylicious Tour', image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600', start_date: '2025-02-12', location: 'Lokal, Oradea', price: 70 },
                { slug: 'concert-3', title: 'Trooper – Strigat In Noapte', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=600', start_date: '2025-03-08', location: 'Arenele Romane, Bucuresti', price: 85 },
                { slug: 'festival-1', title: 'Bikers For Humanity Rock Fest', image: 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600', start_date: '2025-06-18', location: 'Summer Camp, Brezoi', price: 100 }
            ]);
        }
    },

    renderRelatedEvents(events) {
        document.getElementById('related-events-section').style.display = 'block';
        document.getElementById('related-category-text').textContent = `Evenimente similare din categoria ${this.event.category || 'Rock'}`;

        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        document.getElementById('related-events').innerHTML = events.map(e => {
            const date = new Date(e.start_date || e.date);
            return `
                <a href="/bilete/${e.slug}" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">
                    <div class="relative overflow-hidden h-44">
                        <img src="${e.image || '/assets/images/placeholder-event.jpg'}" alt="${e.title}" class="object-cover w-full h-full event-image">
                        <div class="absolute top-3 left-3">
                            <div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl">
                                <span class="block text-lg font-bold leading-none">${date.getDate()}</span>
                                <span class="block text-[10px] uppercase tracking-wide mt-0.5">${months[date.getMonth()]}</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">${e.title}</h3>
                        <p class="text-sm text-muted mt-2 flex items-center gap-1.5">
                            <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            ${e.venue?.name || e.location || 'Locatie TBA'}
                        </p>
                        <div class="flex items-center justify-between pt-3 mt-3 border-t border-border">
                            <span class="font-bold text-primary">de la ${e.price || e.min_price || 50} lei</span>
                        </div>
                    </div>
                </a>
            `;
        }).join('');
    },

    updateHeaderCart() {
        const count = AmbiletCart.getItemCount();
        const cartBadge = document.getElementById('cartBadge');
        const cartDrawerCount = document.getElementById('cartDrawerCount');

        if (cartBadge) {
            if (count > 0) {
                cartBadge.textContent = count > 99 ? '99+' : count;
                cartBadge.classList.remove('hidden');
                cartBadge.classList.add('flex');
            } else {
                cartBadge.classList.add('hidden');
                cartBadge.classList.remove('flex');
            }
        }

        if (cartDrawerCount) {
            if (count > 0) {
                cartDrawerCount.textContent = count;
                cartDrawerCount.classList.remove('hidden');
            } else {
                cartDrawerCount.classList.add('hidden');
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', () => EventPage.init());
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
