<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lista „Ce primești” pentru fiecare microserviciu care apare la /inregistrare,
 * în română și engleză. Se completează doar unde lista lipsește; ce e deja scris
 * rămâne neatins.
 */
return new class extends Migration
{
    private const FEATURES = [
        'ticket-customizer' => [
            'ro' => ['Editor vizual, cu tras și plasat', 'Logo, imagini, texte, cod QR și cod de bare pe bilet', 'Previzualizare pe loc, la fel ca biletul final', 'Șabloane salvate, pe care le refolosești la alte evenimente'],
            'en' => ['Drag-and-drop visual editor', 'Logo, images, text, QR code and barcode on the ticket', 'Live preview that matches the final ticket', 'Saved templates you reuse for other events'],
        ],
        'tracking-pixels-manager' => [
            'ro' => ['Google Analytics 4 și Google Tag Manager', 'Meta Pixel și TikTok Pixel', 'Bara de acord pentru cookie-uri, conform GDPR', 'Vânzările apar ca vânzări în rapoartele reclamelor'],
            'en' => ['Google Analytics 4 and Google Tag Manager', 'Meta Pixel and TikTok Pixel', 'GDPR cookie consent bar', 'Sales show up as purchases in your ad reports'],
        ],
        'whatsapp-notifications' => [
            'ro' => ['Confirmarea comenzii pe WhatsApp', 'Remindere cu 7, 3 și o zi înainte de eveniment', 'Campanii doar către cei care au acceptat mesaje', 'Șabloane de mesaje aprobate de WhatsApp'],
            'en' => ['Order confirmation on WhatsApp', 'Reminders 7, 3 and 1 day before the event', 'Campaigns only to people who opted in', 'WhatsApp-approved message templates'],
        ],
        'affiliate-tracking' => [
            'ro' => ['Link propriu pentru fiecare partener', 'Vânzările atribuite la ultimul click, cu cookie de 90 de zile', 'Comision calculat automat pentru fiecare partener', 'Panou unde partenerul își vede vânzările'],
            'en' => ['A personal link for every partner', 'Last-click attribution with a 90-day cookie', 'Commission calculated automatically per partner', 'A dashboard where partners see their sales'],
        ],
        'accounting-connectors' => [
            'ro' => ['Legătură cu SmartBill, Oblio, FGO sau Keez', 'Factura se emite automat la fiecare vânzare', 'Serii și conturi mapate o singură dată', 'Fără facturi copiate de mână'],
            'en' => ['Connects to SmartBill, Oblio, FGO or Keez', 'An invoice is issued automatically for every sale', 'Series and accounts mapped once', 'No more invoices copied by hand'],
        ],
        'invitations' => [
            'ro' => ['Invitații în număr mare sau dintr-un fișier CSV', 'Trimise pe email ca PDF, cu cod de intrare', 'Vezi cine a descărcat invitația și cine a intrat', 'Merg și pe locuri numerotate'],
            'en' => ['Invitations in bulk or from a CSV file', 'Sent by email as a PDF with an entry code', 'See who downloaded it and who checked in', 'Works with reserved seating too'],
        ],
        'ticket-insurance' => [
            'ro' => ['Asigurare opțională, oferită la plată', 'Prețul asigurării calculat pe loc', 'Polițele se emit și se gestionează automat', 'Cumpărătorul alege dacă o vrea'],
            'en' => ['Optional insurance offered at checkout', 'Insurance price calculated instantly', 'Policies issued and managed automatically', 'Buyers choose whether they want it'],
        ],
        'door-sales' => [
            'ro' => ['Vânzare la intrare, cash sau card', 'Locurile se sincronizează pe loc cu vânzarea online', 'Merge pe tabletă sau telefon, și fără internet', 'Raport zilnic pe fiecare casă'],
            'en' => ['Sell at the door, cash or card', 'Seats stay in sync with online sales', 'Runs on a tablet or phone, even offline', 'Daily report for every till'],
        ],
        'crm' => [
            'ro' => ['Istoricul fiecărui client: ce a cumpărat și când', 'Grupuri de clienți după comportament', 'Campanii de email către grupurile alese', 'Valoarea fiecărui client în timp'],
            'en' => ['Every customer\'s history: what they bought and when', 'Customer groups based on behaviour', 'Email campaigns to the groups you pick', 'Lifetime value per customer'],
        ],
        'analytics' => [
            'ro' => ['Vânzările în timp real', 'De unde vin cumpărătorii și ce cumpără', 'Comparații între evenimente', 'Rapoarte pe care le construiești singur'],
            'en' => ['Sales in real time', 'Where buyers come from and what they buy', 'Comparisons between events', 'Reports you build yourself'],
        ],
        'group-booking' => [
            'ro' => ['Comenzi mari pentru grupuri, într-un singur pas', 'Reduceri automate după mărimea grupului', 'Lista participanților pentru fiecare grup', 'Potrivit pentru școli, firme și agenții'],
            'en' => ['Large group orders in one step', 'Automatic discounts by group size', 'Attendee list for every group', 'Good for schools, companies and agencies'],
        ],
        'waitlist' => [
            'ro' => ['Listă de așteptare pentru evenimentele epuizate', 'Anunț automat când apar bilete', 'Vezi câți oameni așteaptă la fiecare eveniment', 'Vezi câtă cerere ai rămas neacoperită'],
            'en' => ['Waitlist for sold-out events', 'Automatic alert when tickets free up', 'See how many people are waiting for each event', 'See how much demand you couldn\'t cover'],
        ],
        'mobile-wallet' => [
            'ro' => ['Biletul în Apple Wallet și Google Wallet', 'Cumpărătorul îl are mereu în telefon, fără aplicație', 'Se actualizează singur dacă se schimbă ora sau locul', 'Fără PDF căutat prin email la intrare'],
            'en' => ['Tickets in Apple Wallet and Google Wallet', 'Always on the buyer’s phone, no app needed', 'Updates itself if the time or venue changes', 'No hunting for a PDF at the door'],
        ],
        'website-visual-editor' => [
            'ro' => ['Culori, fonturi și aranjare pentru tot site-ul', 'Pagini noi construite din blocuri', 'Peste 25 de blocuri: program, galerii, numărătoare inversă', 'Vezi modificările înainte să le publici'],
            'en' => ['Colours, fonts and layout for the whole site', 'New pages built from blocks', '25+ blocks: schedule, galleries, countdown', 'Preview changes before you publish'],
        ],
        'slack-integration' => [
            'ro' => ['Mesaj în Slack la fiecare comandă', 'Noutăți despre evenimente și clienți', 'Canalele le alegi tu', 'Echipa află pe loc ce se întâmplă'],
            'en' => ['A Slack message for every order', 'Updates about events and customers', 'You choose the channels', 'Your team knows what\'s happening right away'],
        ],
        'google-workspace-integration' => [
            'ro' => ['Evenimentele apar în Google Calendar', 'Documentele se salvează în Google Drive', 'Emailuri trimise din contul tău Gmail'],
            'en' => ['Events show up in Google Calendar', 'Documents saved to Google Drive', 'Emails sent from your Gmail account'],
        ],
        'google-sheets-integration' => [
            'ro' => ['Comenzile și biletele, direct în Google Sheets', 'Tabelul se actualizează singur', 'Rapoarte pentru contabil fără export manual', 'Îl împarți cu cine vrei din echipă'],
            'en' => ['Orders and tickets straight into Google Sheets', 'The sheet updates itself', 'Reports for your accountant with no manual export', 'Share it with anyone on your team'],
        ],
        'blog' => [
            'ro' => ['Articole pe site-ul tău de bilete', 'Categorii, etichete și autori', 'Titluri și descrieri pentru Google', 'Istoricul modificărilor pentru fiecare articol'],
            'en' => ['Articles on your ticketing site', 'Categories, tags and authors', 'Titles and descriptions for Google', 'Revision history for every article'],
        ],
        'coupon-codes' => [
            'ro' => ['Coduri de reducere procentuale sau cu sumă fixă', 'Coduri unice generate în număr mare', 'Limite: pe eveniment, pe perioadă, pe număr de folosiri', 'Vezi câte vânzări a adus fiecare cod'],
            'en' => ['Percentage or fixed-amount discount codes', 'Unique codes generated in bulk', 'Limits by event, period or number of uses', 'See how many sales each code brought'],
        ],
        'gamification' => [
            'ro' => ['Puncte pentru fiecare cumpărare', 'Puncte bonus la recomandări și zile de naștere', 'Niveluri de client', 'Punctele se folosesc direct la plată'],
            'en' => ['Points for every purchase', 'Bonus points for referrals and birthdays', 'Customer tiers', 'Points redeemed right at checkout'],
        ],
        'shop' => [
            'ro' => ['Tricouri, produse și extra-uri lângă bilete', 'Același coș și aceeași plată cu biletele', 'Stocuri urmărite automat', 'Catalog cu poze și prețuri'],
            'en' => ['Merch, products and extras next to tickets', 'Same cart and checkout as tickets', 'Stock tracked automatically', 'Catalogue with photos and prices'],
        ],
        'knowledge-base' => [
            'ro' => ['Pagină de ajutor pe site-ul tău', 'Articole și întrebări frecvente pe categorii', 'Căutare în articole', 'Mai puține emailuri cu aceleași întrebări'],
            'en' => ['A help page on your site', 'Articles and FAQs by category', 'Search across articles', 'Fewer emails asking the same things'],
        ],
        'sms-notifications' => [
            'ro' => ['SMS de confirmare la cumpărare', 'Remindere înainte de eveniment', 'Campanii SMS către clienții care au acceptat', 'Plătești doar SMS-urile trimise'],
            'en' => ['Confirmation SMS on purchase', 'Reminders before the event', 'SMS campaigns to customers who opted in', 'You only pay for SMS sent'],
        ],
        'facebook-capi-integration' => [
            'ro' => ['Vânzările ajung la Meta direct de pe server', 'Măsurare corectă și cu blocare de reclame sau pe iPhone', 'Fără vânzări numărate de două ori', 'Reclame mai bine optimizate pe cumpărători reali'],
            'en' => ['Sales sent to Meta straight from the server', 'Accurate tracking even with ad blockers or on iPhone', 'No double-counted sales', 'Ads optimised on real buyers'],
        ],
        'leisure-multi-society' => [
            'ro' => ['Mai multe firme sub același cont', 'Fiecare produs se facturează pe firma lui', 'O singură comandă, facturi separate automat'],
            'en' => ['Several companies under one account', 'Each product invoiced by its own company', 'One order, separate invoices automatically'],
        ],
        'zoom-integration' => [
            'ro' => ['Întâlnire sau webinar Zoom creat automat', 'Participanții se sincronizează automat', 'Prezența urmărită automat', 'Înregistrările la un loc'],
            'en' => ['Zoom meeting or webinar created automatically', 'Attendees synced automatically', 'Attendance tracked automatically', 'Recordings in one place'],
        ],
        'live-chat' => [
            'ro' => ['Fereastră de chat pe site-ul tău', 'Răspunzi din panou, tu sau echipa', 'Merge pe telefon și pe calculator', 'Istoricul conversațiilor păstrat'],
            'en' => ['A chat window on your site', 'You or your team reply from the panel', 'Works on phone and desktop', 'Conversation history kept'],
        ],
        'customers-export' => [
            'ro' => ['Clienții tăi într-un fișier CSV, deschis în Excel', 'Nume, email, telefon, oraș, comenzi, total cheltuit', 'Și lista abonaților la newsletter', 'Fiecare export rămâne în jurnalul contului'],
            'en' => ['Your customers in a CSV file that opens in Excel', 'Name, email, phone, city, orders, total spent', 'Your newsletter subscribers too', 'Every export is recorded in the account log'],
        ],
    ];

    public function up(): void
    {
        foreach (self::FEATURES as $slug => $lists) {
            $row = DB::table('microservices')->where('slug', $slug)->first();
            if (! $row) {
                continue;
            }

            $current = json_decode((string) ($row->features ?? ''), true);
            $current = is_array($current) ? $current : [];
            // Unele seedere vechi au salvat o listă simplă, fără limbi: o considerăm engleză
            if ($current && array_is_list($current)) {
                $current = ['en' => $current];
            }

            $changed = false;
            foreach ($lists as $locale => $items) {
                if (empty(array_filter((array) ($current[$locale] ?? []), fn ($f) => is_string($f) && trim($f) !== ''))) {
                    $current[$locale] = $items;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('microservices')->where('slug', $slug)
                    ->update(['features' => json_encode($current, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Doar completări de text; nu se revine
    }
};
