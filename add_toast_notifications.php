<?php

$file = 'resources/tenant-client/src/core/Router.ts';
$content = file_get_contents($file);

// Add toast helper class after CartService
$toastClass = "
class ToastNotification {
    static show(message: string, type: 'success' | 'error' | 'info' = 'success'): void {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50 transform transition-all duration-300 translate-y-0 opacity-100`;

        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-blue-600'
        };

        toast.classList.add(colors[type]);
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('translate-y-2', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
";

$content = str_replace('export class Router {', $toastClass . "\nexport class Router {", $content);

// Replace alerts with toast notifications
$content = str_replace(
    "alert('Biletele au fost adăugate în coș!');",
    "ToastNotification.show('✓ Biletele au fost adăugate în coș!', 'success');",
    $content
);

$content = str_replace(
    "alert('Te rog selectează cel puțin un bilet.');",
    "ToastNotification.show('Te rog selectează cel puțin un bilet.', 'error');",
    $content
);

file_put_contents($file, $content);

echo "Toast notifications added!\n";
