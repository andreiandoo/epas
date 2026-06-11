<?php
/**
 * TICS.ro - Categories Bar Component
 *
 * Include this file where you want to display the categories navigation bar.
 *
 * Variables available from config.php:
 * - $CATEGORIES: Array of all categories with name, icon, and count
 * - $filterCategory (optional): Currently selected category slug for highlighting
 * - $currentPage (optional): Current page identifier
 */
?>
<!-- Categories Bar -->
<div class="sticky top-16 z-30 bg-white border-b border-gray-200">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8">
        <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar">
            <a href="/evenimente" class="category-chip <?= ($currentPage ?? '') === 'events' && empty($filterCategory) ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium whitespace-nowrap transition-colors hover:border-gray-300" data-category="">
                Toate
            </a>
            <?php foreach ($CATEGORIES as $slug => $cat): ?>
            <a href="/evenimente/<?= e($slug) ?>" class="category-chip <?= ($filterCategory ?? '') === $slug ? 'chip-active' : '' ?> px-4 py-2 rounded-full border border-gray-200 text-sm font-medium text-gray-600 whitespace-nowrap hover:border-gray-300 transition-colors" data-category="<?= e($slug) ?>">
                <?= $cat['icon'] ?> <?= e($cat['name']) ?>
            </a>
            <?php endforeach; ?>
            <div class="flex-shrink-0 w-4 lg:hidden"></div><!-- Spacer for last item on mobile -->
        </div>
    </div>
</div>
