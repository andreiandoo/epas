<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AmbiletKnowledgeBaseSeeder extends Seeder
{
    private int $marketplaceClientId = 1;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding AmBilet Knowledge Base...');

        // Create categories
        $categories = $this->createCategories();

        // Create articles for each category
        $this->createArticles($categories);

        $this->command->info('✓ AmBilet Knowledge Base seeded successfully');
    }

    private function createCategories(): array
    {
        $categoriesData = [
            [
                'name' => ['ro' => 'Pentru Organizatori', 'en' => 'For Organizers'],
                'slug' => 'pentru-organizatori',
                'description' => ['ro' => 'Ghiduri și informații pentru organizatorii de evenimente', 'en' => 'Guides and information for event organizers'],
                'icon' => 'heroicon-o-user-group',
                'color' => '#8B5CF6',
                'sort_order' => 1,
            ],
            [
                'name' => ['ro' => 'Rambursări', 'en' => 'Refunds'],
                'slug' => 'rambursari',
                'description' => ['ro' => 'Informații despre politica de rambursare și cum să soliciți o rambursare', 'en' => 'Information about refund policy and how to request a refund'],
                'icon' => 'heroicon-o-banknotes',
                'color' => '#EF4444',
                'sort_order' => 2,
            ],
            [
                'name' => ['ro' => 'Evenimente', 'en' => 'Events'],
                'slug' => 'evenimente',
                'description' => ['ro' => 'Tot ce trebuie să știi despre evenimentele de pe AmBilet', 'en' => 'Everything you need to know about events on AmBilet'],
                'icon' => 'heroicon-o-calendar',
                'color' => '#10B981',
                'sort_order' => 3,
            ],
            [
                'name' => ['ro' => 'Cont & Profil', 'en' => 'Account & Profile'],
                'slug' => 'cont-profil',
                'description' => ['ro' => 'Gestionarea contului tău AmBilet', 'en' => 'Managing your AmBilet account'],
                'icon' => 'heroicon-o-user',
                'color' => '#3B82F6',
                'sort_order' => 4,
            ],
            [
                'name' => ['ro' => 'Plăți', 'en' => 'Payments'],
                'slug' => 'plati',
                'description' => ['ro' => 'Metode de plată și informații despre tranzacții', 'en' => 'Payment methods and transaction information'],
                'icon' => 'heroicon-o-credit-card',
                'color' => '#F59E0B',
                'sort_order' => 5,
            ],
            [
                'name' => ['ro' => 'Bilete', 'en' => 'Tickets'],
                'slug' => 'bilete',
                'description' => ['ro' => 'Informații despre bilete, validare și acces la evenimente', 'en' => 'Information about tickets, validation and event access'],
                'icon' => 'heroicon-o-ticket',
                'color' => '#EC4899',
                'sort_order' => 6,
            ],
        ];

        $categories = [];

        foreach ($categoriesData as $data) {
            // Use updateOrInsert to handle existing categories
            DB::table('kb_categories')->updateOrInsert(
                [
                    'marketplace_client_id' => $this->marketplaceClientId,
                    'slug' => $data['slug'],
                ],
                [
                    'name' => json_encode($data['name']),
                    'description' => json_encode($data['description']),
                    'icon' => $data['icon'],
                    'color' => $data['color'],
                    'sort_order' => $data['sort_order'],
                    'is_visible' => true,
                    'updated_at' => now(),
                ]
            );

            // Get the ID (either existing or newly created)
            $category = DB::table('kb_categories')
                ->where('marketplace_client_id', $this->marketplaceClientId)
                ->where('slug', $data['slug'])
                ->first();

            $categories[$data['slug']] = $category->id;
            $this->command->info("  ✓ Category: {$data['name']['ro']}");
        }

        return $categories;
    }

    private function createArticles(array $categories): void
    {
        $articles = [
            // ============================================
            // PENTRU ORGANIZATORI
            // ============================================
            [
                'category' => 'pentru-organizatori',
                'type' => 'article',
                'title' => ['ro' => 'Cum să devii organizator pe AmBilet', 'en' => 'How to become an organizer on AmBilet'],
                'slug' => 'cum-sa-devii-organizator',
                'content' => ['ro' => '<h2>Înregistrarea ca Organizator</h2>
<p>AmBilet oferă o platformă profesională pentru organizatorii de evenimente care doresc să vândă bilete online. Iată pașii pentru a deveni organizator:</p>

<h3>1. Creează un cont de organizator</h3>
<p>Accesează pagina de înregistrare pentru organizatori și completează formularul cu datele tale și ale companiei.</p>

<h3>2. Verificare și aprobare</h3>
<p>Echipa AmBilet va verifica datele introduse și documentele furnizate. Procesul durează de obicei 1-2 zile lucrătoare.</p>

<h3>3. Configurează profilul</h3>
<p>După aprobare, completează profilul cu logo-ul companiei, descriere și informații de contact.</p>

<h3>4. Adaugă primul eveniment</h3>
<p>Folosește panoul de administrare pentru a crea primul tău eveniment și a configura categoriile de bilete.</p>

<h3>Beneficii pentru organizatori:</h3>
<ul>
<li>Comisioane competitive</li>
<li>Plăți rapide și sigure</li>
<li>Dashboard cu statistici în timp real</li>
<li>Suport tehnic dedicat</li>
<li>Promovare pe platforma AmBilet</li>
</ul>', 'en' => '<h2>Registering as an Organizer</h2><p>AmBilet offers a professional platform for event organizers who want to sell tickets online.</p>'],
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'pentru-organizatori',
                'type' => 'article',
                'title' => ['ro' => 'Ghid pentru crearea unui eveniment', 'en' => 'Guide to creating an event'],
                'slug' => 'ghid-creare-eveniment',
                'content' => ['ro' => '<h2>Crearea unui eveniment pe AmBilet</h2>

<h3>Pasul 1: Informații de bază</h3>
<p>Completează numele evenimentului, data, ora și locația. Adaugă o descriere atractivă care să capteze atenția potențialilor participanți.</p>

<h3>Pasul 2: Imagine și materiale</h3>
<p>Încarcă o imagine de cover de înaltă calitate (minim 1200x630 pixeli). Aceasta va fi afișată pe pagina evenimentului și în promovări.</p>

<h3>Pasul 3: Categorii de bilete</h3>
<p>Creează categoriile de bilete dorite:</p>
<ul>
<li><strong>Early Bird</strong> - bilete la preț redus pentru primii cumpărători</li>
<li><strong>Standard</strong> - bilete cu preț normal</li>
<li><strong>VIP</strong> - bilete premium cu beneficii suplimentare</li>
</ul>

<h3>Pasul 4: Setări avansate</h3>
<ul>
<li>Limită de bilete per comandă</li>
<li>Data de început și sfârșit a vânzărilor</li>
<li>Coduri promoționale</li>
<li>Termeni și condiții specifice</li>
</ul>

<h3>Pasul 5: Publicare</h3>
<p>Verifică toate informațiile și publică evenimentul. Acesta va fi vizibil imediat pe platformă.</p>', 'en' => '<h2>Creating an event on AmBilet</h2><p>Follow these steps to create your event.</p>'],
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'category' => 'pentru-organizatori',
                'type' => 'faq',
                'question' => ['ro' => 'Ce comision percepe AmBilet pentru vânzarea biletelor?', 'en' => 'What commission does AmBilet charge for ticket sales?'],
                'slug' => 'comision-ambilet',
                'content' => ['ro' => '<p>Comisionul AmBilet depinde de tipul de colaborare ales:</p>
<ul>
<li><strong>Exclusiv (1%)</strong> - pentru organizatorii care vând bilete exclusiv prin AmBilet</li>
<li><strong>Mixt (2%)</strong> - pentru organizatorii care vând și prin alte canale</li>
<li><strong>Revânzător (3%)</strong> - pentru agenții și revânzători</li>
</ul>
<p>Comisionul se calculează din prețul biletului și poate fi inclus în preț sau adăugat separat, la alegerea organizatorului.</p>', 'en' => '<p>Commission depends on partnership type: Exclusive (1%), Mixed (2%), or Reseller (3%).</p>'],
                'sort_order' => 3,
            ],
            [
                'category' => 'pentru-organizatori',
                'type' => 'faq',
                'question' => ['ro' => 'Când și cum primesc banii din vânzarea biletelor?', 'en' => 'When and how do I receive money from ticket sales?'],
                'slug' => 'plata-organizatori',
                'content' => ['ro' => '<p>Plățile către organizatori se procesează astfel:</p>
<ul>
<li><strong>Evenimente viitoare</strong> - plata se face în maxim 7 zile lucrătoare după finalizarea evenimentului</li>
<li><strong>Plăți anticipate</strong> - disponibile pentru organizatori verificați, cu istoric bun pe platformă</li>
</ul>
<p>Suma minimă pentru retragere este de 100 RON. Plățile se fac prin transfer bancar în contul IBAN specificat în profil.</p>
<p>Poți urmări în timp real situația financiară din secțiunea "Sold & Plăți" din panoul de administrare.</p>', 'en' => '<p>Payments are processed within 7 business days after the event. Minimum withdrawal is 100 RON.</p>'],
                'sort_order' => 4,
            ],
            [
                'category' => 'pentru-organizatori',
                'type' => 'faq',
                'question' => ['ro' => 'Cum pot anula sau amâna un eveniment?', 'en' => 'How can I cancel or postpone an event?'],
                'slug' => 'anulare-amanare-eveniment',
                'content' => ['ro' => '<p>Pentru a anula sau amâna un eveniment:</p>
<ol>
<li>Accesează panoul de administrare și selectează evenimentul</li>
<li>Folosește opțiunea "Amână eveniment" sau "Anulează eveniment"</li>
<li>Completează motivul și noua dată (pentru amânare)</li>
<li>Confirmă acțiunea</li>
</ol>
<p><strong>Important:</strong></p>
<ul>
<li>La anulare, toți participanții vor fi notificați automat și vor primi rambursare completă</li>
<li>La amânare, participanții pot alege să păstreze biletele pentru noua dată sau să solicite rambursare</li>
</ul>
<p>Te rugăm să ne contactezi înainte pentru a coordona procesul, mai ales pentru evenimente mari.</p>', 'en' => '<p>You can cancel or postpone events from the admin panel. All attendees will be notified automatically.</p>'],
                'sort_order' => 5,
            ],

            // ============================================
            // RAMBURSĂRI
            // ============================================
            [
                'category' => 'rambursari',
                'type' => 'article',
                'title' => ['ro' => 'Politica de rambursare AmBilet', 'en' => 'AmBilet Refund Policy'],
                'slug' => 'politica-rambursare',
                'content' => ['ro' => '<h2>Politica de Rambursare</h2>

<h3>Rambursări pentru evenimente anulate</h3>
<p>Dacă un eveniment este anulat de către organizator, vei primi automat rambursare completă (100% din prețul biletului) în termen de 14 zile lucrătoare.</p>

<h3>Rambursări pentru evenimente amânate</h3>
<p>În cazul amânării unui eveniment, ai două opțiuni:</p>
<ul>
<li>Păstrezi biletul pentru noua dată</li>
<li>Soliciți rambursare completă în termen de 30 de zile de la anunțarea amânării</li>
</ul>

<h3>Rambursări din motive personale</h3>
<p>Pentru biletele achiziționate pentru evenimente care nu au fost anulate sau amânate:</p>
<ul>
<li>Rambursările depind de politica fiecărui organizator</li>
<li>Unii organizatori permit rambursări până la o anumită dată înainte de eveniment</li>
<li>Verifică termenii și condițiile specifice evenimentului</li>
</ul>

<h3>Cum solicitezi o rambursare?</h3>
<ol>
<li>Autentifică-te în contul tău AmBilet</li>
<li>Accesează secțiunea "Biletele mele"</li>
<li>Selectează biletul pentru care dorești rambursare</li>
<li>Apasă "Solicită rambursare" și completează formularul</li>
</ol>', 'en' => '<h2>Refund Policy</h2><p>Full refunds are provided for cancelled events. For postponed events, you can keep the ticket or request a refund.</p>'],
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'rambursari',
                'type' => 'faq',
                'question' => ['ro' => 'Cât durează procesarea unei rambursări?', 'en' => 'How long does refund processing take?'],
                'slug' => 'durata-rambursare',
                'content' => ['ro' => '<p>Timpul de procesare a rambursărilor:</p>
<ul>
<li><strong>Rambursări automate (evenimente anulate)</strong>: 5-14 zile lucrătoare</li>
<li><strong>Rambursări solicitate</strong>: 7-21 zile lucrătoare din momentul aprobării</li>
</ul>
<p>Suma va fi returnată pe același card/metodă de plată folosită la achiziție. Dacă cardul a expirat sau nu mai este valid, te vom contacta pentru detalii alternative.</p>', 'en' => '<p>Automatic refunds: 5-14 business days. Requested refunds: 7-21 business days after approval.</p>'],
                'sort_order' => 2,
            ],
            [
                'category' => 'rambursari',
                'type' => 'faq',
                'question' => ['ro' => 'Pot transfera biletul altei persoane în loc să cer rambursare?', 'en' => 'Can I transfer the ticket to someone else instead of requesting a refund?'],
                'slug' => 'transfer-bilet',
                'content' => ['ro' => '<p>Da, în multe cazuri poți transfera biletul unei alte persoane:</p>
<ol>
<li>Accesează "Biletele mele" în contul tău</li>
<li>Selectează biletul pe care vrei să-l transferi</li>
<li>Apasă "Transferă bilet"</li>
<li>Introdu adresa de email a persoanei care va primi biletul</li>
<li>Confirmă transferul</li>
</ol>
<p><strong>Notă:</strong> Unele evenimente pot avea restricții privind transferul biletelor. Verifică termenii evenimentului înainte de a încerca transferul.</p>', 'en' => '<p>Yes, you can transfer tickets through the "My Tickets" section. Some events may have transfer restrictions.</p>'],
                'sort_order' => 3,
            ],
            [
                'category' => 'rambursari',
                'type' => 'faq',
                'question' => ['ro' => 'Se rambursează și comisionul de servicii?', 'en' => 'Is the service fee also refunded?'],
                'slug' => 'rambursare-comision',
                'content' => ['ro' => '<p>Politica privind comisionul de servicii:</p>
<ul>
<li><strong>Evenimente anulate</strong>: Se rambursează integral, inclusiv comisionul de servicii</li>
<li><strong>Evenimente amânate</strong>: Se rambursează integral dacă soliciți rambursare</li>
<li><strong>Rambursări din motive personale</strong>: Comisionul de servicii poate fi reținut, în funcție de politica organizatorului</li>
</ul>', 'en' => '<p>Service fees are fully refunded for cancelled events. For personal refunds, fees may be retained depending on organizer policy.</p>'],
                'sort_order' => 4,
            ],

            // ============================================
            // EVENIMENTE
            // ============================================
            [
                'category' => 'evenimente',
                'type' => 'article',
                'title' => ['ro' => 'Cum să găsești evenimente pe AmBilet', 'en' => 'How to find events on AmBilet'],
                'slug' => 'gaseste-evenimente',
                'content' => ['ro' => '<h2>Descoperă evenimente pe AmBilet</h2>

<h3>Căutare și filtrare</h3>
<p>Folosește bara de căutare pentru a găsi evenimente după:</p>
<ul>
<li>Nume eveniment</li>
<li>Artist/performer</li>
<li>Locație/venue</li>
<li>Oraș</li>
</ul>

<h3>Filtre disponibile</h3>
<ul>
<li><strong>Categorie</strong>: Concerte, Teatru, Stand-up, Sport, Festivaluri etc.</li>
<li><strong>Dată</strong>: Astăzi, Mâine, Acest weekend, Luna aceasta</li>
<li><strong>Locație</strong>: Selectează orașul sau zona</li>
<li><strong>Preț</strong>: Gratuite, sub 50 RON, 50-100 RON etc.</li>
</ul>

<h3>Recomandări personalizate</h3>
<p>Dacă ai cont pe AmBilet, vei primi recomandări bazate pe:</p>
<ul>
<li>Evenimentele anterioare la care ai participat</li>
<li>Categoriile tale preferate</li>
<li>Artiștii urmăriți</li>
</ul>', 'en' => '<h2>Discover events on AmBilet</h2><p>Use search and filters to find events by name, artist, location, or category.</p>'],
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'evenimente',
                'type' => 'faq',
                'question' => ['ro' => 'Ce fac dacă evenimentul este sold out?', 'en' => 'What do I do if the event is sold out?'],
                'slug' => 'eveniment-sold-out',
                'content' => ['ro' => '<p>Dacă evenimentul dorit este sold out, ai câteva opțiuni:</p>
<ul>
<li><strong>Înscrie-te pe lista de așteptare</strong> - Vei fi notificat dacă devin disponibile bilete (de exemplu, din rambursări)</li>
<li><strong>Urmărește evenimentul</strong> - Organizatorul poate adăuga bilete suplimentare</li>
<li><strong>Verifică periodic</strong> - Uneori biletele devin disponibile aproape de data evenimentului</li>
</ul>
<p><strong>Atenție:</strong> Te sfătuim să nu cumperi bilete de la revânzători neautorizați. Acestea pot fi invalide sau la prețuri nejustificat de mari.</p>', 'en' => '<p>Join the waitlist or follow the event for updates. Avoid unauthorized resellers.</p>'],
                'sort_order' => 2,
            ],
            [
                'category' => 'evenimente',
                'type' => 'faq',
                'question' => ['ro' => 'Cum aflu dacă un eveniment este amânat sau anulat?', 'en' => 'How do I find out if an event is postponed or cancelled?'],
                'slug' => 'notificari-evenimente',
                'content' => ['ro' => '<p>Te vom notifica prin multiple canale dacă un eveniment la care ai bilet este modificat:</p>
<ul>
<li><strong>Email</strong> - Vei primi email la adresa asociată contului</li>
<li><strong>SMS</strong> - Dacă ai furnizat numărul de telefon</li>
<li><strong>Notificare în cont</strong> - Verifică secțiunea "Notificări"</li>
<li><strong>Pagina evenimentului</strong> - Statusul va fi actualizat vizibil</li>
</ul>
<p>Asigură-te că datele de contact din profil sunt actualizate pentru a primi notificările.</p>', 'en' => '<p>You will be notified via email, SMS, and in-app notifications. Keep your contact info updated.</p>'],
                'sort_order' => 3,
            ],
            [
                'category' => 'evenimente',
                'type' => 'faq',
                'question' => ['ro' => 'Pot participa la eveniment cu copii minori?', 'en' => 'Can I attend events with minor children?'],
                'slug' => 'evenimente-minori',
                'content' => ['ro' => '<p>Politica privind minorii variază în funcție de eveniment:</p>
<ul>
<li>Verifică secțiunea "Restricții de vârstă" pe pagina evenimentului</li>
<li>Unele evenimente sunt exclusiv pentru adulți (18+)</li>
<li>Alte evenimente permit accesul minorilor însoțiți de un adult</li>
<li>Copiii sub o anumită vârstă pot avea acces gratuit (verifică detaliile)</li>
</ul>
<p>Dacă nu găsești informații clare, contactează organizatorul înainte de a achiziționa bilete.</p>', 'en' => '<p>Age restrictions vary by event. Check the event page for specific policies regarding minors.</p>'],
                'sort_order' => 4,
            ],

            // ============================================
            // CONT & PROFIL
            // ============================================
            [
                'category' => 'cont-profil',
                'type' => 'article',
                'title' => ['ro' => 'Crearea și gestionarea contului AmBilet', 'en' => 'Creating and managing your AmBilet account'],
                'slug' => 'gestionare-cont',
                'content' => ['ro' => '<h2>Contul tău AmBilet</h2>

<h3>Crearea contului</h3>
<p>Poți crea un cont AmBilet în câteva secunde:</p>
<ul>
<li>Cu adresa de email și o parolă</li>
<li>Folosind contul Google</li>
<li>Folosind contul Facebook</li>
</ul>

<h3>Ce poți face cu un cont AmBilet?</h3>
<ul>
<li>Cumpără bilete rapid, fără a reintroduce datele</li>
<li>Accesează toate biletele tale într-un singur loc</li>
<li>Primește recomandări personalizate</li>
<li>Urmărește artiști și organizatori</li>
<li>Salvează evenimente favorite</li>
<li>Gestionează notificările</li>
</ul>

<h3>Setări de profil</h3>
<p>Din setările contului poți:</p>
<ul>
<li>Actualiza datele personale</li>
<li>Schimba parola</li>
<li>Gestiona metodele de plată salvate</li>
<li>Configura preferințele de notificare</li>
<li>Descărca datele tale (GDPR)</li>
<li>Șterge contul</li>
</ul>', 'en' => '<h2>Your AmBilet Account</h2><p>Create an account with email, Google, or Facebook to access all features.</p>'],
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'cont-profil',
                'type' => 'faq',
                'question' => ['ro' => 'Am uitat parola. Cum o pot reseta?', 'en' => 'I forgot my password. How can I reset it?'],
                'slug' => 'resetare-parola',
                'content' => ['ro' => '<p>Pentru a reseta parola:</p>
<ol>
<li>Accesează pagina de autentificare</li>
<li>Apasă pe "Am uitat parola"</li>
<li>Introdu adresa de email asociată contului</li>
<li>Verifică inbox-ul (și folderul Spam) pentru email-ul de resetare</li>
<li>Urmează link-ul din email pentru a seta o nouă parolă</li>
</ol>
<p>Link-ul de resetare este valid 24 de ore. Dacă nu primești email-ul, contactează suportul.</p>', 'en' => '<p>Click "Forgot password" on the login page and follow the email instructions to reset.</p>'],
                'sort_order' => 2,
            ],
            [
                'category' => 'cont-profil',
                'type' => 'faq',
                'question' => ['ro' => 'Cum îmi schimb adresa de email?', 'en' => 'How do I change my email address?'],
                'slug' => 'schimbare-email',
                'content' => ['ro' => '<p>Pentru a schimba adresa de email:</p>
<ol>
<li>Autentifică-te în contul tău</li>
<li>Accesează "Setări cont" din meniul profilului</li>
<li>În secțiunea "Email", apasă "Modifică"</li>
<li>Introdu noua adresă de email</li>
<li>Confirmă cu parola actuală</li>
<li>Verifică noua adresă folosind link-ul trimis pe email</li>
</ol>
<p><strong>Important:</strong> Până la confirmarea noii adrese, vei continua să primești notificări pe adresa veche.</p>', 'en' => '<p>Go to Account Settings, change your email, and verify the new address via the confirmation link.</p>'],
                'sort_order' => 3,
            ],
            [
                'category' => 'cont-profil',
                'type' => 'faq',
                'question' => ['ro' => 'Cum îmi șterg contul AmBilet?', 'en' => 'How do I delete my AmBilet account?'],
                'slug' => 'stergere-cont',
                'content' => ['ro' => '<p>Pentru a șterge definitiv contul:</p>
<ol>
<li>Autentifică-te în cont</li>
<li>Accesează "Setări cont" → "Confidențialitate"</li>
<li>Apasă "Șterge contul"</li>
<li>Confirmă acțiunea</li>
</ol>
<p><strong>Atenție:</strong></p>
<ul>
<li>Ștergerea este permanentă și ireversibilă</li>
<li>Vei pierde accesul la istoricul comenzilor</li>
<li>Biletele active vor rămâne valabile (sunt asociate email-ului)</li>
<li>Datele vor fi șterse conform GDPR în maxim 30 de zile</li>
</ul>', 'en' => '<p>Go to Account Settings → Privacy → Delete Account. This action is permanent and irreversible.</p>'],
                'sort_order' => 4,
            ],

            // ============================================
            // PLĂȚI
            // ============================================
            [
                'category' => 'plati',
                'type' => 'article',
                'title' => ['ro' => 'Metode de plată acceptate pe AmBilet', 'en' => 'Payment methods accepted on AmBilet'],
                'slug' => 'metode-plata',
                'content' => ['ro' => '<h2>Metode de Plată</h2>

<h3>Carduri bancare</h3>
<p>Acceptăm toate cardurile de debit și credit:</p>
<ul>
<li>Visa</li>
<li>Mastercard</li>
<li>Maestro</li>
</ul>

<h3>Portofele digitale</h3>
<ul>
<li>Apple Pay</li>
<li>Google Pay</li>
</ul>

<h3>Plată în rate</h3>
<p>Pentru comenzi mai mari, poți plăti în rate prin partenerii noștri (disponibil pentru anumite bănci).</p>

<h3>Securitatea plăților</h3>
<p>Toate tranzacțiile sunt securizate prin:</p>
<ul>
<li>Criptare SSL/TLS</li>
<li>Autentificare 3D Secure</li>
<li>Conformitate PCI DSS</li>
</ul>
<p>Nu stocăm datele complete ale cardului. Pentru plăți rapide, poți salva cardul în cont (doar ultimele 4 cifre sunt vizibile).</p>', 'en' => '<h2>Payment Methods</h2><p>We accept Visa, Mastercard, Apple Pay, and Google Pay. All transactions are secured with 3D Secure.</p>'],
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'plati',
                'type' => 'faq',
                'question' => ['ro' => 'De ce a fost refuzată plata mea?', 'en' => 'Why was my payment declined?'],
                'slug' => 'plata-refuzata',
                'content' => ['ro' => '<p>Plata poate fi refuzată din mai multe motive:</p>
<ul>
<li><strong>Fonduri insuficiente</strong> - Verifică soldul disponibil</li>
<li><strong>Limită de tranzacționare</strong> - Cardul poate avea limite zilnice</li>
<li><strong>3D Secure eșuat</strong> - Autentificarea nu a fost completată</li>
<li><strong>Card expirat</strong> - Verifică data de expirare</li>
<li><strong>Blocare de securitate</strong> - Banca poate bloca tranzacții neobișnuite</li>
</ul>
<p><strong>Ce poți face:</strong></p>
<ol>
<li>Încearcă din nou după câteva minute</li>
<li>Folosește alt card</li>
<li>Contactează banca pentru a debloca cardul</li>
<li>Încearcă o altă metodă de plată (Apple Pay, Google Pay)</li>
</ol>', 'en' => '<p>Common reasons: insufficient funds, card limits, failed 3D Secure, expired card, or security blocks.</p>'],
                'sort_order' => 2,
            ],
            [
                'category' => 'plati',
                'type' => 'faq',
                'question' => ['ro' => 'Când voi primi factura pentru comanda mea?', 'en' => 'When will I receive the invoice for my order?'],
                'slug' => 'factura-comanda',
                'content' => ['ro' => '<p>Factura este emisă automat după finalizarea comenzii:</p>
<ul>
<li>Este trimisă pe email în maxim 24 de ore</li>
<li>O poți descărca oricând din "Comenzile mele"</li>
<li>Este disponibilă în format PDF</li>
</ul>
<p><strong>Pentru facturare pe persoană juridică:</strong></p>
<ol>
<li>Bifează opțiunea "Doresc factură pe firmă" la checkout</li>
<li>Completează datele companiei (CUI, denumire, adresă)</li>
<li>Factura va fi emisă cu datele introduse</li>
</ol>
<p><strong>Notă:</strong> Nu se pot modifica datele de facturare după emiterea facturii.</p>', 'en' => '<p>Invoices are sent automatically via email within 24 hours. You can download them from "My Orders".</p>'],
                'sort_order' => 3,
            ],
            [
                'category' => 'plati',
                'type' => 'faq',
                'question' => ['ro' => 'Pot plăti în valută străină?', 'en' => 'Can I pay in foreign currency?'],
                'slug' => 'plata-valuta',
                'content' => ['ro' => '<p>Prețurile pe AmBilet sunt afișate în RON (Lei românești).</p>
<p>Dacă folosești un card emis în altă valută:</p>
<ul>
<li>Conversia se face automat de către banca ta</li>
<li>Se poate aplica un comision de conversie valutară</li>
<li>Verifică cu banca ta cursul de schimb aplicat</li>
</ul>
<p>Pentru carduri din zona Euro, poți activa opțiunea DCC (Dynamic Currency Conversion) dacă este disponibilă la checkout.</p>', 'en' => '<p>Prices are displayed in RON. Foreign cards will be charged in RON with automatic conversion by your bank.</p>'],
                'sort_order' => 4,
            ],

            // ============================================
            // BILETE
            // ============================================
            [
                'category' => 'bilete',
                'type' => 'article',
                'title' => ['ro' => 'Cum funcționează biletele electronice AmBilet', 'en' => 'How AmBilet electronic tickets work'],
                'slug' => 'bilete-electronice',
                'content' => ['ro' => '<h2>Bilete Electronice (E-Tickets)</h2>

<h3>Ce este un bilet electronic?</h3>
<p>Biletul electronic este varianta digitală a biletului tradițional. Conține un cod QR unic care este scanat la intrarea în locație.</p>

<h3>Cum primești biletul?</h3>
<ol>
<li>Imediat după plată, biletul este generat automat</li>
<li>Îl primești pe email în format PDF</li>
<li>Este disponibil și în contul tău AmBilet, secțiunea "Biletele mele"</li>
</ol>

<h3>Cum folosești biletul?</h3>
<ul>
<li><strong>Pe telefon:</strong> Arată codul QR direct de pe ecran</li>
<li><strong>Printat:</strong> Poți printa biletul și prezenta codul QR pe hârtie</li>
</ul>

<h3>Important de știut:</h3>
<ul>
<li>Fiecare bilet are un cod QR unic</li>
<li>Codul poate fi scanat O SINGURĂ DATĂ</li>
<li>Nu distribui sau publica biletul online - poate fi folosit de altcineva!</li>
<li>Păstrează biletul până la sfârșitul evenimentului</li>
</ul>', 'en' => '<h2>Electronic Tickets</h2><p>E-tickets contain a unique QR code that is scanned at entry. Each ticket can only be scanned once.</p>'],
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'bilete',
                'type' => 'faq',
                'question' => ['ro' => 'Nu am primit biletul pe email. Ce fac?', 'en' => 'I did not receive the ticket by email. What should I do?'],
                'slug' => 'bilet-neprimiti-email',
                'content' => ['ro' => '<p>Dacă nu ai primit biletul pe email:</p>
<ol>
<li><strong>Verifică folderul Spam/Junk</strong> - Emailul poate fi filtrat automat</li>
<li><strong>Așteaptă câteva minute</strong> - Uneori livrarea poate dura până la 15 minute</li>
<li><strong>Verifică adresa de email</strong> - Asigură-te că ai introdus adresa corect la comandă</li>
<li><strong>Descarcă din cont</strong> - Autentifică-te și accesează "Biletele mele"</li>
<li><strong>Retrimite biletul</strong> - Folosește opțiunea "Retrimite pe email" din cont</li>
</ol>
<p>Dacă problema persistă, contactează suportul cu numărul comenzii.</p>', 'en' => '<p>Check spam folder, wait 15 minutes, verify email address, or download from "My Tickets" in your account.</p>'],
                'sort_order' => 2,
            ],
            [
                'category' => 'bilete',
                'type' => 'faq',
                'question' => ['ro' => 'Trebuie să printez biletul sau pot folosi telefonul?', 'en' => 'Do I need to print the ticket or can I use my phone?'],
                'slug' => 'bilet-print-telefon',
                'content' => ['ro' => '<p>Poți folosi biletul în ambele variante:</p>

<p><strong>Pe telefon (recomandat):</strong></p>
<ul>
<li>Deschide biletul din email sau din aplicație</li>
<li>Mărește luminozitatea ecranului</li>
<li>Prezintă codul QR la scanner</li>
</ul>

<p><strong>Printat:</strong></p>
<ul>
<li>Printează biletul în format A4</li>
<li>Asigură-te că codul QR este clar și nu e tăiat</li>
<li>Păstrează biletul uscat și neboțit</li>
</ul>

<p><strong>Sfat:</strong> Recomandăm să ai și o variantă printată ca backup, în cazul în care bateria telefonului se descarcă.</p>', 'en' => '<p>Both work! Phone is recommended, but print as backup in case of low battery.</p>'],
                'sort_order' => 3,
            ],
            [
                'category' => 'bilete',
                'type' => 'faq',
                'question' => ['ro' => 'Am pierdut biletul. Cum îl pot recupera?', 'en' => 'I lost my ticket. How can I recover it?'],
                'slug' => 'bilet-pierdut',
                'content' => ['ro' => '<p>Nu-ți face griji, biletul electronic poate fi recuperat ușor:</p>
<ol>
<li><strong>Din contul AmBilet:</strong> Autentifică-te și accesează "Biletele mele" pentru a descărca din nou biletul</li>
<li><strong>Retrimite pe email:</strong> Folosește opțiunea "Retrimite pe email" din detaliile comenzii</li>
<li><strong>Căutare în email:</strong> Caută în inbox după "AmBilet" sau "bilet" pentru emailul original</li>
</ol>
<p><strong>Dacă ai cumpărat fără cont:</strong></p>
<ul>
<li>Folosește funcția "Recuperează bilet" de pe site</li>
<li>Introdu email-ul și numărul de telefon folosite la comandă</li>
<li>Vei primi biletul din nou pe email</li>
</ul>', 'en' => '<p>Log into your AmBilet account and download from "My Tickets", or use "Resend by email" option.</p>'],
                'sort_order' => 4,
            ],
            [
                'category' => 'bilete',
                'type' => 'faq',
                'question' => ['ro' => 'Pot cumpăra mai multe bilete într-o singură comandă?', 'en' => 'Can I buy multiple tickets in one order?'],
                'slug' => 'bilete-multiple',
                'content' => ['ro' => '<p>Da, poți cumpăra mai multe bilete într-o singură comandă:</p>
<ul>
<li>Selectează cantitatea dorită pentru fiecare categorie de bilete</li>
<li>Limita maximă variază în funcție de eveniment (de obicei 6-10 bilete)</li>
<li>Fiecare bilet are un cod QR unic</li>
<li>Poți trimite biletele individual către prieteni direct din cont</li>
</ul>
<p><strong>Sfat:</strong> Dacă fiecare persoană vrea să aibă biletul pe numele său, folosește funcția "Adaugă detalii participant" la checkout sau transferă biletele după achiziție.</p>', 'en' => '<p>Yes, you can buy multiple tickets per order. Each ticket has a unique QR code and can be transferred individually.</p>'],
                'sort_order' => 5,
            ],
            [
                'category' => 'bilete',
                'type' => 'faq',
                'question' => ['ro' => 'Ce fac dacă codul QR nu poate fi scanat?', 'en' => 'What should I do if the QR code cannot be scanned?'],
                'slug' => 'qr-code-problema',
                'content' => ['ro' => '<p>Dacă codul QR nu poate fi scanat:</p>

<p><strong>Pe telefon:</strong></p>
<ul>
<li>Mărește luminozitatea ecranului la maxim</li>
<li>Curăță ecranul de amprente</li>
<li>Ține telefonul stabil</li>
<li>Încearcă să mărești imaginea codului</li>
</ul>

<p><strong>Pe hârtie:</strong></p>
<ul>
<li>Asigură-te că hârtia nu e boțită sau udă</li>
<li>Verifică că biletul e printat complet și clar</li>
<li>Întinde hârtia pentru o scanare mai bună</li>
</ul>

<p><strong>Dacă problema persistă:</strong></p>
<ul>
<li>Solicită verificare manuală de la staff</li>
<li>Arată confirmarea comenzii din email</li>
<li>Prezintă un act de identitate</li>
</ul>', 'en' => '<p>Increase screen brightness, clean the screen, hold steady. If issues persist, ask staff for manual verification.</p>'],
                'sort_order' => 6,
            ],
        ];

        $sortCounter = [];

        foreach ($articles as $articleData) {
            $categorySlug = $articleData['category'];
            $categoryId = $categories[$categorySlug];

            // Track sort order per category
            if (!isset($sortCounter[$categorySlug])) {
                $sortCounter[$categorySlug] = 0;
            }
            $sortCounter[$categorySlug]++;

            $insertData = [
                'marketplace_client_id' => $this->marketplaceClientId,
                'kb_category_id' => $categoryId,
                'type' => $articleData['type'],
                'slug' => $articleData['slug'],
                'is_visible' => true,
                'is_featured' => $articleData['is_featured'] ?? false,
                'is_popular' => $articleData['is_popular'] ?? false,
                'sort_order' => $articleData['sort_order'] ?? $sortCounter[$categorySlug],
                'view_count' => rand(10, 500),
                'helpful_count' => rand(5, 50),
                'not_helpful_count' => rand(0, 5),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($articleData['type'] === 'article') {
                $insertData['title'] = json_encode($articleData['title']);
                $insertData['content'] = json_encode($articleData['content']);
            } else {
                // FAQ type
                $insertData['question'] = json_encode($articleData['question']);
                $insertData['content'] = json_encode($articleData['content']);
            }

            // Use updateOrInsert to handle existing articles
            DB::table('kb_articles')->updateOrInsert(
                [
                    'marketplace_client_id' => $this->marketplaceClientId,
                    'slug' => $articleData['slug'],
                ],
                $insertData
            );

            $title = $articleData['type'] === 'article'
                ? ($articleData['title']['ro'] ?? 'Article')
                : ($articleData['question']['ro'] ?? 'FAQ');
            $this->command->info("  ✓ {$articleData['type']}: " . Str::limit($title, 50));
        }

        // Update article counts
        foreach ($categories as $slug => $categoryId) {
            $count = DB::table('kb_articles')
                ->where('kb_category_id', $categoryId)
                ->count();

            DB::table('kb_categories')
                ->where('id', $categoryId)
                ->update(['article_count' => $count]);
        }
    }
}
