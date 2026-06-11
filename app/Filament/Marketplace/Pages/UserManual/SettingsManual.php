<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class SettingsManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-settings';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Setari', 'en' => 'Settings'],
            'description' => [
                'ro' => 'Ghid complet pentru configurarea marketplace-ului: date firma, branding, pagini legale, retele sociale, email, domenii, plati, profil si echipa.',
                'en' => 'Complete guide for configuring the marketplace: company details, branding, legal pages, social links, email, domains, payments, profile and team.',
            ],
            'icon' => 'heroicon-o-cog-6-tooth',
            'sections' => [
                // Section 1: Business Details
                [
                    'id' => 'business-details',
                    'title' => ['ro' => 'Date firma si setari financiare', 'en' => 'Company information and financial settings'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Setari].',
                                'en' => 'From the left sidebar menu, click on [Setari].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina de setari cu mai multe tab-uri. Primul tab [Detalii firma] este selectat implicit.',
                                'en' => 'The settings page opens with multiple tabs. The first tab [Detalii firma] is selected by default.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza datele firmei, adresa si informatiile de contact, apoi apasa [Salveaza] din partea de jos a paginii.',
                                'en' => 'Fill in the company details, address and contact information, then click [Salveaza] at the bottom of the page.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume firma', 'en' => 'Company name'],
                            'description' => [
                                'ro' => 'Denumirea oficiala a firmei, asa cum apare in documentele legale si facturile emise.',
                                'en' => 'The official company name, as it appears in legal documents and issued invoices.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => 'CUI / VAT',
                            'description' => [
                                'ro' => 'Codul unic de identificare (CUI) sau codul VAT al firmei. Se foloseste pe facturi si in rapoartele fiscale.',
                                'en' => 'The unique identification code (CUI) or VAT code of the company. Used on invoices and in tax reports.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Registrul Comertului', 'en' => 'Trade Register'],
                            'description' => [
                                'ro' => 'Numarul de inregistrare la Registrul Comertului (ex: J12/345/2020). Apare pe facturile emise.',
                                'en' => 'The Trade Register registration number (e.g. J12/345/2020). Appears on issued invoices.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Platitor TVA', 'en' => 'VAT payer'],
                            'description' => [
                                'ro' => 'Comutator care indica daca firma este platitoare de TVA. Activeaza optiunile de afisare TVA pe facturi.',
                                'en' => 'Toggle that indicates whether the company is a VAT payer. Enables VAT display options on invoices.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Mod afisare taxe', 'en' => 'Tax display mode'],
                            'description' => [
                                'ro' => 'Determina cum sunt afisate preturile: "Inclus in pret" (TVA inclus) sau "Adaugat la pret" (TVA adaugat separat). Afecteaza modul de calcul si afisare pe bilete si facturi.',
                                'en' => 'Determines how prices are displayed: "Included in price" (VAT included) or "Added to price" (VAT added separately). Affects calculation and display on tickets and invoices.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Comision fix', 'en' => 'Fixed commission'],
                            'description' => [
                                'ro' => 'Comisionul fix (in moneda selectata) care se aplica per bilet vandut, in plus fata de comisionul procentual.',
                                'en' => 'The fixed commission (in selected currency) applied per ticket sold, in addition to the percentage commission.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Banca', 'en' => 'Bank name'],
                            'description' => [
                                'ro' => 'Numele bancii la care este deschis contul firmei. Apare pe facturi.',
                                'en' => 'The name of the bank where the company account is opened. Appears on invoices.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => 'IBAN',
                            'description' => [
                                'ro' => 'Codul IBAN al contului bancar al firmei. Se foloseste pe facturi si pentru primirea platilor.',
                                'en' => 'The IBAN code of the company bank account. Used on invoices and for receiving payments.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Moneda', 'en' => 'Currency'],
                            'description' => [
                                'ro' => 'Moneda principala a marketplace-ului. Optiuni: RON, EUR, USD, GBP. Toate preturile si rapoartele vor fi afisate in aceasta moneda.',
                                'en' => 'The main currency of the marketplace. Options: RON, EUR, USD, GBP. All prices and reports will be displayed in this currency.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Strada', 'en' => 'Street'],
                            'description' => [
                                'ro' => 'Adresa sediului social al firmei (strada, numar, bloc, etc.).',
                                'en' => 'The company registered office address (street, number, building, etc.).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Oras', 'en' => 'City'],
                            'description' => [
                                'ro' => 'Orasul sediului social.',
                                'en' => 'The city of the registered office.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Judet / Stat', 'en' => 'State / County'],
                            'description' => [
                                'ro' => 'Judetul sau statul in care se afla sediul social.',
                                'en' => 'The county or state where the registered office is located.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Tara', 'en' => 'Country'],
                            'description' => [
                                'ro' => 'Tara in care este inregistrata firma.',
                                'en' => 'The country where the company is registered.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Cod postal', 'en' => 'Postal code'],
                            'description' => [
                                'ro' => 'Codul postal al sediului social.',
                                'en' => 'The postal code of the registered office.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email de contact a firmei. Poate fi afisata pe site si folosita pentru comunicarile cu clientii.',
                                'en' => 'The company contact email address. Can be displayed on the site and used for customer communications.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Telefon', 'en' => 'Phone'],
                            'description' => [
                                'ro' => 'Numarul de telefon de contact al firmei.',
                                'en' => 'The company contact phone number.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => ['ro' => 'Program de lucru', 'en' => 'Operating hours'],
                            'description' => [
                                'ro' => 'Programul de lucru al firmei, afisat pe site (ex: Luni-Vineri 09:00-18:00).',
                                'en' => 'The company working hours, displayed on the site (e.g. Monday-Friday 09:00-18:00).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                        [
                            'name' => 'Website',
                            'description' => [
                                'ro' => 'Adresa website-ului principal al firmei (URL complet, ex: https://www.exemplu.ro).',
                                'en' => 'The main company website address (full URL, e.g. https://www.example.com).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii firma', 'en' => 'Business Details'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Datele firmei (CUI, nume, adresa) sunt folosite automat pe facturile emise catre clienti si organizatori. Asigura-te ca sunt corecte si actualizate.',
                                'en' => 'Company details (VAT code, name, address) are automatically used on invoices issued to customers and organizers. Make sure they are correct and up to date.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Modul de afisare taxe ("Inclus" vs "Adaugat") afecteaza cum sunt calculate si afisate preturile pe tot site-ul. Schimbarea lui dupa ce ai bilete vandute poate crea confuzie.',
                                'en' => 'The tax display mode ("Included" vs "Added") affects how prices are calculated and displayed across the entire site. Changing it after tickets are sold may create confusion.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 2: Branding
                [
                    'id' => 'branding',
                    'title' => ['ro' => 'Branding si personalizare site', 'en' => 'Branding and site personalization'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din pagina [Setari], apasa pe tab-ul [Personalizare].',
                                'en' => 'From the [Setari] page, click on the [Personalizare] tab.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Configureaza identitatea vizuala a marketplace-ului: logo, culori, descriere si template-ul site-ului.',
                                'en' => 'Configure the visual identity of the marketplace: logo, colors, description and site template.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Dupa ce faci modificarile dorite, apasa [Salveaza] din partea de jos a paginii.',
                                'en' => 'After making the desired changes, click [Salveaza] at the bottom of the page.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Titlu site', 'en' => 'Site title'],
                            'description' => [
                                'ro' => 'Numele marketplace-ului afisat in bara de navigare, in titlul paginii (tab-ul browser-ului) si in footer.',
                                'en' => 'The marketplace name displayed in the navigation bar, page title (browser tab) and footer.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => 'Logo',
                            'description' => [
                                'ro' => 'Logo-ul principal al marketplace-ului. Apare in header-ul site-ului si pe bilete. Recomandat: format PNG sau SVG cu fundal transparent.',
                                'en' => 'The main marketplace logo. Appears in the site header and on tickets. Recommended: PNG or SVG format with transparent background.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => 'Favicon',
                            'description' => [
                                'ro' => 'Iconita mica care apare in tab-ul browser-ului. Recomandat: format ICO sau PNG de 32x32 pixeli.',
                                'en' => 'The small icon that appears in the browser tab. Recommended: ICO or PNG format at 32x32 pixels.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => ['ro' => 'Descriere site', 'en' => 'Site description'],
                            'description' => [
                                'ro' => 'Descrierea marketplace-ului folosita in meta tag-uri pentru SEO. Apare in rezultatele motoarelor de cautare.',
                                'en' => 'The marketplace description used in meta tags for SEO. Appears in search engine results.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => ['ro' => 'Slogan site', 'en' => 'Site tagline'],
                            'description' => [
                                'ro' => 'Un slogan scurt care apare sub logo sau in zona de hero a site-ului.',
                                'en' => 'A short tagline that appears under the logo or in the hero area of the site.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => ['ro' => 'Culoare primara', 'en' => 'Primary color'],
                            'description' => [
                                'ro' => 'Culoarea principala a site-ului, folosita pentru butoane, linkuri si elemente de accent. Selecteaza din color picker sau introdu codul hex (ex: #3B82F6).',
                                'en' => 'The main site color, used for buttons, links and accent elements. Select from the color picker or enter the hex code (e.g. #3B82F6).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => ['ro' => 'Culoare secundara', 'en' => 'Secondary color'],
                            'description' => [
                                'ro' => 'Culoarea secundara folosita pentru elemente complementare, fundaluri si hover-uri.',
                                'en' => 'The secondary color used for complementary elements, backgrounds and hover states.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => ['ro' => 'Template site', 'en' => 'Site template'],
                            'description' => [
                                'ro' => 'Template-ul vizual al site-ului public. Determina layout-ul, stilul si structura paginilor publice ale marketplace-ului.',
                                'en' => 'The visual template of the public site. Determines the layout, style and structure of the marketplace public pages.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                        [
                            'name' => ['ro' => 'Termeni si conditii bilete', 'en' => 'Ticket terms and conditions'],
                            'description' => [
                                'ro' => 'Textul termenilor si conditiilor care apare pe biletele emise. Foloseste editorul de text pentru formatare (bold, liste, linkuri). Acest text este diferit de pagina legala "Termeni si conditii".',
                                'en' => 'The terms and conditions text that appears on issued tickets. Use the text editor for formatting (bold, lists, links). This text is different from the legal "Terms and conditions" page.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Personalizare', 'en' => 'Personalization'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Logo-ul ar trebui sa aiba fundal transparent (PNG sau SVG) pentru a arata bine atat pe tema deschisa cat si pe tema inchisa.',
                                'en' => 'The logo should have a transparent background (PNG or SVG) to look good on both light and dark themes.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Culorile primara si secundara se aplica pe tot site-ul. Testeaza-le dupa salvare pe pagina publica pentru a verifica contrastul si lizibilitatea.',
                                'en' => 'The primary and secondary colors are applied across the entire site. Test them after saving on the public page to verify contrast and readability.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 3: Legal Pages
                [
                    'id' => 'legal-pages',
                    'title' => ['ro' => 'Pagini legale (Termeni si Confidentialitate)', 'en' => 'Legal pages (Terms & Privacy)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din pagina [Setari], apasa pe tab-ul [Pagini legale].',
                                'en' => 'From the [Setari] page, click on the [Pagini legale] tab.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza titlul si continutul pentru fiecare pagina legala: Termeni si conditii, Politica de confidentialitate.',
                                'en' => 'Fill in the title and content for each legal page: Terms and conditions, Privacy policy.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste editorul de text (RichEditor) pentru a formata continutul: titluri, paragrafe, liste, bold, italic, linkuri.',
                                'en' => 'Use the text editor (RichEditor) to format the content: headings, paragraphs, lists, bold, italic, links.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salveaza] pentru a publica modificarile. Paginile vor fi accesibile pe site la adresele /terms si /privacy.',
                                'en' => 'Click [Salveaza] to publish the changes. The pages will be accessible on the site at /terms and /privacy.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Titlu Termeni si conditii', 'en' => 'Terms and conditions title'],
                            'description' => [
                                'ro' => 'Titlul paginii de termeni si conditii, afisat in header-ul paginii si in linkurile din footer.',
                                'en' => 'The title of the terms and conditions page, displayed in the page header and footer links.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Pagini legale', 'en' => 'Legal Pages'],
                        ],
                        [
                            'name' => ['ro' => 'Continut Termeni si conditii', 'en' => 'Terms and conditions content'],
                            'description' => [
                                'ro' => 'Textul complet al termenilor si conditiilor. Poate include HTML formatat prin editorul RichEditor. Acesta este documentul legal afisat pe pagina publica /terms.',
                                'en' => 'The full text of the terms and conditions. Can include HTML formatted via the RichEditor. This is the legal document displayed on the public /terms page.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Pagini legale', 'en' => 'Legal Pages'],
                        ],
                        [
                            'name' => ['ro' => 'Titlu Politica de confidentialitate', 'en' => 'Privacy policy title'],
                            'description' => [
                                'ro' => 'Titlul paginii de politica de confidentialitate.',
                                'en' => 'The title of the privacy policy page.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Pagini legale', 'en' => 'Legal Pages'],
                        ],
                        [
                            'name' => ['ro' => 'Continut Politica de confidentialitate', 'en' => 'Privacy policy content'],
                            'description' => [
                                'ro' => 'Textul complet al politicii de confidentialitate. Foloseste editorul RichEditor pentru formatare. Afisat pe pagina publica /privacy.',
                                'en' => 'The full text of the privacy policy. Use the RichEditor for formatting. Displayed on the public /privacy page.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Pagini legale', 'en' => 'Legal Pages'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Paginile legale sunt obligatorii conform legislatiei GDPR. Asigura-te ca sunt completate inainte de a lansa marketplace-ul public.',
                                'en' => 'Legal pages are mandatory under GDPR legislation. Make sure they are filled in before launching the public marketplace.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Termenii de pe bilete (din tab-ul Personalizare) sunt diferiti de pagina legala "Termeni si conditii". Primul apare pe biletul PDF, al doilea pe site.',
                                'en' => 'Ticket terms (from the Personalization tab) are different from the legal "Terms and conditions" page. The first appears on the PDF ticket, the second on the site.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 4: Social Links
                [
                    'id' => 'social-links',
                    'title' => ['ro' => 'Linkuri retele sociale', 'en' => 'Social media links'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din pagina [Setari], apasa pe tab-ul [Retele sociale].',
                                'en' => 'From the [Setari] page, click on the [Retele sociale] tab.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza URL-urile complete ale profilurilor de pe retelele sociale dorite.',
                                'en' => 'Fill in the full URLs of the desired social media profiles.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salveaza]. Iconitele retelelor sociale vor aparea automat in footer-ul site-ului public.',
                                'en' => 'Click [Salveaza]. The social media icons will automatically appear in the footer of the public site.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Facebook',
                            'description' => [
                                'ro' => 'URL-ul complet al paginii de Facebook (ex: https://www.facebook.com/numepagina).',
                                'en' => 'The full URL of the Facebook page (e.g. https://www.facebook.com/pagename).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Retele sociale', 'en' => 'Social Links'],
                        ],
                        [
                            'name' => 'Instagram',
                            'description' => [
                                'ro' => 'URL-ul complet al profilului de Instagram (ex: https://www.instagram.com/numeprofil).',
                                'en' => 'The full URL of the Instagram profile (e.g. https://www.instagram.com/profilename).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Retele sociale', 'en' => 'Social Links'],
                        ],
                        [
                            'name' => 'Twitter / X',
                            'description' => [
                                'ro' => 'URL-ul complet al profilului de Twitter/X (ex: https://x.com/numeprofil).',
                                'en' => 'The full URL of the Twitter/X profile (e.g. https://x.com/profilename).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Retele sociale', 'en' => 'Social Links'],
                        ],
                        [
                            'name' => 'YouTube',
                            'description' => [
                                'ro' => 'URL-ul complet al canalului de YouTube (ex: https://www.youtube.com/@numecanal).',
                                'en' => 'The full URL of the YouTube channel (e.g. https://www.youtube.com/@channelname).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Retele sociale', 'en' => 'Social Links'],
                        ],
                        [
                            'name' => 'TikTok',
                            'description' => [
                                'ro' => 'URL-ul complet al profilului de TikTok (ex: https://www.tiktok.com/@numeprofil).',
                                'en' => 'The full URL of the TikTok profile (e.g. https://www.tiktok.com/@profilename).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Retele sociale', 'en' => 'Social Links'],
                        ],
                        [
                            'name' => 'LinkedIn',
                            'description' => [
                                'ro' => 'URL-ul complet al paginii de LinkedIn (ex: https://www.linkedin.com/company/numefirma).',
                                'en' => 'The full URL of the LinkedIn page (e.g. https://www.linkedin.com/company/companyname).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Retele sociale', 'en' => 'Social Links'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Doar retelele sociale cu URL completat vor fi afisate in footer-ul site-ului. Lasa campurile goale pentru retelele pe care nu le folosesti.',
                                'en' => 'Only social networks with a filled URL will be displayed in the site footer. Leave fields empty for networks you do not use.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Mail Settings
                [
                    'id' => 'mail-settings',
                    'title' => ['ro' => 'Configurare email (SMTP)', 'en' => 'Email configuration (SMTP)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din pagina [Setari], apasa pe tab-ul [Setari mail].',
                                'en' => 'From the [Setari] page, click on the [Setari mail] tab.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Selecteaza driver-ul de email dorit (SMTP, Mailgun, Postmark, Amazon SES, etc.).',
                                'en' => 'Select the desired email driver (SMTP, Mailgun, Postmark, Amazon SES, etc.).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza campurile specifice driver-ului selectat si apasa [Salveaza].',
                                'en' => 'Fill in the fields specific to the selected driver and click [Salveaza].',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Driver email', 'en' => 'Mail driver'],
                            'description' => [
                                'ro' => 'Metoda de trimitere email: SMTP (cel mai comun), Mailgun, Postmark, Amazon SES, sau altele. Fiecare driver are campuri specifice de configurare.',
                                'en' => 'Email sending method: SMTP (most common), Mailgun, Postmark, Amazon SES, or others. Each driver has specific configuration fields.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Adresa expeditor', 'en' => 'From address'],
                            'description' => [
                                'ro' => 'Adresa de email de la care sunt trimise toate email-urile automate (confirmari, notificari, newsletter-uri). Trebuie sa fie o adresa valida pe domeniul tau.',
                                'en' => 'The email address from which all automated emails are sent (confirmations, notifications, newsletters). Must be a valid address on your domain.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Nume expeditor', 'en' => 'From name'],
                            'description' => [
                                'ro' => 'Numele care apare ca expeditor in inbox-ul destinatarului (ex: "Bilete.online" sau "Echipa Ambilet").',
                                'en' => 'The name that appears as sender in the recipient\'s inbox (e.g. "Bilete.online" or "Ambilet Team").',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Host SMTP', 'en' => 'SMTP host'],
                            'description' => [
                                'ro' => 'Adresa serverului SMTP (ex: smtp.gmail.com, smtp.mailgun.org). Necesar doar pentru driver-ul SMTP.',
                                'en' => 'The SMTP server address (e.g. smtp.gmail.com, smtp.mailgun.org). Required only for the SMTP driver.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Port SMTP', 'en' => 'SMTP port'],
                            'description' => [
                                'ro' => 'Portul serverului SMTP. Valori comune: 587 (TLS), 465 (SSL), 25 (fara criptare). Recomandat: 587 cu TLS.',
                                'en' => 'The SMTP server port. Common values: 587 (TLS), 465 (SSL), 25 (no encryption). Recommended: 587 with TLS.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Utilizator SMTP', 'en' => 'SMTP username'],
                            'description' => [
                                'ro' => 'Numele de utilizator pentru autentificarea pe serverul SMTP. De obicei este adresa de email completa.',
                                'en' => 'The username for SMTP server authentication. Usually the full email address.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Parola SMTP', 'en' => 'SMTP password'],
                            'description' => [
                                'ro' => 'Parola sau app password-ul pentru autentificarea SMTP. Pentru Gmail, foloseste un "App Password" generat din setarile contului Google.',
                                'en' => 'The password or app password for SMTP authentication. For Gmail, use an "App Password" generated from Google account settings.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Criptare SMTP', 'en' => 'SMTP encryption'],
                            'description' => [
                                'ro' => 'Tipul de criptare: TLS (recomandat, port 587) sau SSL (port 465). Lasa gol doar daca serverul nu suporta criptare.',
                                'en' => 'Encryption type: TLS (recommended, port 587) or SSL (port 465). Leave empty only if the server does not support encryption.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                        [
                            'name' => ['ro' => 'Chei API', 'en' => 'API keys'],
                            'description' => [
                                'ro' => 'Cheile API necesare pentru driverele de tip Mailgun, Postmark sau Amazon SES. Se obtin din panoul de control al serviciului respectiv.',
                                'en' => 'The API keys required for Mailgun, Postmark or Amazon SES drivers. Obtained from the respective service control panel.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Setari mail', 'en' => 'Mail Settings'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Dupa configurare, trimite un email de test pentru a verifica ca setarile sunt corecte. Verifica si folderul de spam al destinatarului.',
                                'en' => 'After configuration, send a test email to verify that the settings are correct. Also check the recipient\'s spam folder.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru productie, se recomanda folosirea unui serviciu dedicat de email (Mailgun, Postmark, Amazon SES) in loc de SMTP direct, pentru o livrabilitate mai buna.',
                                'en' => 'For production, it is recommended to use a dedicated email service (Mailgun, Postmark, Amazon SES) instead of direct SMTP, for better deliverability.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 6: Domains
                [
                    'id' => 'domains',
                    'title' => ['ro' => 'Gestionarea domeniilor', 'en' => 'Managing domains'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Domenii] (sub grupul Setari).',
                                'en' => 'From the left sidebar menu, click on [Domenii] (under the Settings group).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina cu tabelul interactiv al domeniilor configurate pentru marketplace-ul tau.',
                                'en' => 'The page opens with the interactive table of domains configured for your marketplace.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a adauga un domeniu nou, apasa butonul [Adauga domeniu] si introdu numele complet al domeniului (ex: bilete.exemplu.ro).',
                                'en' => 'To add a new domain, click the [Adauga domeniu] button and enter the full domain name (e.g. tickets.example.com).',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume domeniu', 'en' => 'Domain name'],
                            'description' => [
                                'ro' => 'Adresa completa a domeniului (ex: bilete.exemplu.ro). Domeniul trebuie sa aiba DNS-ul configurat sa pointeze catre serverul marketplace-ului.',
                                'en' => 'The full domain address (e.g. tickets.example.com). The domain must have DNS configured to point to the marketplace server.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Status activ/suspendat', 'en' => 'Active/suspended status'],
                            'description' => [
                                'ro' => 'Comutator pentru activarea sau suspendarea unui domeniu. Un domeniu suspendat nu va mai fi accesibil de catre vizitatori.',
                                'en' => 'Toggle for activating or suspending a domain. A suspended domain will no longer be accessible to visitors.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Domeniu primar', 'en' => 'Primary domain'],
                            'description' => [
                                'ro' => 'Marcheaza un domeniu ca primar. Domeniul primar este cel principal folosit in linkuri, email-uri si pe bilete. Doar un singur domeniu poate fi primar.',
                                'en' => 'Marks a domain as primary. The primary domain is the main one used in links, emails and on tickets. Only one domain can be primary.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Inainte de a adauga un domeniu, asigura-te ca inregistrarea DNS (A record sau CNAME) a fost configurata corect la furnizorul de domenii.',
                                'en' => 'Before adding a domain, make sure the DNS record (A record or CNAME) has been correctly configured at the domain provider.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Schimbarea domeniului primar va afecta toate linkurile din email-urile trimise si de pe biletele generate ulterior. Biletele deja emise nu se modifica.',
                                'en' => 'Changing the primary domain will affect all links in emails sent and on tickets generated afterwards. Already issued tickets are not modified.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 7: Payment Config
                [
                    'id' => 'payment-config',
                    'title' => ['ro' => 'Configurarea metodelor de plata', 'en' => 'Payment methods setup'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Configurare plati] (sub grupul Setari).',
                                'en' => 'From the left sidebar menu, click on [Configurare plati] (under the Settings group).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina de configurare a metodelor de plata disponibile pe marketplace.',
                                'en' => 'The payment methods configuration page for the marketplace opens.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Configureaza fiecare metoda de plata dorita (card bancar, transfer bancar, etc.) cu credentialele si setarile specifice.',
                                'en' => 'Configure each desired payment method (bank card, bank transfer, etc.) with specific credentials and settings.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salveaza] pentru a activa configurarea. Metodele de plata configurate vor fi disponibile clientilor la checkout.',
                                'en' => 'Click [Salveaza] to activate the configuration. Configured payment methods will be available to customers at checkout.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Testeaza intotdeauna metodele de plata cu o tranzactie de test inainte de a le face publice. Majoritatea procesatorilor de plati ofera un mod sandbox/test.',
                                'en' => 'Always test payment methods with a test transaction before making them public. Most payment processors offer a sandbox/test mode.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Credentialele de plata (chei API, secrete) sunt stocate criptat. Nu le partaja prin email sau mesaje. Foloseste modul "live" doar cand esti pregatit sa accepti plati reale.',
                                'en' => 'Payment credentials (API keys, secrets) are stored encrypted. Do not share them via email or messages. Use "live" mode only when you are ready to accept real payments.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 8: Profile
                [
                    'id' => 'profile',
                    'title' => ['ro' => 'Profil personal si schimbarea parolei', 'en' => 'Personal profile and password change'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Profil] (sub grupul Setari), sau apasa pe numele tau din coltul din dreapta sus si selecteaza [Profil].',
                                'en' => 'From the left sidebar menu, click on [Profil] (under the Settings group), or click on your name in the top right corner and select [Profil].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina de profil cu informatiile tale personale.',
                                'en' => 'The profile page opens with your personal information.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Modifica campurile dorite si apasa [Salveaza] pentru a actualiza profilul.',
                                'en' => 'Modify the desired fields and click [Salveaza] to update the profile.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Informatii personale', 'en' => 'Personal information'],
                            'description' => [
                                'ro' => 'Numele, prenumele si adresa de email asociate contului tau de administrator. Emailul este folosit pentru autentificare si notificari.',
                                'en' => 'The first name, last name and email address associated with your admin account. The email is used for authentication and notifications.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Avatar',
                            'description' => [
                                'ro' => 'Imaginea de profil care apare langa numele tau in panoul de administrare. Recomandat: imagine patrata de minim 128x128 pixeli.',
                                'en' => 'The profile image that appears next to your name in the admin panel. Recommended: square image of at least 128x128 pixels.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Schimbare parola', 'en' => 'Password change'],
                            'description' => [
                                'ro' => 'Pentru a schimba parola, introdu parola curenta, apoi parola noua si confirma parola noua. Parola trebuie sa aiba minim 8 caractere.',
                                'en' => 'To change the password, enter the current password, then the new password and confirm the new password. The password must be at least 8 characters.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Dupa schimbarea parolei vei fi delogat automat si va trebui sa te autentifici din nou cu noua parola.',
                                'en' => 'After changing the password you will be automatically logged out and will need to log in again with the new password.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste o parola puternica: minim 8 caractere, cu litere mari, litere mici, cifre si caractere speciale.',
                                'en' => 'Use a strong password: at least 8 characters, with uppercase letters, lowercase letters, numbers and special characters.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 9: Team Members
                [
                    'id' => 'team-members',
                    'title' => ['ro' => 'Gestionarea echipei (utilizatori admin)', 'en' => 'Managing team (admin users)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Administratori] (sub grupul Setari).',
                                'en' => 'From the left sidebar menu, click on [Administratori] (under the Settings group).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide lista cu toti utilizatorii admin ai marketplace-ului tau.',
                                'en' => 'The list of all admin users of your marketplace opens.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a adauga un membru nou in echipa, apasa butonul [Creare administrator] din coltul din dreapta sus.',
                                'en' => 'To add a new team member, click the [Creare administrator] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza datele noului administrator: nume, email, parola si rolul/permisiunile dorite.',
                                'en' => 'Fill in the new admin details: name, email, password and desired role/permissions.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salveaza] pentru a crea contul. Noul administrator va primi un email de notificare si va putea accesa panoul de administrare.',
                                'en' => 'Click [Salveaza] to create the account. The new admin will receive a notification email and will be able to access the admin panel.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume complet', 'en' => 'Full name'],
                            'description' => [
                                'ro' => 'Numele si prenumele utilizatorului admin. Apare in panoul de administrare si in logurile de activitate.',
                                'en' => 'The admin user\'s first and last name. Appears in the admin panel and activity logs.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email a administratorului. Se foloseste pentru autentificare si primirea notificarilor. Trebuie sa fie unica in sistem.',
                                'en' => 'The admin\'s email address. Used for authentication and receiving notifications. Must be unique in the system.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Parola', 'en' => 'Password'],
                            'description' => [
                                'ro' => 'Parola de acces pentru noul administrator. Minim 8 caractere. La editare, lasa campul gol pentru a pastra parola existenta.',
                                'en' => 'The access password for the new admin. Minimum 8 characters. When editing, leave the field empty to keep the existing password.',
                            ],
                            'required' => true,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Fiecare actiune a unui administrator este inregistrata in logurile de activitate. Poti vedea cine a modificat ce si cand.',
                                'en' => 'Every action of an admin is recorded in the activity logs. You can see who changed what and when.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Nu partaja conturile de administrator intre mai multi utilizatori. Creeaza conturi separate pentru fiecare membru al echipei pentru securitate si trasabilitate.',
                                'en' => 'Do not share admin accounts between multiple users. Create separate accounts for each team member for security and traceability.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],
            ],
        ];
    }
}
