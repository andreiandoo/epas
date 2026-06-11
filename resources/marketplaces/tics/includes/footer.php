<?php
/**
 * TICS.ro - Footer Component
 */
?>

<!-- Footer -->
<footer class="bg-gray-900 text-white mt-16">
    <div class="max-w-[1600px] mx-auto px-4 lg:px-8 py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8">
            <div class="col-span-2 lg:col-span-1">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
                        <span class="text-gray-900 font-bold text-sm">T</span>
                    </div>
                    <span class="font-bold text-lg">TICS</span>
                </div>
                <p class="text-gray-400 text-sm mb-4">Descoperă evenimente unice. Powered by Tixello.</p>
                <div class="flex gap-3">
                    <a href="https://facebook.com/tics.ro" target="_blank" rel="noopener" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </a>
                    <a href="https://instagram.com/tics.ro" target="_blank" rel="noopener" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="https://twitter.com/ticsro" target="_blank" rel="noopener" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Evenimente</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                    <li><a href="/evenimente/concerte" class="hover:text-white transition-colors">Concerte</a></li>
                    <li><a href="/evenimente/festivaluri" class="hover:text-white transition-colors">Festivaluri</a></li>
                    <li><a href="/evenimente/stand-up" class="hover:text-white transition-colors">Stand-up</a></li>
                    <li><a href="/evenimente/teatru" class="hover:text-white transition-colors">Teatru</a></li>
                    <li><a href="/evenimente/sport" class="hover:text-white transition-colors">Sport</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Orașe</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                    <li><a href="<?= cityUrl('bucuresti') ?>" class="hover:text-white transition-colors">București</a></li>
                    <li><a href="<?= cityUrl('cluj-napoca') ?>" class="hover:text-white transition-colors">Cluj-Napoca</a></li>
                    <li><a href="<?= cityUrl('timisoara') ?>" class="hover:text-white transition-colors">Timișoara</a></li>
                    <li><a href="<?= cityUrl('iasi') ?>" class="hover:text-white transition-colors">Iași</a></li>
                    <li><a href="<?= cityUrl('constanta') ?>" class="hover:text-white transition-colors">Constanța</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Companie</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                    <li><a href="/despre" class="hover:text-white transition-colors">Despre noi</a></li>
                    <li><a href="/contact" class="hover:text-white transition-colors">Contact</a></li>
                    <li><a href="/cariere" class="hover:text-white transition-colors">Cariere</a></li>
                    <li><a href="/blog" class="hover:text-white transition-colors">Blog</a></li>
                    <li><a href="/organizatori" class="hover:text-white transition-colors">Organizatori</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Suport</h4>
                <ul class="space-y-2 text-gray-400 text-sm">
                    <li><a href="/ajutor" class="hover:text-white transition-colors">Ajutor</a></li>
                    <li><a href="/termeni" class="hover:text-white transition-colors">Termeni și condiții</a></li>
                    <li><a href="/confidentialitate" class="hover:text-white transition-colors">Politica de confidențialitate</a></li>
                    <li><a href="/retur" class="hover:text-white transition-colors">Retur bilete</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-10 pt-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="text-gray-400 text-sm">© <?= date('Y') ?> TICS.ro. Toate drepturile rezervate. Powered by <a href="https://tixello.com" class="text-white hover:underline" target="_blank" rel="noopener">Tixello</a>.</p>
            <div class="flex items-center gap-4">
                <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" class="h-6 opacity-60">
                <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" class="h-6 opacity-60">
                <span class="text-gray-500 text-sm">Apple Pay</span>
                <span class="text-gray-500 text-sm">Google Pay</span>
            </div>
        </div>
    </div>
</footer>

<?php include __DIR__ . '/accessibility.php'; ?>
<?php include __DIR__ . '/cookie-consent.php'; ?>
