<?php
/**
 * Tailwind CSS Configuration
 *
 * This file contains the Tailwind CSS CDN and configuration.
 * Included in head.php for centralized theme management.
 *
 * Theme colors are pulled from config.php $THEME array.
 */
?>
<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    'sans': ['Plus Jakarta Sans', 'sans-serif']
                },
                colors: {
                    'primary': '<?= $THEME['primary'] ?>',
                    'primary-dark': '<?= $THEME['primary_dark'] ?>',
                    'primary-light': '<?= $THEME['primary_light'] ?>',
                    'secondary': '<?= $THEME['secondary'] ?>',
                    'accent': '<?= $THEME['accent'] ?>',
                    'surface': '<?= $THEME['surface'] ?>',
                    'muted': '<?= $THEME['muted'] ?>',
                    'border': '<?= $THEME['border'] ?>',
                    'success': '<?= $THEME['success'] ?>',
                    'warning': '<?= $THEME['warning'] ?>',
                    'error': '<?= $THEME['error'] ?>',
                }
            }
        }
    }
</script>
