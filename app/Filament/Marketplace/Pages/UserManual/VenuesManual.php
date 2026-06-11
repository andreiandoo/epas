<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class VenuesManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-venues';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Locatii', 'en' => 'Venues'],
            'description' => [
                'ro' => 'Ghid complet pentru crearea, editarea si gestionarea locatiilor pe platforma.',
                'en' => 'Complete guide for creating, editing and managing venues on the platform.',
            ],
            'icon' => 'heroicon-o-building-office',
            'sections' => [
                // Section 1: Create Venue
                [
                    'id' => 'create-venue',
                    'title' => ['ro' => 'Cum creezi o locatie', 'en' => 'How to create a venue'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Locatii].',
                                'en' => 'From the left sidebar menu, click on [Locatii].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In pagina "Locatii" apasa butonul [Creare locatie] din coltul din dreapta sus.',
                                'en' => 'On the "Locatii" page, click the [Creare locatie] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de creare. Formularul are doua coloane: coloana principala (stanga, 3/4 din latime) si bara laterala (dreapta, 1/4 din latime).',
                                'en' => 'The creation form opens. The form has two columns: main column (left, 3/4 width) and sidebar (right, 1/4 width).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza campurile obligatorii (marcate mai jos) si apoi apasa butonul [Salveaza] din partea de jos a paginii.',
                                'en' => 'Fill in the required fields (marked below) and then click the [Salveaza] button at the bottom of the page.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume locatie', 'en' => 'Venue name'],
                            'description' => [
                                'ro' => 'Numele locatiei care va aparea pe site. Camp traductibil — poti completa versiunea in limba engleza si in limba romana folosind tab-urile EN/RO. Maxim 255 de caractere.',
                                'en' => 'The venue name that will appear on the site. Translatable field — you can fill in the English and Romanian versions using the EN/RO tabs. Maximum 255 characters.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Slug',
                            'description' => [
                                'ro' => 'Identificator URL unic, generat automat din numele locatiei. Se poate edita manual daca doresti o adresa personalizata. Trebuie sa fie unic in sistem.',
                                'en' => 'Unique URL identifier, auto-generated from the venue name. Can be manually edited for a custom address. Must be unique in the system.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Imagine principala', 'en' => 'Main image'],
                            'description' => [
                                'ro' => 'Imaginea de coperta a locatiei care va aparea in listari si pe pagina locatiei. Formate acceptate: JPEG, PNG, WebP, GIF.',
                                'en' => 'The venue cover image that will appear in listings and on the venue page. Accepted formats: JPEG, PNG, WebP, GIF.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Galerie foto', 'en' => 'Photo gallery'],
                            'description' => [
                                'ro' => 'Poti incarca pana la 3 imagini suplimentare pentru galeria locatiei. Imaginile pot fi reordonate prin drag and drop.',
                                'en' => 'You can upload up to 3 additional images for the venue gallery. Images can be reordered via drag and drop.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Video',
                            'description' => [
                                'ro' => 'Poti adauga un video despre locatie — fie un link YouTube, fie un fisier video incarcat direct.',
                                'en' => 'You can add a video about the venue — either a YouTube link or a directly uploaded video file.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Campurile "Nume" si "Descriere" sunt traductibile. Foloseste tab-urile EN/RO din formular pentru a completa ambele versiuni lingvistice.',
                                'en' => 'The "Name" and "Description" fields are translatable. Use the EN/RO tabs in the form to fill in both language versions.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Slug-ul trebuie sa fie unic. Daca primesti o eroare de validare la salvare, verifica sa nu existe deja o alta locatie cu acelasi slug.',
                                'en' => 'The slug must be unique. If you get a validation error on save, check that another venue with the same slug does not already exist.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 2: Search Existing Venues
                [
                    'id' => 'search-existing',
                    'title' => ['ro' => 'Cauta si adauga o locatie existenta ca partener', 'en' => 'Search and add an existing venue as partner'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In formularul de creare a unei locatii noi, in partea de sus gasesti sectiunea "Cauta locatii existente".',
                                'en' => 'In the new venue creation form, at the top you will find the "Search existing venues" section.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Introdu numele sau orasul locatiei in campul de cautare. Sistemul va cauta in baza de date a tuturor locatiilor din platforma.',
                                'en' => 'Enter the venue name or city in the search field. The system will search the database of all venues on the platform.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca locatia exista deja, o poti adauga ca partener fara a o crea din nou. Apasa pe locatia gasita pentru a o selecta.',
                                'en' => 'If the venue already exists, you can add it as a partner without creating it again. Click on the found venue to select it.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca locatia nu exista in baza de date, continua cu completarea formularului pentru a crea o locatie noua.',
                                'en' => 'If the venue does not exist in the database, continue filling in the form to create a new venue.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea de cautare apare doar la crearea unei locatii noi, nu si la editare. Aceasta functie previne crearea de locatii duplicate in sistem.',
                                'en' => 'The search section only appears when creating a new venue, not when editing. This feature prevents creating duplicate venues in the system.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'O locatie adaugata ca partener inseamna ca aceasta locatie va fi disponibila in lista ta de locatii pentru a o asocia la evenimentele tale.',
                                'en' => 'A venue added as partner means this venue will be available in your venues list to associate with your events.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 3: All Form Fields Explained
                [
                    'id' => 'venue-fields',
                    'title' => ['ro' => 'Campurile formularului de locatie', 'en' => 'Venue form fields'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Formularul de locatie este impartit in mai multe sectiuni. Mai jos gasesti explicatia fiecarui camp grupat pe sectiune.',
                                'en' => 'The venue form is divided into multiple sections. Below you will find the explanation of each field grouped by section.',
                            ],
                        ],
                    ],
                    'fields' => [
                        // Identity
                        [
                            'name' => ['ro' => 'Nume (EN / RO)', 'en' => 'Name (EN / RO)'],
                            'description' => [
                                'ro' => 'Sectiunea Identitate. Numele locatiei in limba engleza si romana. Foloseste tab-urile EN/RO pentru a completa ambele versiuni.',
                                'en' => 'Identity section. The venue name in English and Romanian. Use the EN/RO tabs to fill in both versions.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Slug',
                            'description' => [
                                'ro' => 'Sectiunea Identitate. Adresa URL unica a locatiei, generata automat din nume. Poate fi editata manual. Trebuie sa fie unica.',
                                'en' => 'Identity section. The unique URL address of the venue, auto-generated from the name. Can be manually edited. Must be unique.',
                            ],
                            'required' => true,
                        ],
                        // Location
                        [
                            'name' => ['ro' => 'Adresa', 'en' => 'Address'],
                            'description' => [
                                'ro' => 'Sectiunea Localizare. Adresa stradala completa a locatiei (strada, numar, etc.).',
                                'en' => 'Location section. Full street address of the venue (street, number, etc.).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Oras', 'en' => 'City'],
                            'description' => [
                                'ro' => 'Sectiunea Localizare. Orasul in care se afla locatia. Apare in listari si in filtre.',
                                'en' => 'Location section. The city where the venue is located. Appears in listings and filters.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Judet / Stat', 'en' => 'State / Region'],
                            'description' => [
                                'ro' => 'Sectiunea Localizare. Judetul sau statul in care se afla locatia.',
                                'en' => 'Location section. The state or region where the venue is located.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tara', 'en' => 'Country'],
                            'description' => [
                                'ro' => 'Sectiunea Localizare. Tara in care se afla locatia.',
                                'en' => 'Location section. The country where the venue is located.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Latitudine / Longitudine', 'en' => 'Latitude / Longitude'],
                            'description' => [
                                'ro' => 'Sectiunea Localizare. Coordonatele GPS ale locatiei. Folosite pentru afisarea pe harta.',
                                'en' => 'Location section. GPS coordinates of the venue. Used for map display.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Google Maps URL',
                            'description' => [
                                'ro' => 'Sectiunea Localizare. Link direct catre locatia pe Google Maps.',
                                'en' => 'Location section. Direct link to the venue on Google Maps.',
                            ],
                            'required' => false,
                        ],
                        // Capacity
                        [
                            'name' => ['ro' => 'Capacitate totala', 'en' => 'Total capacity'],
                            'description' => [
                                'ro' => 'Sectiunea Capacitate. Numarul total de persoane pe care le poate gazdui locatia.',
                                'en' => 'Capacity section. The total number of people the venue can host.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Capacitate in picioare', 'en' => 'Standing capacity'],
                            'description' => [
                                'ro' => 'Sectiunea Capacitate. Numarul de persoane in format standing (fara scaune).',
                                'en' => 'Capacity section. The number of people in standing format (no seats).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Capacitate pe scaune', 'en' => 'Seated capacity'],
                            'description' => [
                                'ro' => 'Sectiunea Capacitate. Numarul de locuri pe scaune disponibile.',
                                'en' => 'Capacity section. The number of seated places available.',
                            ],
                            'required' => false,
                        ],
                        // Contact
                        [
                            'name' => ['ro' => 'Telefon 1 / Telefon 2', 'en' => 'Phone 1 / Phone 2'],
                            'description' => [
                                'ro' => 'Sectiunea Contact. Doua campuri pentru numere de telefon ale locatiei.',
                                'en' => 'Contact section. Two fields for venue phone numbers.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Email 1 / Email 2',
                            'description' => [
                                'ro' => 'Sectiunea Contact. Doua campuri pentru adresele de email ale locatiei.',
                                'en' => 'Contact section. Two fields for venue email addresses.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Website',
                            'description' => [
                                'ro' => 'Sectiunea Contact. Adresa website-ului oficial al locatiei.',
                                'en' => 'Contact section. The official website address of the venue.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Facebook / Instagram / TikTok',
                            'description' => [
                                'ro' => 'Sectiunea Contact. Link-urile catre profilurile de social media ale locatiei.',
                                'en' => 'Contact section. Links to the venue\'s social media profiles.',
                            ],
                            'required' => false,
                        ],
                        // Facilities
                        [
                            'name' => ['ro' => 'Facilitati', 'en' => 'Facilities'],
                            'description' => [
                                'ro' => 'Sectiune pliabila cu peste 20 de optiuni (checkboxuri) pentru facilitatile locatiei: parcare, Wi-Fi, aer conditionat, scena, vestiare, acces persoane cu dizabilitati, etc. Deschide sectiunea apasand pe ea.',
                                'en' => 'Collapsible section with 20+ checkbox options for venue facilities: parking, Wi-Fi, air conditioning, stage, dressing rooms, disability access, etc. Open the section by clicking on it.',
                            ],
                            'required' => false,
                        ],
                        // Description
                        [
                            'name' => ['ro' => 'Descriere (EN / RO)', 'en' => 'Description (EN / RO)'],
                            'description' => [
                                'ro' => 'Descrierea detaliata a locatiei in editor vizual (RichEditor). Camp traductibil — foloseste tab-urile EN/RO. Poti formata textul, adauga liste, link-uri etc.',
                                'en' => 'Detailed venue description in visual editor (RichEditor). Translatable field — use the EN/RO tabs. You can format text, add lists, links, etc.',
                            ],
                            'required' => false,
                        ],
                        // Partner Notes
                        [
                            'name' => ['ro' => 'Note partener', 'en' => 'Partner notes'],
                            'description' => [
                                'ro' => 'Sectiune pliabila cu un camp textarea pentru note interne despre locatie. Aceste note nu sunt vizibile public si sunt doar pentru uz intern.',
                                'en' => 'Collapsible section with a textarea field for internal notes about the venue. These notes are not publicly visible and are for internal use only.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Facilitati" si "Note partener" sunt pliabile (collapsible). Apasa pe titlul sectiunii pentru a o deschide sau inchide.',
                                'en' => 'The "Facilities" and "Partner notes" sections are collapsible. Click on the section title to open or close it.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Campurile traductibile (Nume si Descriere) au tab-uri EN/RO. Este recomandat sa completezi cel putin versiunea in limba principala a site-ului tau.',
                                'en' => 'Translatable fields (Name and Description) have EN/RO tabs. It is recommended to fill in at least the version in your site\'s primary language.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 4: Categories
                [
                    'id' => 'categories',
                    'title' => ['ro' => 'Gestionarea categoriilor de locatii', 'en' => 'Managing venue categories'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In bara laterala dreapta a formularului, gasesti campul "Categorii".',
                                'en' => 'In the right sidebar of the form, you will find the "Categories" field.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti selecta una sau mai multe categorii din lista existenta (multi-select). Categoriile ajuta la organizarea si filtrarea locatiilor.',
                                'en' => 'You can select one or more categories from the existing list (multi-select). Categories help organize and filter venues.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca categoria dorita nu exista, o poti crea direct din acest camp — scrie numele noii categorii si selecteaza optiunea de creare.',
                                'en' => 'If the desired category does not exist, you can create it directly from this field — type the new category name and select the create option.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Categorii', 'en' => 'Categories'],
                            'description' => [
                                'ro' => 'Camp multi-select in bara laterala dreapta. Permite selectarea mai multor categorii si crearea de categorii noi direct din formular.',
                                'en' => 'Multi-select field in the right sidebar. Allows selecting multiple categories and creating new categories directly from the form.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Featured (promovat)', 'en' => 'Featured (promoted)'],
                            'description' => [
                                'ro' => 'Comutator (toggle) in bara laterala dreapta. Marcheaza locatia ca featured pentru a o evidentia in listari.',
                                'en' => 'Toggle in the right sidebar. Marks the venue as featured to highlight it in listings.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Data infiintarii', 'en' => 'Established date'],
                            'description' => [
                                'ro' => 'Camp de data in bara laterala dreapta. Data la care a fost infiintata sau deschisa locatia.',
                                'en' => 'Date field in the right sidebar. The date when the venue was established or opened.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Program / Ore de functionare', 'en' => 'Schedule / Operating hours'],
                            'description' => [
                                'ro' => 'Camp text in bara laterala dreapta. Programul de functionare al locatiei (ex: "Luni-Vineri: 10:00-22:00").',
                                'en' => 'Text field in the right sidebar. The operating hours of the venue (e.g. "Monday-Friday: 10:00-22:00").',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Categoriile sunt afisate ca badge-uri colorate in tabelul de locatii. Selecteaza categorii relevante pentru a ajuta utilizatorii sa gaseasca mai usor locatia.',
                                'en' => 'Categories are displayed as colored badges in the venues table. Select relevant categories to help users find the venue more easily.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Edit Venue
                [
                    'id' => 'edit-venue',
                    'title' => ['ro' => 'Cum editezi o locatie existenta', 'en' => 'How to edit an existing venue'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral, apasa pe [Locatii] pentru a vedea lista tuturor locatiilor.',
                                'en' => 'From the left sidebar, click on [Locatii] to see the list of all venues.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Gaseste locatia dorita folosind campul de cautare din partea de sus sau filtrele disponibile.',
                                'en' => 'Find the desired venue using the search field at the top or the available filters.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe numele locatiei sau pe iconita de editare (creion) din coloana de actiuni.',
                                'en' => 'Click on the venue name or on the edit icon (pencil) in the actions column.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Modifica campurile dorite si apasa [Salveaza] din partea de jos.',
                                'en' => 'Modify the desired fields and click [Salveaza] at the bottom.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Statistici', 'en' => 'Statistics'],
                            'description' => [
                                'ro' => 'Sectiune vizibila doar la editare. Afiseaza statistici despre locatie (numar de evenimente gazduite, etc.).',
                                'en' => 'Section visible only when editing. Displays statistics about the venue (number of hosted events, etc.).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Actiuni rapide', 'en' => 'Quick actions'],
                            'description' => [
                                'ro' => 'Sectiune vizibila doar la editare. Contine butoane rapide: [Vezi evenimente] pentru a vedea evenimentele asociate locatiei si [Creare eveniment] pentru a crea un eveniment nou direct legat de aceasta locatie.',
                                'en' => 'Section visible only when editing. Contains quick buttons: [Vezi evenimente] to see events associated with the venue and [Creare eveniment] to create a new event directly linked to this venue.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Meta informatii', 'en' => 'Meta information'],
                            'description' => [
                                'ro' => 'Sectiune vizibila doar la editare. Afiseaza informatii despre cine a creat si modificat ultima data locatia, precum si datele de creare/modificare.',
                                'en' => 'Section visible only when editing. Displays information about who created and last modified the venue, along with creation/modification dates.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunile "Statistici", "Actiuni rapide" si "Meta informatii" apar doar la editarea unei locatii existente, nu si la creare.',
                                'en' => 'The "Statistics", "Quick actions" and "Meta information" sections only appear when editing an existing venue, not when creating.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Modificarea slug-ului va schimba adresa URL a locatiei. Verifica sa nu existe link-uri externe care depind de adresa veche.',
                                'en' => 'Changing the slug will modify the venue URL. Check that there are no external links that depend on the old address.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 6: Venue List
                [
                    'id' => 'venue-list',
                    'title' => ['ro' => 'Lista de locatii — cum o folosesti', 'en' => 'Venue list — how to use it'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina [Locatii] afiseaza toate locatiile intr-un tabel cu urmatoarele coloane:',
                                'en' => 'The [Locatii] page displays all venues in a table with the following columns:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Imagine', 'en' => 'Image'],
                            'description' => [
                                'ro' => 'Miniatura imaginii principale a locatiei.',
                                'en' => 'Thumbnail of the venue\'s main image.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Nume locatie', 'en' => 'Venue name'],
                            'description' => [
                                'ro' => 'Numele locatiei. Poti cauta dupa nume folosind campul de cautare din partea de sus a tabelului.',
                                'en' => 'The venue name. You can search by name using the search field at the top of the table.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Oras', 'en' => 'City'],
                            'description' => [
                                'ro' => 'Orasul in care se afla locatia.',
                                'en' => 'The city where the venue is located.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Capacitate', 'en' => 'Capacity'],
                            'description' => [
                                'ro' => 'Capacitatea totala a locatiei.',
                                'en' => 'The total capacity of the venue.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Categorii', 'en' => 'Categories'],
                            'description' => [
                                'ro' => 'Categoriile asociate locatiei, afisate ca badge-uri colorate.',
                                'en' => 'Categories associated with the venue, displayed as colored badges.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Status partener', 'en' => 'Partner status'],
                            'description' => [
                                'ro' => 'Indica daca locatia este partener sau nu. Partenerii sunt locatii asociate contului tau.',
                                'en' => 'Indicates whether the venue is a partner or not. Partners are venues associated with your account.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Foloseste filtrele din partea de sus a tabelului pentru a gasi rapid locatiile dorite. Poti filtra dupa: status partener si categorii.',
                                'en' => 'Use the filters at the top of the table to quickly find desired venues. You can filter by: partner status and categories.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Locatiile cu status "Partener" sunt cele pe care le-ai creat sau adaugat ca partener. Locatiile fara acest status sunt create de alti utilizatori si pot fi cautate la crearea unui eveniment.',
                                'en' => 'Venues with "Partner" status are those you created or added as a partner. Venues without this status are created by other users and can be searched when creating an event.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
