<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Blog';
$transparentHeader = false;

// Sample blog data
$categories = ['Toate', 'Industrie', 'Pentru organizatori', 'Ghiduri', 'Interviuri', 'Noutati'];
$activeCategory = $_GET['cat'] ?? 'Toate';

$featuredArticle = [
    'title' => 'Tendinte in industria evenimentelor pentru 2025: Ce ne asteapta?',
    'excerpt' => 'De la experiente hibride la personalizare avansata si sustenabilitate, exploram principalele tendinte care vor defini industria evenimentelor in anul care vine.',
    'category' => 'Industrie',
    'date' => '20 Decembrie 2024',
    'author' => ['name' => 'Maria Alexandrescu', 'role' => 'Editor sef', 'initials' => 'MA'],
    'slug' => 'tendinte-industria-evenimentelor-2025',
];

$articles = [
    ['title' => 'Cum sa creezi o strategie de preturi pentru bilete', 'excerpt' => 'Ghid complet pentru stabilirea preturilor optime: early bird, last minute si tot ce trebuie sa stii.', 'category' => 'Ghiduri', 'date' => '18 Dec 2024', 'author' => ['name' => 'Ion Popescu', 'initials' => 'IP'], 'read_time' => '8 min'],
    ['title' => 'Interviu cu Echo Events: Secretele unui organizator de succes', 'excerpt' => 'Am stat de vorba cu echipa Echo Events despre cum au crescut de la 0 la 50.000 de participanti.', 'category' => 'Interviuri', 'date' => '15 Dec 2024', 'author' => ['name' => 'Ana Dumitrescu', 'initials' => 'AD'], 'read_time' => '12 min'],
    ['title' => 'AmBilet lanseaza noul sistem de check-in cu NFC', 'excerpt' => 'Descopera cum poti valida biletele in mai putin de o secunda cu noua noastra tehnologie.', 'category' => 'Noutati', 'date' => '12 Dec 2024', 'author' => ['name' => 'Maria Alexandrescu', 'initials' => 'MA'], 'read_time' => '5 min'],
    ['title' => '5 greseli comune in promovarea evenimentelor', 'excerpt' => 'Evita aceste capcane frecvente si maximizeaza vizibilitatea evenimentului tau.', 'category' => 'Pentru organizatori', 'date' => '10 Dec 2024', 'author' => ['name' => 'Cristian Matei', 'initials' => 'CM'], 'read_time' => '6 min'],
    ['title' => 'Raport: Piata evenimentelor din Romania in 2024', 'excerpt' => 'Analiza detaliata a pietei: cifre, tendinte si previziuni pentru urmatorii ani.', 'category' => 'Industrie', 'date' => '8 Dec 2024', 'author' => ['name' => 'Ion Popescu', 'initials' => 'IP'], 'read_time' => '15 min'],
    ['title' => 'Cum sa folosesti social media pentru promovare', 'excerpt' => 'Strategii dovedite pentru Instagram, TikTok si Facebook care aduc participanti.', 'category' => 'Ghiduri', 'date' => '5 Dec 2024', 'author' => ['name' => 'Ana Dumitrescu', 'initials' => 'AD'], 'read_time' => '10 min'],
];

