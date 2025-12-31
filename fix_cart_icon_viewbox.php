<?php

$file = 'resources/tenant-client/src/templates/default.ts';
$content = file_get_contents($file);

// Fix the SVG viewBox to match the path coordinates
$oldSvg = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" transform="scale(0.047)" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>';

$newSvg = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="30">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"/>
                                </svg>';

$content = str_replace($oldSvg, $newSvg, $content);

file_put_contents($file, $content);

echo "✓ Fixed cart icon SVG viewBox\n";
