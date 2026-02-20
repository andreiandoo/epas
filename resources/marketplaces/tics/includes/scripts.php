<?php
/**
 * TICS.ro - Scripts include file
 * Include at the bottom of every page
 *
 * Variables:
 * - $scriptsExtra (optional): Additional scripts to include
 */
?>

<!-- Core JavaScript -->
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script src="<?= asset('assets/js/utils.js') ?>"></script>

<!-- Components -->
<script src="<?= asset('assets/js/components/search.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-card.js') ?>"></script>
<script src="<?= asset('assets/js/components/event-promo-card.js') ?>"></script>

<!-- Initialize Search -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof TicsSearch !== 'undefined') {
        TicsSearch.init();
    }
});
</script>

<!-- Page-specific scripts -->
<?php if (isset($scriptsExtra)) echo $scriptsExtra; ?>

<!-- AI Chat Widget -->
<script src="<?= asset('assets/js/chat-widget.js') ?>"></script>

</body>
</html>