$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-16 md:py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto text-center relative z-10">
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4">Blog</h1>
            <p class="text-lg text-white/90 leading-relaxed">Noutati, ghiduri si inspiratie din lumea evenimentelor</p>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
        <!-- Categories -->
        <div class="flex flex-wrap gap-3 justify-center mb-12">
            <?php foreach ($categories as $cat):
                $isActive = $activeCategory === $cat;
            ?>
            <a href="?cat=<?= urlencode($cat) ?>" class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all <?= $isActive ? 'bg-gradient-to-br from-primary to-red-600 text-white' : 'bg-white border border-slate-200 text-slate-500 hover:border-primary hover:text-primary' ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Featured Article -->
        <div class="mb-16">
            <div class="grid md:grid-cols-[1.2fr_1fr] gap-0 bg-white rounded-3xl overflow-hidden border border-slate-200 hover:shadow-[0_20px_60px_rgba(0,0,0,0.1)] hover:-translate-y-1 transition-all">
                <div class="h-[250px] md:h-[400px] bg-gradient-to-br from-primary to-red-800 relative overflow-hidden">
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 md:w-20 md:h-20 text-white/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                </div>
                <div class="p-8 md:p-12 flex flex-col justify-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-[11px] font-bold text-white uppercase tracking-wide w-fit mb-4">Articol principal</div>
                    <div class="flex items-center gap-4 mb-4">
                        <span class="text-sm font-semibold text-primary"><?= htmlspecialchars($featuredArticle['category']) ?></span>
                        <span class="text-sm text-slate-400"><?= htmlspecialchars($featuredArticle['date']) ?></span>
                    </div>
                    <h2 class="text-2xl md:text-[32px] font-extrabold text-slate-800 leading-tight mb-4">
                        <a href="/blog/<?= htmlspecialchars($featuredArticle['slug']) ?>" class="hover:text-primary transition-colors"><?= htmlspecialchars($featuredArticle['title']) ?></a>
                    </h2>
                    <p class="text-base text-slate-500 leading-relaxed mb-6"><?= htmlspecialchars($featuredArticle['excerpt']) ?></p>
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-base font-bold text-slate-500"><?= htmlspecialchars($featuredArticle['author']['initials']) ?></div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($featuredArticle['author']['name']) ?></div>
                            <div class="text-sm text-slate-400"><?= htmlspecialchars($featuredArticle['author']['role']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Articles Grid -->
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-slate-800">Articole recente</h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-16">
            <?php foreach ($articles as $article): ?>
            <article class="bg-white rounded-2xl overflow-hidden border border-slate-200 hover:shadow-[0_12px_40px_rgba(0,0,0,0.08)] hover:-translate-y-1 transition-all">
                <div class="h-[200px] bg-gradient-to-br from-slate-200 to-slate-300 relative overflow-hidden">
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-xs font-semibold text-primary bg-primary/10 px-2.5 py-1 rounded"><?= htmlspecialchars($article['category']) ?></span>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars($article['date']) ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 leading-snug mb-3">
                        <a href="#" class="hover:text-primary transition-colors"><?= htmlspecialchars($article['title']) ?></a>
                    </h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4 line-clamp-3"><?= htmlspecialchars($article['excerpt']) ?></p>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-bold text-slate-500"><?= htmlspecialchars($article['author']['initials']) ?></div>
                            <span class="text-sm font-medium text-slate-500"><?= htmlspecialchars($article['author']['name']) ?></span>
                        </div>
                        <span class="text-xs text-slate-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= htmlspecialchars($article['read_time']) ?>
                        </span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Newsletter -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-10 md:p-16 text-center relative overflow-hidden mb-16">
            <div class="absolute -top-[100px] -right-[100px] w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(165,28,48,0.2),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10 max-w-lg mx-auto">
                <h2 class="text-2xl md:text-[28px] font-extrabold text-white mb-3">Aboneaza-te la newsletter</h2>
                <p class="text-base text-white/90 mb-8">Primeste cele mai noi articole si noutati direct in inbox.</p>
                <form class="flex flex-col sm:flex-row gap-3">
                    <input type="email" placeholder="Adresa ta de email" class="flex-1 px-5 py-4 bg-white/10 border border-white/20 rounded-xl text-[15px] text-white placeholder:text-white/90 focus:outline-none focus:border-primary focus:bg-white/15">
                    <button type="submit" class="px-7 py-4 bg-gradient-to-br from-primary to-red-600 rounded-xl text-[15px] font-bold text-white hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(165,28,48,0.4)] transition-all">Aboneaza-te</button>
                </form>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-center gap-2">
            <a href="#" class="w-11 h-11 flex items-center justify-center bg-slate-50 border border-slate-200 rounded-xl text-slate-500 hover:bg-primary hover:text-white hover:border-primary transition-all">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <a href="#" class="w-11 h-11 flex items-center justify-center bg-gradient-to-br from-primary to-red-600 rounded-xl text-sm font-semibold text-white">1</a>
            <a href="#" class="w-11 h-11 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-500 hover:border-primary hover:text-primary transition-all">2</a>
            <a href="#" class="w-11 h-11 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-500 hover:border-primary hover:text-primary transition-all">3</a>
            <a href="#" class="w-11 h-11 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-500 hover:border-primary hover:text-primary transition-all">4</a>
            <a href="#" class="w-11 h-11 flex items-center justify-center bg-slate-50 border border-slate-200 rounded-xl text-slate-500 hover:bg-primary hover:text-white hover:border-primary transition-all">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
