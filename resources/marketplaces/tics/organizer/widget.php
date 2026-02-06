<?php
/**
 * Widget Configurator Page
 * Real-time widget configurator for organizers
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Get event slug from URL or use first event
$events = $demoData['events'];
$eventSlug = isset($_GET['event']) ? $_GET['event'] : 'coldplay-2026';

// Demo event data for widget preview
$eventData = [
    'name' => 'Coldplay: Music of the Spheres',
    'date' => '14 Feb 2026',
    'time' => '20:00',
    'venue' => 'Arena Nationala',
    'city' => 'Bucuresti',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=200&fit=crop',
    'min_price' => 149,
    'tickets' => [
        ['name' => 'General Admission', 'price' => 149, 'available' => true],
        ['name' => 'VIP Experience', 'price' => 349, 'available' => true],
        ['name' => 'Golden Circle', 'price' => 599, 'available' => false],
    ],
    'status' => 'available',
    'slug' => $eventSlug
];

// Available colors - more options, compact display
$colorOptions = [
    'indigo' => '#6366f1',
    'blue' => '#3b82f6',
    'cyan' => '#06b6d4',
    'teal' => '#14b8a6',
    'green' => '#22c55e',
    'yellow' => '#eab308',
    'orange' => '#f97316',
    'red' => '#ef4444',
    'pink' => '#ec4899',
    'purple' => '#8b5cf6',
    'gray' => '#6b7280',
    'black' => '#1f2937',
];

// Current page for sidebar
$currentPage = 'widget';

// Page config for head
$pageTitle = 'Configurator Widget';
$pageDescription = 'Personalizeaza si integreaza widget-ul TICS pe site-ul tau';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/organizer-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="lg:ml-64 pt-16 lg:pt-0">
        <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between px-8 py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Configurator Widget</h1>
                    <p class="text-sm text-gray-500">Personalizeaza si integreaza widget-ul TICS pe site-ul tau</p>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Left: Configurator Options -->
                <div class="space-y-6">
                    <!-- Event Selection -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Selecteaza evenimentul</h2>
                        <select id="eventSelect" class="input-field w-full px-4 py-3 border border-gray-200 rounded-xl outline-none" onchange="updateWidget()">
                            <?php foreach ($events as $event): ?>
                            <option value="<?= htmlspecialchars($event['slug'] ?? 'event-' . $event['id']) ?>" <?= ($event['slug'] ?? '') === $eventSlug ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Theme & Color -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.1s">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Tema si culoare</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tema</label>
                                <div class="flex gap-2">
                                    <button type="button" onclick="setTheme('light')" id="themeLight" class="theme-btn flex-1 flex items-center justify-center gap-2 p-3 border-2 border-indigo-500 bg-indigo-50 rounded-xl transition-all">
                                        <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/></svg>
                                        <span class="text-sm font-medium">Light</span>
                                    </button>
                                    <button type="button" onclick="setTheme('dark')" id="themeDark" class="theme-btn flex-1 flex items-center justify-center gap-2 p-3 border-2 border-gray-200 rounded-xl transition-all hover:border-gray-300">
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/></svg>
                                        <span class="text-sm font-medium">Dark</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Culoare principala</label>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($colorOptions as $key => $hex): ?>
                                    <button type="button" onclick="setColor('<?= $key ?>')" class="color-btn w-8 h-8 rounded-lg border-2 border-transparent transition-all hover:scale-110 <?= $key === 'indigo' ? 'ring-2 ring-offset-2 ring-gray-900' : '' ?>" style="background-color: <?= $hex ?>" data-color="<?= $key ?>" title="<?= ucfirst($key) ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Size & Style -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.2s">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Dimensiune si stil</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Latime</label>
                                <div class="flex items-center gap-3">
                                    <input type="range" id="widthSlider" min="300" max="500" value="400" class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer" onchange="updateWidget()" oninput="document.getElementById('widthValue').textContent = this.value + 'px'">
                                    <span id="widthValue" class="text-sm font-medium text-gray-700 w-14 text-right">400px</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stil afisare</label>
                                <div class="flex gap-2">
                                    <button type="button" onclick="setStyle('compact')" id="styleCompact" class="style-btn flex-1 p-3 border-2 border-indigo-500 bg-indigo-50 rounded-xl transition-all text-center">
                                        <span class="text-sm font-medium">Compact</span>
                                        <span class="block text-xs text-gray-500">Pret minim</span>
                                    </button>
                                    <button type="button" onclick="setStyle('extended')" id="styleExtended" class="style-btn flex-1 p-3 border-2 border-gray-200 rounded-xl transition-all text-center hover:border-gray-300">
                                        <span class="text-sm font-medium">Extins</span>
                                        <span class="block text-xs text-gray-500">Toate biletele</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Integration Code -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 animate-fadeInUp" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Cod de integrare</h2>
                            <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                                <button type="button" onclick="setCodeType('js')" id="codeJs" class="code-type-btn px-3 py-1 text-sm font-medium rounded-md bg-white shadow-sm">JS</button>
                                <button type="button" onclick="setCodeType('iframe')" id="codeIframe" class="code-type-btn px-3 py-1 text-sm font-medium rounded-md text-gray-500">iFrame</button>
                            </div>
                        </div>
                        <div class="relative">
                            <pre id="codeBlock" class="bg-gray-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto"></pre>
                            <button onclick="copyCode()" class="absolute top-2 right-2 p-1.5 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition-colors">
                                <svg id="copyIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <svg id="checkIcon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </div>
                        <p id="codeNote" class="mt-2 text-xs text-gray-500">Varianta JS ofera redimensionare automata si actualizari in timp real.</p>
                    </div>
                </div>

                <!-- Right: Live Preview -->
                <div class="lg:sticky lg:top-24 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900">Previzualizare</h2>
                        <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Live</span>
                    </div>

                    <!-- Preview Container -->
                    <div class="bg-gray-100 rounded-2xl p-6 flex items-start justify-center min-h-[520px]" style="background-image: url('data:image/svg+xml,%3Csvg width=\'16\' height=\'16\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M0 0h8v8H0zM8 8h8v8H8z\' fill=\'%23e5e7eb\' fill-opacity=\'.5\'/%3E%3C/svg%3E');">

                        <!-- Widget Preview -->
                        <div id="widgetPreview" class="widget-preview bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-lg transition-all duration-300" style="width: 400px;">
                            <!-- Top accent bar -->
                            <div id="widgetAccent" class="h-1 bg-indigo-600"></div>

                            <div class="p-5">
                                <!-- Event Image -->
                                <img src="<?= htmlspecialchars($eventData['image']) ?>" id="widgetImage" class="w-full h-36 object-cover rounded-xl mb-4" alt="Event">

                                <!-- Event Info -->
                                <h3 id="widgetTitle" class="font-bold text-gray-900 text-lg mb-1"><?= htmlspecialchars($eventData['name']) ?></h3>
                                <p id="widgetDetails" class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($eventData['date']) ?> <?= htmlspecialchars($eventData['time']) ?> - <?= htmlspecialchars($eventData['venue']) ?>, <?= htmlspecialchars($eventData['city']) ?></p>

                                <!-- Compact: Price Only -->
                                <div id="compactPrice" class="flex items-center justify-between mb-4">
                                    <div>
                                        <p id="priceLabel" class="text-xs text-gray-500">de la</p>
                                        <p id="widgetPrice" class="text-xl font-bold text-indigo-600"><?= number_format($eventData['min_price']) ?> RON</p>
                                    </div>
                                    <span id="widgetStatus" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Disponibil</span>
                                </div>

                                <!-- Extended: Ticket List -->
                                <div id="extendedTickets" class="hidden space-y-2 mb-4">
                                    <?php foreach ($eventData['tickets'] as $ticket): ?>
                                    <div class="ticket-row flex items-center justify-between p-3 border border-gray-200 rounded-xl <?= !$ticket['available'] ? 'opacity-50' : '' ?>">
                                        <div>
                                            <p class="ticket-name text-sm font-medium text-gray-900"><?= htmlspecialchars($ticket['name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= $ticket['available'] ? 'Disponibil' : 'Epuizat' ?></p>
                                        </div>
                                        <span class="ticket-price font-bold text-gray-900"><?= number_format($ticket['price']) ?> RON</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- CTA Button -->
                                <button id="widgetCTA" class="w-full py-3 bg-indigo-600 text-white font-bold rounded-xl hover:opacity-90 transition-opacity">
                                    Cumpara bilete
                                </button>

                                <!-- TICS Branding - Bottom -->
                                <div class="flex items-center justify-center gap-2 mt-4 pt-3 border-t border-gray-100">
                                    <div id="widgetLogoBg" class="w-5 h-5 bg-indigo-600 rounded flex items-center justify-center">
                                        <span class="text-white text-[10px] font-bold">T</span>
                                    </div>
                                    <span id="widgetLogoText" class="text-xs text-gray-400">Powered by TICS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        /* Range slider */
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #6366f1;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input[type="range"]::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #6366f1;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
    </style>

    <script>
        // Configuration state
        const config = {
            event: '<?= htmlspecialchars($eventSlug) ?>',
            theme: 'light',
            color: 'indigo',
            width: 400,
            style: 'compact'
        };

        const colors = <?= json_encode($colorOptions) ?>;
        let codeType = 'js';

        // Theme selection
        function setTheme(theme) {
            config.theme = theme;
            document.getElementById('themeLight').className = 'theme-btn flex-1 flex items-center justify-center gap-2 p-3 border-2 rounded-xl transition-all ' +
                (theme === 'light' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300');
            document.getElementById('themeDark').className = 'theme-btn flex-1 flex items-center justify-center gap-2 p-3 border-2 rounded-xl transition-all ' +
                (theme === 'dark' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300');
            updateWidget();
        }

        // Color selection
        function setColor(color) {
            config.color = color;
            document.querySelectorAll('.color-btn').forEach(btn => {
                btn.classList.remove('ring-2', 'ring-offset-2', 'ring-gray-900');
            });
            document.querySelector(`.color-btn[data-color="${color}"]`).classList.add('ring-2', 'ring-offset-2', 'ring-gray-900');
            updateWidget();
        }

        // Style selection
        function setStyle(style) {
            config.style = style;
            document.getElementById('styleCompact').className = 'style-btn flex-1 p-3 border-2 rounded-xl transition-all text-center ' +
                (style === 'compact' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300');
            document.getElementById('styleExtended').className = 'style-btn flex-1 p-3 border-2 rounded-xl transition-all text-center ' +
                (style === 'extended' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300');
            updateWidget();
        }

        // Code type selection
        function setCodeType(type) {
            codeType = type;
            document.getElementById('codeJs').className = 'code-type-btn px-3 py-1 text-sm font-medium rounded-md ' +
                (type === 'js' ? 'bg-white shadow-sm' : 'text-gray-500');
            document.getElementById('codeIframe').className = 'code-type-btn px-3 py-1 text-sm font-medium rounded-md ' +
                (type === 'iframe' ? 'bg-white shadow-sm' : 'text-gray-500');
            document.getElementById('codeNote').textContent = type === 'js'
                ? 'Varianta JS ofera redimensionare automata si actualizari in timp real.'
                : 'iFrame-ul are dimensiuni fixe. Ajusteaza height daca folosesti stilul Extins.';
            updateCode();
        }

        // Update widget preview
        function updateWidget() {
            config.event = document.getElementById('eventSelect').value;
            config.width = document.getElementById('widthSlider').value;

            const colorHex = colors[config.color];
            const widget = document.getElementById('widgetPreview');
            const isDark = config.theme === 'dark';

            // Width
            widget.style.width = config.width + 'px';

            // Colors
            document.getElementById('widgetAccent').style.backgroundColor = colorHex;
            document.getElementById('widgetLogoBg').style.backgroundColor = colorHex;
            document.getElementById('widgetPrice').style.color = colorHex;
            document.getElementById('widgetCTA').style.backgroundColor = colorHex;

            // Theme
            widget.className = 'widget-preview rounded-2xl border overflow-hidden shadow-lg transition-all duration-300 ' +
                (isDark ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200');
            document.getElementById('widgetTitle').className = 'font-bold text-lg mb-1 ' + (isDark ? 'text-white' : 'text-gray-900');
            document.getElementById('widgetDetails').className = 'text-sm mb-4 ' + (isDark ? 'text-gray-400' : 'text-gray-500');
            document.getElementById('priceLabel').className = 'text-xs ' + (isDark ? 'text-gray-400' : 'text-gray-500');
            document.getElementById('widgetLogoText').className = 'text-xs ' + (isDark ? 'text-gray-500' : 'text-gray-400');

            // Ticket rows theme
            document.querySelectorAll('.ticket-row').forEach(row => {
                row.className = row.className.replace(/border-gray-\d+/g, '') + (isDark ? ' border-gray-700' : ' border-gray-200');
            });
            document.querySelectorAll('.ticket-name').forEach(el => {
                el.className = 'ticket-name text-sm font-medium ' + (isDark ? 'text-white' : 'text-gray-900');
            });
            document.querySelectorAll('.ticket-price').forEach(el => {
                el.className = 'ticket-price font-bold ' + (isDark ? 'text-white' : 'text-gray-900');
            });

            // Style
            document.getElementById('compactPrice').classList.toggle('hidden', config.style !== 'compact');
            document.getElementById('extendedTickets').classList.toggle('hidden', config.style !== 'extended');

            updateCode();
        }

        // Update code display
        function updateCode() {
            const codeBlock = document.getElementById('codeBlock');

            if (codeType === 'js') {
                // Elegant, minimal JS code
                codeBlock.innerHTML = escapeHtml(`<div class="tics-widget" data-event="${config.event}" data-theme="${config.theme}" data-color="${config.color}" data-width="${config.width}" data-style="${config.style}"></div>
<script src="https://tics.ro/widget.js"><\/script>`);
            } else {
                const h = config.style === 'extended' ? 520 : 420;
                codeBlock.innerHTML = escapeHtml(`<iframe src="https://tics.ro/w/${config.event}?t=${config.theme}&c=${config.color}&s=${config.style}" width="${config.width}" height="${h}" frameborder="0"></iframe>`);
            }
        }

        function escapeHtml(text) {
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }

        function copyCode() {
            let code;
            if (codeType === 'js') {
                code = `<div class="tics-widget" data-event="${config.event}" data-theme="${config.theme}" data-color="${config.color}" data-width="${config.width}" data-style="${config.style}"></div>\n<script src="https://tics.ro/widget.js"><\/script>`;
            } else {
                const h = config.style === 'extended' ? 520 : 420;
                code = `<iframe src="https://tics.ro/w/${config.event}?t=${config.theme}&c=${config.color}&s=${config.style}" width="${config.width}" height="${h}" frameborder="0"></iframe>`;
            }

            navigator.clipboard.writeText(code).then(() => {
                document.getElementById('copyIcon').classList.add('hidden');
                document.getElementById('checkIcon').classList.remove('hidden');
                setTimeout(() => {
                    document.getElementById('copyIcon').classList.remove('hidden');
                    document.getElementById('checkIcon').classList.add('hidden');
                }, 2000);
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', updateWidget);
    </script>
</body>
</html>
