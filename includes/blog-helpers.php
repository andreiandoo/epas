<?php
/**
 * Shared bits for /blog and /blog/{slug}. Articles come from the admin
 * (Marketplace > Blog) through /blog-articles.
 */

const BLOG_AUTHOR = 'Redacția AmBilet';

/** Cover path from the admin (public disk) or a full URL => absolute URL, null when there is none. */
function blog_image_url(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return STORAGE_URL . '/' . ltrim(preg_replace('#^/?storage/#', '', $path), '/');
}

/** "2026-10-22T09:00:00Z" => "22 octombrie 2026" */
function blog_date(?string $iso): string
{
    if (!$iso || !($ts = strtotime($iso))) {
        return '';
    }
    $months = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

    return date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function blog_url(string $slug): string
{
    return '/blog/' . rawurlencode($slug);
}

/** Submits every form[data-newsletter] on the page to the same endpoint the footer form uses. */
function blog_newsletter_script(): void
{
    ?>
    <script>
    document.querySelectorAll('form[data-newsletter]').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            var msg = form.querySelector('[data-newsletter-msg]');
            var button = form.querySelector('button[type="submit"]');
            var label = button.textContent;
            button.disabled = true;
            button.textContent = 'Se trimite...';
            try {
                if (typeof AmbiletAPI === 'undefined') throw new Error('A apărut o eroare. Încearcă din nou.');
                await AmbiletAPI.post('/newsletter/subscribe', { email: form.email.value.trim() });
                msg.textContent = 'Gata, te-ai abonat. Îți scriem când apar articole și evenimente noi.';
                msg.className = 'mt-3 text-sm text-white';
                form.reset();
            } catch (err) {
                msg.textContent = (err && err.message) || 'A apărut o eroare. Încearcă din nou.';
                msg.className = 'mt-3 text-sm text-red-200';
            } finally {
                button.disabled = false;
                button.textContent = label;
            }
        });
    });
    </script>
    <?php
}
