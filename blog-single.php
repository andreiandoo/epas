<?php
require_once __DIR__ . '/includes/config.php';

// Sample article data - in production this would come from database
$article = [
    'title' => 'Tendinte in industria evenimentelor pentru 2025: Ce ne asteapta?',
    'category' => 'Industrie',
    'author' => ['name' => 'Maria Alexandrescu', 'role' => 'Editor sef', 'initials' => 'MA'],
    'date' => '20 Dec 2024',
    'read_time' => '10 min citire',
    'image' => null,
    'tags' => ['tendinte', '2025', 'evenimente hibride', 'sustenabilitate', 'tehnologie'],
];

$pageTitle = $article['title'] . ' - Blog';
$transparentHeader = false;
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Article Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-12 md:py-16 px-6 md:px-12 pb-20 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 mb-6">
                <a href="/" class="text-sm text-white/90 hover:text-white transition-colors">Acasa</a>
                <span class="text-sm text-white/40">/</span>
                <a href="/blog" class="text-sm text-white/90 hover:text-white transition-colors">Blog</a>
                <span class="text-sm text-white/40">/</span>
                <span class="text-sm text-white/80"><?= htmlspecialchars($article['category']) ?></span>
            </nav>

            <!-- Category Badge -->
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-5"><?= htmlspecialchars($article['category']) ?></span>

            <!-- Title -->
            <h1 class="text-3xl md:text-[44px] font-extrabold text-white leading-tight mb-6 tracking-tight"><?= htmlspecialchars($article['title']) ?></h1>

            <!-- Author & Meta -->
            <div class="flex flex-wrap items-center gap-4 md:gap-8">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center text-base font-bold text-white"><?= htmlspecialchars($article['author']['initials']) ?></div>
                    <div>
                        <div class="text-[15px] font-semibold text-white"><?= htmlspecialchars($article['author']['name']) ?></div>
                        <div class="text-sm text-white/90"><?= htmlspecialchars($article['author']['role']) ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-5">
                    <span class="flex items-center gap-1.5 text-sm text-white/90">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?= htmlspecialchars($article['date']) ?>
                    </span>
                    <span class="flex items-center gap-1.5 text-sm text-white/90">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= htmlspecialchars($article['read_time']) ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Article Image -->
    <div class="max-w-4xl mx-auto -mt-10 px-6 md:px-12 relative z-10">
        <div class="rounded-2xl overflow-hidden shadow-[0_20px_60px_rgba(0,0,0,0.2)]">
            <?php if ($article['image']): ?>
            <img src="<?= htmlspecialchars($article['image']) ?>" alt="" class="w-full h-[280px] md:h-[450px] object-cover">
            <?php else: ?>
            <div class="h-[280px] md:h-[450px] bg-gradient-to-br from-primary to-red-800 flex items-center justify-center">
                <svg class="w-16 h-16 md:w-20 md:h-20 text-white/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Article Layout -->
    <div class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16 grid lg:grid-cols-[1fr_300px] gap-12 md:gap-16">
        <!-- Article Content -->
        <article>
            <div class="prose prose-lg max-w-none text-slate-600 leading-relaxed
                        [&>p]:mb-6 [&>p]:text-lg
                        [&>h2]:text-[28px] [&>h2]:font-bold [&>h2]:text-slate-800 [&>h2]:mt-12 [&>h2]:mb-5
                        [&>h3]:text-[22px] [&>h3]:font-bold [&>h3]:text-slate-800 [&>h3]:mt-9 [&>h3]:mb-4
                        [&>ul]:my-5 [&>ul]:ml-6 [&>ul>li]:mb-3
                        [&>ol]:my-5 [&>ol]:ml-6 [&>ol>li]:mb-3
                        [&>blockquote]:my-8 [&>blockquote]:py-6 [&>blockquote]:px-8 [&>blockquote]:bg-gradient-to-br [&>blockquote]:from-primary/5 [&>blockquote]:to-primary/[0.02] [&>blockquote]:border-l-4 [&>blockquote]:border-primary [&>blockquote]:rounded-r-xl [&>blockquote]:text-xl [&>blockquote]:italic [&>blockquote]:text-slate-800
                        [&_a]:text-primary [&_a]:underline
                        [&_strong]:text-slate-800 [&_strong]:font-semibold">
                <p>Industria evenimentelor trece printr-o transformare profunda. Dupa provocarile din ultimii ani, organizatorii au invatat sa se adapteze si sa inoveze. In 2025, ne asteptam la schimbari semnificative care vor redefini modul in care cream si experimentam evenimentele.</p>

                <h2>1. Experiente hibride devin standard</h2>
                <p>Evenimentele hibride nu mai sunt o noutate, dar in 2025 vor deveni norma. Participantii se asteapta la flexibilitate: optiunea de a participa fizic sau virtual, fara compromisuri in calitatea experientei.</p>
                <p>Organizatorii care vor excela sunt cei care vor crea <strong>doua experiente complementare</strong>, nu o experienta fizica transmisa online. Aceasta inseamna continut exclusiv pentru participantii virtuali, networking facilitat de tehnologie si interactivitate in timp real.</p>

                <blockquote>"Evenimentele hibride de succes nu sunt evenimente fizice cu o camera de streaming. Sunt doua experiente distincte care comunica intre ele."</blockquote>

                <h2>2. Personalizare la scara larga</h2>
                <p>Datorita analizei datelor si inteligentei artificiale, organizatorii pot oferi experiente personalizate fiecarui participant. De la recomandari de sesiuni bazate pe interese, pana la networking matches inteligente.</p>
                <ul>
                    <li>Agende personalizate generate automat</li>
                    <li>Recomandari de conexiuni relevante</li>
                    <li>Continut adaptat in functie de comportamentul participantului</li>
                    <li>Comunicare segmentata pre si post eveniment</li>
                </ul>

                <h2>3. Sustenabilitate ca prioritate</h2>
                <p>Participantii, in special generatiile tinere, aleg sa participe la evenimente care demonstreaza angajament fata de mediu. Organizatorii care ignora acest aspect risca sa piarda relevanta.</p>
                <p>Masuri concrete precum eliminarea materialelor printate, catering local si de sezon, compensarea amprentei de carbon si alegerea locatiilor cu certificari verzi devin factori de decizie pentru participanti.</p>

                <h3>Ce pot face organizatorii?</h3>
                <p>Incepe prin a masura impactul actual al evenimentului. Foloseste instrumente de calcul al amprentei de carbon si stabileste obiective realiste de reducere. Comunica transparent eforturile tale - participantii apreciaza onestitatea mai mult decat perfectiunea.</p>

                <h2>4. Experiente imersive si tehnologii emergente</h2>
                <p>Realitatea augmentata si virtuala nu mai sunt gimmick-uri, ci unelte practice. De la tururi virtuale ale locatiei inaintea evenimentului, la experiente AR care imbogatesc standurile expozantilor.</p>
                <p>In 2025, vom vedea mai multe:</p>
                <ol>
                    <li>Aplicatii AR pentru navigare si informatii contextuale</li>
                    <li>Showroom-uri virtuale pentru expozanti</li>
                    <li>Sesiuni de networking in spatii VR</li>
                    <li>Integrare cu wearables pentru experiente personalizate</li>
                </ol>

                <h2>Concluzie</h2>
                <p>2025 va fi un an al maturizarii industriei evenimentelor. Tendintele de care am vorbit nu sunt doar despre adoptarea tehnologiei, ci despre <strong>crearea de valoare reala</strong> pentru participanti si comunitati.</p>
                <p>Organizatorii care vor reusi sunt cei care vad schimbarea ca oportunitate, nu amenintare. Cei care asculta ce isi doresc participantii si raspund cu solutii inovatoare.</p>
            </div>

            <!-- Tags -->
            <div class="mt-12 pt-8 border-t border-slate-200">
                <div class="text-sm font-semibold text-slate-500 mb-3">Etichete</div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($article['tags'] as $tag): ?>
                    <a href="/blog?tag=<?= urlencode($tag) ?>" class="px-4 py-2 bg-slate-100 rounded-lg text-sm font-medium text-slate-500 hover:bg-primary hover:text-white transition-all"><?= htmlspecialchars($tag) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Share -->
            <div class="mt-8 pt-8 border-t border-slate-200">
                <div class="text-sm font-semibold text-slate-500 mb-3">Distribuie articolul</div>
                <div class="flex gap-3">
                    <a href="#" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" title="Facebook">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                    <a href="#" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" title="Twitter">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
                    </a>
                    <a href="#" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" title="LinkedIn">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                    </a>
                    <a href="#" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" title="Copiaza link">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    </a>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="space-y-6 lg:space-y-0 lg:contents [&>*]:lg:mb-6">
            <!-- TOC -->
            <div class="bg-white rounded-2xl border border-slate-200 p-7">
                <h3 class="text-base font-bold text-slate-800 mb-5">Cuprins</h3>
                <nav class="space-y-0">
                    <a href="#" class="block py-2.5 text-sm text-primary font-semibold border-b border-slate-100 last:border-0">1. Experiente hibride devin standard</a>
                    <a href="#" class="block py-2.5 text-sm text-slate-500 hover:text-primary border-b border-slate-100 last:border-0 transition-colors">2. Personalizare la scara larga</a>
                    <a href="#" class="block py-2.5 text-sm text-slate-500 hover:text-primary border-b border-slate-100 last:border-0 transition-colors">3. Sustenabilitate ca prioritate</a>
                    <a href="#" class="block py-2.5 text-sm text-slate-500 hover:text-primary border-b border-slate-100 last:border-0 transition-colors">4. Experiente imersive</a>
                    <a href="#" class="block py-2.5 text-sm text-slate-500 hover:text-primary transition-colors">Concluzie</a>
                </nav>
            </div>

            <!-- Author Bio -->
            <div class="bg-white rounded-2xl border border-slate-200 p-7">
                <h3 class="text-base font-bold text-slate-800 mb-5">Despre autor</h3>
                <div class="flex gap-4 items-start">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center text-lg font-bold text-white flex-shrink-0"><?= htmlspecialchars($article['author']['initials']) ?></div>
                    <div>
                        <div class="text-[15px] font-bold text-slate-800 mb-1"><?= htmlspecialchars($article['author']['name']) ?></div>
                        <div class="text-sm text-slate-500 leading-relaxed">Editor sef la AmBilet Blog. Pasionata de evenimente si tendinte in industrie.</div>
                    </div>
                </div>
            </div>

            <!-- Newsletter -->
            <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-2xl p-7">
                <h3 class="text-base font-bold text-white mb-5">Newsletter</h3>
                <p class="text-sm text-white/90 mb-4">Primeste articole noi direct in inbox.</p>
                <input type="email" placeholder="Email-ul tau" class="w-full px-4 py-3.5 bg-white/10 border border-white/20 rounded-xl text-sm text-white placeholder:text-white/90 mb-3 focus:outline-none focus:border-white/40">
                <button class="w-full py-3.5 bg-gradient-to-br from-primary to-red-600 rounded-xl text-sm font-bold text-white hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(165,28,48,0.4)] transition-all">Aboneaza-te</button>
            </div>

            <!-- Related Articles -->
            <div class="bg-white rounded-2xl border border-slate-200 p-7">
                <h3 class="text-base font-bold text-slate-800 mb-5">Articole similare</h3>
                <div class="space-y-4">
                    <a href="#" class="flex gap-4 group">
                        <div class="w-20 h-[60px] rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 flex-shrink-0"></div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800 leading-snug mb-1 group-hover:text-primary transition-colors">Raport: Piata evenimentelor din Romania in 2024</div>
                            <div class="text-xs text-slate-400">8 Dec 2024</div>
                        </div>
                    </a>
                    <a href="#" class="flex gap-4 group pt-4 border-t border-slate-100">
                        <div class="w-20 h-[60px] rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 flex-shrink-0"></div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800 leading-snug mb-1 group-hover:text-primary transition-colors">5 greseli comune in promovarea evenimentelor</div>
                            <div class="text-xs text-slate-400">10 Dec 2024</div>
                        </div>
                    </a>
                    <a href="#" class="flex gap-4 group pt-4 border-t border-slate-100">
                        <div class="w-20 h-[60px] rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 flex-shrink-0"></div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800 leading-snug mb-1 group-hover:text-primary transition-colors">Cum sa folosesti social media pentru promovare</div>
                            <div class="text-xs text-slate-400">5 Dec 2024</div>
                        </div>
                    </a>
                </div>
            </div>
        </aside>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
