<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Confidențialitate — ' . SITE_NAME;
$resp = api_get('/tenant-client/privacy', [], 600);
$content = ($resp['success'] && !empty($resp['data']['content'])) ? $resp['data']['content'] : null;
$pageExtraStyles = '
    .legal-content h2 { font-family: "Playfair Display", serif; font-size: 1.6rem; color: #FFFEF2; margin: 2.5rem 0 1rem; }
    .legal-content h3 { font-family: "Playfair Display", serif; font-size: 1.2rem; color: #D4AF37; margin: 1.5rem 0 .75rem; }
    .legal-content p { color: rgba(255,254,242,.7); line-height: 1.8; margin-bottom: 1rem; }
    .legal-content ul { list-style: disc; padding-left: 1.5rem; color: rgba(255,254,242,.7); margin-bottom: 1rem; }
    .legal-content li { margin-bottom: .4rem; }
    .legal-content a { color: #D4AF37; }
    .legal-content table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .9rem; }
    .legal-content th, .legal-content td { border: 1px solid rgba(212,175,55,.15); padding: .6rem .8rem; text-align: left; color: rgba(255,254,242,.8); }
    .legal-content th { color: #D4AF37; background: rgba(212,175,55,.05); }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<main class="pt-32 pb-20 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Document legal</p>
        <h1 class="font-display text-4xl lg:text-5xl mb-4">Politica de confidențialitate</h1>
        <p class="text-warm-gray mb-12">Conform Regulamentului (UE) 2016/679 (GDPR)</p>
        <div class="legal-content">
            <?php if ($content): echo $content; else: ?>
                <h2>1. Operatorul de date</h2>
                <p><?= e(SITE_NAME) ?> este operatorul datelor dumneavoastră cu caracter personal în sensul GDPR. Pentru orice solicitare privind datele, ne puteți contacta la adresa de email a instituției.</p>
                <h2>2. Ce date colectăm</h2>
                <h3>2.1 Date furnizate direct</h3>
                <ul><li>Nume și prenume</li><li>Adresă de email</li><li>Număr de telefon</li><li>Date de facturare (dacă sunt solicitate)</li></ul>
                <h3>2.2 Date colectate automat</h3>
                <ul><li>Adresa IP și tipul dispozitivului</li><li>Paginile vizitate</li><li>Cookies și identificatori similari</li></ul>
                <h2>3. De ce procesăm datele</h2>
                <table>
                    <thead><tr><th>Scop</th><th>Temei legal</th></tr></thead>
                    <tbody>
                        <tr><td>Procesarea comenzilor și emiterea biletelor</td><td>Executarea contractului</td></tr>
                        <tr><td>Comunicări tranzacționale</td><td>Executarea contractului</td></tr>
                        <tr><td>Newsletter și oferte</td><td>Consimțământ</td></tr>
                        <tr><td>Îmbunătățirea serviciilor</td><td>Interes legitim</td></tr>
                    </tbody>
                </table>
                <h2>4. Drepturile dumneavoastră</h2>
                <p>Aveți dreptul de acces, rectificare, ștergere, restricționare, portabilitate și opoziție privind datele personale, precum și dreptul de a depune plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal.</p>
                <h2>5. Securitate și păstrare</h2>
                <p>Aplicăm măsuri tehnice și organizatorice adecvate pentru protejarea datelor. Datele sunt păstrate doar cât este necesar pentru scopurile menționate sau conform obligațiilor legale.</p>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php';
