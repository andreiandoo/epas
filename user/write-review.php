<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Scrie o recenzie';
$currentPage = 'reviews';
$cssBundle = 'account';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Container -->
<div class="px-4 py-6 mx-auto max-w-2xl lg:py-8">
    <!-- Event Preview -->
    <div class="flex flex-col items-center gap-4 p-5 mb-6 text-center bg-white border sm:flex-row sm:text-left rounded-xl border-border" id="event-preview">
        <div class="flex items-center justify-center flex-shrink-0 w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500" id="event-image">
            <svg class="w-10 h-10 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
        </div>
        <div>
            <h2 class="mb-1 text-lg font-bold text-secondary" id="event-title">---</h2>
            <div class="space-y-1">
                <div class="flex items-center justify-center gap-2 text-sm sm:justify-start text-muted">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span id="event-date">---</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-sm sm:justify-start text-muted">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span id="event-venue">---</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Form -->
    <form class="overflow-hidden bg-white border rounded-xl border-border" id="reviewForm">
        <div class="p-5 border-b border-border">
            <h1 class="text-xl font-bold text-secondary">Spune-ne cum a fost!</h1>
            <p class="mt-1 text-sm text-muted">Recenzia ta ajuta alti utilizatori sa ia decizii informate.</p>
        </div>

        <div class="p-5 space-y-6">
            <!-- Overall Rating -->
            <div class="p-6 text-center rounded-xl bg-gradient-to-r from-yellow-100 to-amber-100">
                <h3 class="mb-4 font-semibold text-amber-800">Rating general</h3>
                <div class="flex justify-center gap-2 overall-stars" id="overall-rating">
                    <label class="cursor-pointer hover:scale-110 transition-transform" data-rating="1">
                        <svg class="w-10 h-10 text-amber-600 opacity-30 hover:opacity-100" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </label>
                    <label class="cursor-pointer hover:scale-110 transition-transform" data-rating="2">
                        <svg class="w-10 h-10 text-amber-600 opacity-30 hover:opacity-100" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </label>
                    <label class="cursor-pointer hover:scale-110 transition-transform" data-rating="3">
                        <svg class="w-10 h-10 text-amber-600 opacity-30 hover:opacity-100" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </label>
                    <label class="cursor-pointer hover:scale-110 transition-transform" data-rating="4">
                        <svg class="w-10 h-10 text-amber-600 opacity-30 hover:opacity-100" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </label>
                    <label class="cursor-pointer hover:scale-110 transition-transform" data-rating="5">
                        <svg class="w-10 h-10 text-amber-600 opacity-30 hover:opacity-100" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </label>
                </div>
                <div class="mt-3 text-sm font-semibold text-amber-800" id="rating-text">Selecteaza rating-ul</div>
                <input type="hidden" name="rating" id="rating-value" value="">
            </div>

            <!-- Detailed Ratings -->
            <div>
                <label class="label">Evalueaza detaliat <span class="font-normal text-muted">(optional)</span></label>
                <div class="space-y-3">
                    <div class="flex flex-col items-center justify-between gap-3 p-4 sm:flex-row rounded-xl bg-surface">
                        <span class="text-sm font-medium text-gray-600">Calitatea show-ului</span>
                        <div class="flex gap-1 star-rating-small" data-name="show">
                            <label data-rating="1"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="2"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="3"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="4"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="5"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                        </div>
                    </div>
                    <div class="flex flex-col items-center justify-between gap-3 p-4 sm:flex-row rounded-xl bg-surface">
                        <span class="text-sm font-medium text-gray-600">Locatie & Venue</span>
                        <div class="flex gap-1 star-rating-small" data-name="venue">
                            <label data-rating="1"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="2"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="3"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="4"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="5"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                        </div>
                    </div>
                    <div class="flex flex-col items-center justify-between gap-3 p-4 sm:flex-row rounded-xl bg-surface">
                        <span class="text-sm font-medium text-gray-600">Organizare</span>
                        <div class="flex gap-1 star-rating-small" data-name="organization">
                            <label data-rating="1"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="2"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="3"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="4"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="5"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                        </div>
                    </div>
                    <div class="flex flex-col items-center justify-between gap-3 p-4 sm:flex-row rounded-xl bg-surface">
                        <span class="text-sm font-medium text-gray-600">Raport calitate/pret</span>
                        <div class="flex gap-1 star-rating-small" data-name="value">
                            <label data-rating="1"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="2"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="3"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="4"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                            <label data-rating="5"><svg class="w-6 h-6 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review Text -->
            <div>
                <label class="label" for="review-text">Spune-ne mai multe</label>
                <textarea class="input min-h-[150px]" id="review-text" name="text" placeholder="Descrie experienta ta la acest eveniment. Ce ti-a placut? Ce ar fi putut fi mai bine?"></textarea>
                <div class="mt-1 text-xs text-right text-muted"><span id="char-count">0</span> / 2000 caractere</div>
            </div>

            <!-- Photo Upload -->
            <div>
                <label class="label">Adauga fotografii <span class="font-normal text-muted">(optional)</span></label>
                <label class="flex flex-col items-center p-6 text-center border-2 border-dashed cursor-pointer rounded-xl border-border hover:border-primary hover:bg-red-50 transition-colors">
                    <input type="file" class="hidden" id="photo-upload" multiple accept="image/*">
                    <div class="flex items-center justify-center w-12 h-12 mb-3 rounded-full bg-surface">
                        <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="text-sm text-muted"><strong class="text-primary">Click pentru a incarca</strong> sau trage fotografiile aici</div>
                    <div class="mt-1 text-xs text-muted">PNG, JPG pana la 5MB fiecare (max. 5 fotografii)</div>
                </label>
                <div class="flex flex-wrap gap-2 mt-3" id="photo-preview"></div>
            </div>

            <!-- Options -->
            <div>
                <label class="label">Optiuni</label>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" class="mt-1 checkbox" name="recommend" checked>
                        <span class="text-sm text-muted"><strong class="block text-secondary">Recomand acest eveniment</strong>As sugera prietenilor sa participe la evenimente similare</span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" class="mt-1 checkbox" name="anonymous">
                        <span class="text-sm text-muted"><strong class="block text-secondary">Recenzie anonima</strong>Numele tau nu va fi afisat public</span>
                    </label>
                </div>
            </div>

            <!-- Tips -->
            <div class="p-4 border rounded-xl bg-blue-50 border-blue-200">
                <h4 class="flex items-center gap-2 mb-2 text-sm font-semibold text-blue-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Sfaturi pentru o recenzie utila
                </h4>
                <ul class="space-y-1 text-sm text-blue-700">
                    <li class="flex items-center gap-2"><span class="text-blue-500">&#10003;</span> Fii specific - mentioneaza detalii concrete despre experienta</li>
                    <li class="flex items-center gap-2"><span class="text-blue-500">&#10003;</span> Fii onest - atat aspectele pozitive cat si cele negative ajuta</li>
                    <li class="flex items-center gap-2"><span class="text-blue-500">&#10003;</span> Evita spoilere - nu dezvalui surprize sau momente cheie</li>
                </ul>
            </div>
        </div>

        <!-- Form Footer -->
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between bg-surface border-t border-border">
            <div class="flex items-center gap-2 text-sm text-muted">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Recenzia va fi moderata inainte de publicare
            </div>
            <div class="flex gap-3">
                <a href="/cont/recenzii" class="btn btn-secondary">Anuleaza</a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Publica recenzia
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const WriteReviewPage = {
    eventId: null,
    selectedRating: 0,
    detailedRatings: {},
    photos: [],

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=' + window.location.pathname;
            return;
        }

        this.eventId = new URLSearchParams(window.location.search).get('event') || new URLSearchParams(window.location.search).get('edit');
        if (this.eventId) {
            this.loadEvent();
        }

        this.setupRatingStars();
        this.setupDetailedRatings();
        this.setupCharCounter();
        this.setupPhotoUpload();
        this.setupFormSubmit();
    },

    async loadEvent() {
        try {
            const response = await AmbiletAPI.get('/events/' + this.eventId);
            if (response.success && response.event) {
                document.getElementById('event-title').textContent = response.event.title;
                document.getElementById('event-date').textContent = response.event.date;
                document.getElementById('event-venue').textContent = response.event.venue;
            }
        } catch (error) {
            // Demo data
            document.getElementById('event-title').textContent = 'Coldplay - Music of the Spheres World Tour';
            document.getElementById('event-date').textContent = 'Sambata, 15 Iunie 2025';
            document.getElementById('event-venue').textContent = 'Arena Nationala, Bucuresti';
        }
    },

    setupRatingStars() {
        const container = document.getElementById('overall-rating');
        const ratingTexts = { 1: 'Dezamagitor', 2: 'Acceptabil', 3: 'Bine', 4: 'Foarte bine', 5: 'Exceptional!' };

        container.querySelectorAll('label').forEach(label => {
            label.addEventListener('click', () => {
                this.selectedRating = parseInt(label.dataset.rating);
                document.getElementById('rating-value').value = this.selectedRating;
                document.getElementById('rating-text').textContent = ratingTexts[this.selectedRating];
                this.updateOverallStars();
            });
        });
    },

    updateOverallStars() {
        const container = document.getElementById('overall-rating');
        container.querySelectorAll('label').forEach(label => {
            const rating = parseInt(label.dataset.rating);
            const svg = label.querySelector('svg');
            svg.classList.toggle('opacity-100', rating <= this.selectedRating);
            svg.classList.toggle('opacity-30', rating > this.selectedRating);
        });
    },

    setupDetailedRatings() {
        document.querySelectorAll('.star-rating-small').forEach(container => {
            const name = container.dataset.name;
            container.querySelectorAll('label').forEach(label => {
                label.classList.add('cursor-pointer', 'hover:scale-110', 'transition-transform');
                label.addEventListener('click', () => {
                    const rating = parseInt(label.dataset.rating);
                    this.detailedRatings[name] = rating;
                    this.updateDetailedStars(container, rating);
                });
            });
        });
    },

    updateDetailedStars(container, selectedRating) {
        container.querySelectorAll('label').forEach(label => {
            const rating = parseInt(label.dataset.rating);
            const svg = label.querySelector('svg');
            svg.classList.toggle('text-yellow-400', rating <= selectedRating);
            svg.classList.toggle('text-gray-200', rating > selectedRating);
        });
    },

    setupCharCounter() {
        const textarea = document.getElementById('review-text');
        const counter = document.getElementById('char-count');
        textarea.addEventListener('input', () => {
            counter.textContent = textarea.value.length;
        });
    },

    setupPhotoUpload() {
        const input = document.getElementById('photo-upload');
        const preview = document.getElementById('photo-preview');

        input.addEventListener('change', (e) => {
            const files = Array.from(e.target.files).slice(0, 5 - this.photos.length);
            files.forEach(file => {
                if (file.size <= 5 * 1024 * 1024) {
                    this.photos.push(file);
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        const div = document.createElement('div');
                        div.className = 'relative w-20 h-20 rounded-lg overflow-hidden';
                        div.innerHTML = `
                            <img src="${ev.target.result}" class="object-cover w-full h-full">
                            <button type="button" class="absolute top-1 right-1 w-5 h-5 bg-black/60 rounded-full flex items-center justify-center" onclick="WriteReviewPage.removePhoto(${this.photos.length - 1})">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    },

    removePhoto(index) {
        this.photos.splice(index, 1);
        const preview = document.getElementById('photo-preview');
        preview.innerHTML = '';
        this.photos.forEach((file, i) => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const div = document.createElement('div');
                div.className = 'relative w-20 h-20 rounded-lg overflow-hidden';
                div.innerHTML = `
                    <img src="${ev.target.result}" class="object-cover w-full h-full">
                    <button type="button" class="absolute top-1 right-1 w-5 h-5 bg-black/60 rounded-full flex items-center justify-center" onclick="WriteReviewPage.removePhoto(${i})">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    },

    setupFormSubmit() {
        document.getElementById('reviewForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!this.selectedRating) {
                AmbiletNotifications.error('Te rugam sa selectezi un rating general.');
                return;
            }

            const text = document.getElementById('review-text').value;
            if (text.length < 20) {
                AmbiletNotifications.error('Recenzia trebuie sa aiba cel putin 20 de caractere.');
                return;
            }

            const formData = new FormData();
            formData.append('event_id', this.eventId);
            formData.append('rating', this.selectedRating);
            formData.append('text', text);
            formData.append('detailed_ratings', JSON.stringify(this.detailedRatings));
            formData.append('recommend', document.querySelector('input[name="recommend"]').checked);
            formData.append('anonymous', document.querySelector('input[name="anonymous"]').checked);
            this.photos.forEach((photo, i) => formData.append('photos[]', photo));

            try {
                const response = await AmbiletAPI.post('/customer/reviews', formData);
                if (response.success) {
                    AmbiletNotifications.success('Recenzia a fost trimisa si va fi publicata dupa moderare!');
                    setTimeout(() => window.location.href = '/cont/recenzii', 2000);
                } else {
                    AmbiletNotifications.error(response.message || 'Eroare la trimiterea recenziei.');
                }
            } catch (error) {
                AmbiletNotifications.error('Eroare la trimiterea recenziei.');
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => WriteReviewPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
