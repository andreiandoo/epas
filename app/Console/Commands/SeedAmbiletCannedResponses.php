<?php

namespace App\Console\Commands;

use App\Models\Chat\ChatCannedResponse;
use App\Models\MarketplaceClient;
use Illuminate\Console\Command;

/**
 * Răspunsuri predefinite pentru chatul AmBilet, scrise după termeni, politica de retur,
 * confidențialitate, cookies, FAQ și pagina de contact de pe ambilet.ro.
 * Nu suprascrie nimic: sare peste scurtăturile care există deja.
 *
 *   php artisan chat:seed-ambilet-canned --dry-run
 *   php artisan chat:seed-ambilet-canned
 */
class SeedAmbiletCannedResponses extends Command
{
    protected $signature = 'chat:seed-ambilet-canned {--client= : ID-ul marketplace-ului (implicit cel cu domeniul ambilet)} {--dry-run : Doar arată ce s-ar adăuga}';

    protected $description = 'Adaugă răspunsurile predefinite de chat pentru AmBilet';

    public function handle(): int
    {
        $client = $this->option('client')
            ? MarketplaceClient::find((int) $this->option('client'))
            : MarketplaceClient::query()->where('domain', 'like', '%ambilet%')->first();

        if (! $client) {
            $this->error('Nu am găsit marketplace-ul AmBilet. Folosește --client=ID.');
            return self::FAILURE;
        }

        $this->info('Marketplace: ' . $client->id . ' ' . $client->name);

        $existing = ChatCannedResponse::query()
            ->where('marketplace_client_id', $client->id)
            ->pluck('shortcut')
            ->map(fn ($s) => mb_strtolower((string) $s))
            ->all();

        $added = 0;
        foreach ($this->responses() as $i => [$shortcut, $title, $body]) {
            if (in_array(mb_strtolower($shortcut), $existing, true)) {
                $this->line('  există deja: ' . $shortcut);
                continue;
            }

            $this->line('  + ' . $shortcut . '  ' . $title);
            if (! $this->option('dry-run')) {
                ChatCannedResponse::query()->create([
                    'marketplace_client_id' => $client->id,
                    'support_department_id' => null,
                    'shortcut' => $shortcut,
                    'title' => $title,
                    'body' => $body,
                    'sort_order' => 100 + $i,
                    'is_active' => true,
                ]);
            }
            $added++;
        }

        $this->info(($this->option('dry-run') ? 'De adăugat: ' : 'Adăugate: ') . $added);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function responses(): array
    {
        $site = 'https://ambilet.ro';

        return [
            ['/salut', 'Salut', 'Bună! Mulțumim că ne-ai scris. Cu ce te pot ajuta?'],

            ['/comanda', 'Cer datele comenzii', 'Mă uit acum pe comanda ta. Îmi dai te rog numărul comenzii sau adresa de email cu care ai cumpărat?'],

            ['/moment', 'Un moment, verific', 'Îmi ia un minut să verific. Rămâi te rog pe chat, revin imediat.'],

            ['/nuamprimit', 'Nu am primit biletele',
                "Biletele pleacă pe email imediat după plată, dar livrarea poate dura până la 15 minute. Verifică te rog și folderul Spam sau Promoții.\n\n"
                . "Le găsești oricând și în contul tău, la Biletele mele: {$site}/cont/bilete"],

            ['/bilete', 'Unde găsesc biletele',
                "Biletele le primești pe email, în PDF, și le ai mereu în cont, la Biletele mele: {$site}/cont/bilete\n\n"
                . 'La intrare arăți codul QR direct de pe telefon sau printat. Nu ai nevoie de altceva.'],

            ['/emailgresit', 'Email greșit la comandă',
                'Dacă ai greșit adresa de email la comandă, scrie-mi adresa corectă și numărul comenzii. Verific comanda și îți trimit biletele pe adresa bună.'],

            ['/debitat', 'Bani retrași, fără bilete',
                'Îmi pare rău pentru situație. Trimite-mi te rog numărul comenzii sau emailul folosit la plată și verific imediat dacă plata a fost confirmată de procesator.'],

            ['/platanereusita', 'Plata nu a mers',
                "Când plata nu trece, de obicei ajută una dintre variantele de mai jos:\n"
                . "• verifică datele cardului și confirmarea 3D Secure din aplicația băncii;\n"
                . "• verifică dacă ai fonduri și dacă limita pentru plăți online nu e depășită;\n"
                . "• încearcă alt card sau Apple Pay / Google Pay.\n\n"
                . 'Dacă tot nu merge, spune-mi ce mesaj apare și mă uit.'],

            ['/plata', 'Metode de plată',
                'Poți plăti cu cardul (Visa, Mastercard, Maestro), prin NETOPIA Payments, sau cu Apple Pay și Google Pay. La unele evenimente se acceptă și cardul cultural (Edenred, Sodexo, Up România), iar opțiunea apare la plată doar acolo unde e activă.'],

            ['/retur', 'Biletele nu se returnează',
                "Conform OUG 34/2014, art. 16 lit. l), biletele pentru evenimente nu se pot returna. Dacă nu mai poți ajunge, contravaloarea biletului nu se rambursează, dar poți transfera biletul altei persoane din contul tău.\n\n"
                . "Taxa de procesare nu se rambursează, nici dacă evenimentul se anulează. Politica completă: {$site}/politica-retur"],

            ['/anulat', 'Eveniment anulat',
                "Când un eveniment se anulează, te anunțăm pe email, iar rambursarea se face conform condițiilor comunicate de organizator. Taxa de procesare nu se rambursează.\n\n"
                . "Poți cere rambursarea din cont, la Comenzile mele → Solicită rambursare: {$site}/cont/cerere-rambursare"],

            ['/amanat', 'Eveniment amânat',
                'Când un eveniment se amână, te anunțăm pe email cu noua dată. Dacă nu poți ajunge la noua dată, trimite-mi numărul comenzii și verific cu organizatorul ce variante ai.'],

            ['/rambursare', 'Cum cer o rambursare',
                "Rambursarea se cere din cont, la Comenzile mele → Solicită rambursare: {$site}/cont/cerere-rambursare\n\n"
                . 'După aprobare, o procesăm în 1-3 zile lucrătoare, iar banii ajung de regulă pe aceeași metodă de plată în 5-14 zile lucrătoare, în funcție de bancă. Dacă nu primești banii în 14 zile de la confirmare, scrie-ne.'],

            ['/transfer', 'Transfer bilet',
                "Dacă evenimentul permite transferul, intră în cont la Biletele mele, alege biletul și apasă Transferă bilet, apoi scrie emailul persoanei care merge în locul tău: {$site}/cont/bilete\n\n"
                . 'După transfer, biletul tău inițial nu mai e valabil, iar noul bilet ajunge pe emailul destinatarului.'],

            ['/nume', 'Schimbare nume pe bilet',
                'Ca să meargă altcineva în locul tău, cel mai simplu e să-i transferi biletul din cont: Biletele mele → Transferă bilet. Biletul nou ajunge pe emailul lui. Dacă la evenimentul tău transferul nu e disponibil, spune-mi numărul comenzii și verific.'],

            ['/factura', 'Factură pe firmă',
                "Pentru factură pe firmă bifezi la plată „Vreau factură pe firmă” și introduci CUI-ul. Datele firmei se completează automat din ANAF.\n\n"
                . "Pe email primești factura pentru comision și servicii, iar organizatorul îți trimite factura pentru bilete. Dacă organizatorul e persoană fizică, el nu emite factură pentru bilete. Facturile le găsești și în cont: {$site}/cont/facturi"],

            ['/facturadupa', 'Factură cerută după comandă',
                'Dacă n-ai bifat factura pe firmă la comandă, trimite-mi numărul comenzii, denumirea firmei și CUI-ul. Verific și transmit cererea mai departe.'],

            ['/taxa', 'Ce include prețul',
                'Prețul final afișat la plată include prețul biletului stabilit de organizator, taxa de serviciu AmBilet (cu TVA) și taxa de procesare a plății. Toate apar separat în coș, înainte să plătești.'],

            ['/parola', 'Am uitat parola',
                "Poți seta o parolă nouă de aici: {$site}/parola-uitata\n\nPrimești pe email un link de resetare. Dacă nu vine în câteva minute, verifică și folderul Spam."],

            ['/stergecont', 'Ștergere cont',
                "Contul îl ștergi din Setări cont → Zona periculoasă → Șterge contul, cu parola: {$site}/cont/setari\n\n"
                . 'Datele se șterg în cel mult 30 de zile. Îți recomand să descarci întâi biletele pentru evenimentele viitoare.'],

            ['/gdpr', 'Cerere date personale (GDPR)',
                "Pentru acces, export sau ștergerea datelor personale scrie la dpo@ambilet.ro. Confirmăm cererea în 24 de ore și răspundem în cel mult 30 de zile, gratuit.\n\nDetalii: {$site}/gdpr"],

            ['/cookies', 'Setări cookies',
                "Poți schimba oricând ce cookie-uri accepți din butonul „Setări cookie-uri” din josul paginii sau din setările browserului. Cookie-urile esențiale nu pot fi dezactivate, pentru că fără ele site-ul nu funcționează.\n\nDetalii: {$site}/cookies"],

            ['/intrare', 'Acces la eveniment',
                'La intrare arăți codul QR de pe bilet, direct de pe telefon sau printat. Regulile de acces (oră, vârstă, obiecte permise) le stabilește organizatorul, așa că verifică și pagina evenimentului.'],

            ['/revanzare', 'Revânzare bilete',
                'Revânzarea biletelor la un preț mai mare decât cel de pe bilet nu e permisă. Dacă nu mai poți merge, poți transfera biletul unei persoane cunoscute din contul tău.'],

            ['/contact', 'Contact și program',
                "Ne găsești la bilete@ambilet.ro sau la +40 726 131 516.\n"
                . "Program: luni-vineri 09:00-18:00, sâmbătă 10:00-14:00. În zilele de eveniment suntem disponibili până la finalul evenimentului."],

            ['/organizator', 'Vreau să vând bilete',
                "Pentru colaborare ca organizator scrie-ne la organizatori@ambilet.ro. Comisioanele și planurile sunt aici: {$site}/comisioane"],

            ['/tichet', 'Deschid un tichet',
                'Am nevoie de câteva verificări în plus. Am deschis un tichet cu discuția noastră și revenim pe email cât mai repede, de regulă în 24 de ore.'],

            ['/multumesc', 'Încheiere',
                'Mă bucur că am rezolvat. Dacă mai ai nevoie de ceva, ne scrii oricând aici. O zi frumoasă!'],
        ];
    }
}
