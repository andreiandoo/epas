<?php
/**
 * Embed Widget - Actual iframe content
 * This is what gets loaded when the widget is embedded on external sites
 */

// Get event slug from URL
$eventSlug = isset($_GET['event']) ? $_GET['event'] : 'coldplay-2026';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'light';
$showBranding = isset($_GET['branding']) ? $_GET['branding'] !== 'false' : true;

// Demo event data (in production, fetch from database)
$eventData = [
    'name' => 'Coldplay: Music of the Spheres',
    'date' => '14 Feb 2026',
    'venue' => 'Arena Nationala',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=180&fit=crop',
    'tickets' => [
        ['name' => 'General Admission', 'description' => 'Acces zona generala', 'price' => 149],
        ['name' => 'VIP Experience', 'description' => 'Meet & greet inclus', 'price' => 499]
    ]
];

// Include config for asset function
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TICS Widget - <?= htmlspecialchars($eventData['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        * { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Inter', system-ui, sans-serif; margin: 0; padding: 0; }
        .ticket-option { transition: all 0.2s ease; }
        .ticket-option:hover { border-color: #6366f1; }
        .ticket-option.selected { border-color: #6366f1; background: rgba(99, 102, 241, 0.05); }
    </style>
</head>
<body class="<?= $theme === 'dark' ? 'bg-gray-900' : 'bg-white' ?>">
    <div class="<?= $theme === 'dark' ? 'bg-gray-800' : 'bg-white' ?> rounded-lg overflow-hidden" style="max-width: 400px">
        <div class="h-1 bg-gradient-to-r from-indigo-600 to-purple-600"></div>
        <div class="p-4">
            <img src="<?= htmlspecialchars($eventData['image']) ?>" class="w-full h-36 object-cover rounded-lg mb-3" alt="<?= htmlspecialchars($eventData['name']) ?>">
            <h3 class="font-bold <?= $theme === 'dark' ? 'text-white' : 'text-gray-900' ?> mb-1"><?= htmlspecialchars($eventData['name']) ?></h3>
            <p class="text-sm <?= $theme === 'dark' ? 'text-gray-400' : 'text-gray-500' ?> mb-3">📅 <?= htmlspecialchars($eventData['date']) ?> - 📍 <?= htmlspecialchars($eventData['venue']) ?></p>

            <!-- Ticket Selection -->
            <div class="space-y-2 mb-4">
                <?php foreach ($eventData['tickets'] as $index => $ticket): ?>
                <label class="ticket-option flex items-center justify-between p-3 border <?= $theme === 'dark' ? 'border-gray-700' : 'border-gray-200' ?> rounded-lg cursor-pointer <?= $index === 0 ? 'selected' : '' ?>" data-price="<?= $ticket['price'] ?>">
                    <div class="flex items-center gap-3">
                        <input type="radio" name="ticket" value="<?= $index ?>" <?= $index === 0 ? 'checked' : '' ?> class="text-indigo-600">
                        <div>
                            <p class="text-sm font-medium <?= $theme === 'dark' ? 'text-white' : 'text-gray-900' ?>"><?= htmlspecialchars($ticket['name']) ?></p>
                            <p class="text-xs <?= $theme === 'dark' ? 'text-gray-400' : 'text-gray-500' ?>"><?= htmlspecialchars($ticket['description']) ?></p>
                        </div>
                    </div>
                    <span class="font-bold <?= $theme === 'dark' ? 'text-white' : 'text-gray-900' ?>"><?= number_format($ticket['price']) ?> RON</span>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Quantity -->
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm <?= $theme === 'dark' ? 'text-gray-400' : 'text-gray-500' ?>">Cantitate:</span>
                <div class="flex items-center gap-3">
                    <button onclick="updateQuantity(-1)" class="w-8 h-8 border <?= $theme === 'dark' ? 'border-gray-700 text-white hover:bg-gray-700' : 'border-gray-200 hover:bg-gray-50' ?> rounded-lg transition-colors">-</button>
                    <span id="quantity" class="font-bold <?= $theme === 'dark' ? 'text-white' : 'text-gray-900' ?>">2</span>
                    <button onclick="updateQuantity(1)" class="w-8 h-8 border <?= $theme === 'dark' ? 'border-gray-700 text-white hover:bg-gray-700' : 'border-gray-200 hover:bg-gray-50' ?> rounded-lg transition-colors">+</button>
                </div>
            </div>

            <!-- Total & Button -->
            <div class="flex items-center justify-between mb-4">
                <span class="<?= $theme === 'dark' ? 'text-gray-400' : 'text-gray-500' ?>">Total:</span>
                <span id="total" class="text-xl font-bold text-indigo-600">298 RON</span>
            </div>
            <a href="/checkout?event=<?= urlencode($eventSlug) ?>" target="_blank" class="block w-full py-3 bg-indigo-600 text-white text-center font-bold rounded-lg hover:bg-indigo-700 transition-colors">
                Cumpara bilete
            </a>

            <?php if ($showBranding): ?>
            <!-- Footer -->
            <div class="flex items-center justify-center gap-2 mt-4 pt-4 border-t <?= $theme === 'dark' ? 'border-gray-700' : 'border-gray-100' ?>">
                <div class="w-4 h-4 bg-indigo-600 rounded flex items-center justify-center">
                    <span class="text-white text-[8px] font-bold">T</span>
                </div>
                <span class="text-xs <?= $theme === 'dark' ? 'text-gray-500' : 'text-gray-400' ?>">Securizat de TICS.ro</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let quantity = 2;
        const prices = <?= json_encode(array_column($eventData['tickets'], 'price')) ?>;
        let selectedTicketIndex = 0;

        function updateQuantity(delta) {
            quantity = Math.max(1, Math.min(10, quantity + delta));
            document.getElementById('quantity').textContent = quantity;
            updateTotal();
        }

        function updateTotal() {
            const total = prices[selectedTicketIndex] * quantity;
            document.getElementById('total').textContent = total.toLocaleString('ro-RO') + ' RON';
        }

        // Ticket selection
        document.querySelectorAll('.ticket-option').forEach((option, index) => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.ticket-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                selectedTicketIndex = index;
                updateTotal();
            });
        });
    </script>
</body>
</html>
