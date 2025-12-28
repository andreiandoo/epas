<?php

$file = 'resources/tenant-client/src/templates/default.ts';
$content = file_get_contents($file);

// Replace the cart link with ticket icon + badge
$oldCartLink = '<a href="/cart" class="text-gray-600 hover:text-primary transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </a>';

$newCartLink = '<a href="/cart" class="relative text-gray-600 hover:text-primary transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" transform="scale(0.047)" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span id="cart-badge" class="absolute -top-2 -right-2 bg-primary text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>';

$content = str_replace($oldCartLink, $newCartLink, $content);

file_put_contents($file, $content);

echo "✓ Replaced cart icon with ticket icon + badge\n";
