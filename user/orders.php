<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Comenzile mele';
$currentPage = 'orders';
$cssBundle = 'account';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .order-details { display: none; }
    .order-card.expanded .order-details { display: block; }
    .order-card .expand-icon { transition: transform 0.2s ease; }
    .order-card.expanded .expand-icon { transform: rotate(180deg); }
</style>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Comenzile mele</h1>
                <p class="mt-1 text-sm text-muted">Istoric complet al achizițiilor tale</p>
            </div>
            <div class="flex items-center gap-2">
                <select id="filter-status" class="px-4 py-2 text-sm border bg-surface border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate comenzile</option>
                    <option value="confirmed">Confirmate</option>
                    <option value="pending">În așteptare</option>
                    <option value="refunded">Rambursate</option>
                </select>
            </div>
        </div>

        <!-- Orders Stats -->
        <div class="grid grid-cols-3 gap-3 mb-6 mobile:grid-cols-1 lg:gap-4">
            <div class="p-4 text-center bg-white border rounded-xl border-border mobile:p-2">
                <p id="stat-total" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-xs text-muted">Total comenzi</p>
            </div>
            <div class="p-4 text-center bg-white border rounded-xl border-border mobile:p-2">
                <p id="stat-spent" class="text-2xl font-bold text-success">0 lei</p>
                <p class="text-xs text-muted">Cheltuit total</p>
            </div>
            <div class="p-4 text-center bg-white border rounded-xl border-border mobile:p-2">
                <p id="stat-saved" class="text-2xl font-bold text-accent">0 lei</p>
                <p class="text-xs text-muted">Economisit</p>
            </div>
        </div>

        <!-- Orders List -->
        <div id="orders-list" class="space-y-4">
            <div class="py-8 text-center">
                <div class="w-8 h-8 mx-auto border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                <p class="mt-2 text-muted">Se încarcă comenzile...</p>
            </div>
        </div>

        <!-- Load More -->
        <div id="load-more" class="hidden mt-8 text-center">
            <button class="px-6 py-2.5 bg-surface text-secondary font-medium rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                Încarcă mai multe comenzi
            </button>
        </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/user-orders.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
