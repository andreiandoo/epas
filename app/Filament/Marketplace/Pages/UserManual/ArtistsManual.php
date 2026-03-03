<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ArtistsManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-artists';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Artisti', 'en' => 'Artists'],
            'description' => [
                'ro' => 'Ghid complet pentru crearea, editarea si gestionarea artistilor pe platforma, inclusiv parteneriate si genuri muzicale.',
                'en' => 'Complete guide for creating, editing and managing artists on the platform, including partnerships and music genres.',
            ],
            'icon' => 'heroicon-o-user-group',
            'sections' => [
                // Section 1: Create Artist
                [
                    'id' => 'create-artist',
                    'title' => ['ro' => 'Cum creezi un artist', 'en' => 'How to create an artist'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Artisti].',
                                'en' => 'From the left sidebar menu, click on [Artisti].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In pagina "Artisti" apasa butonul [Creare Artist] din coltul din dreapta sus.',
                                'en' => 'On the "Artisti" page, click the [Creare Artist] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de creare. Formularul are o coloana principala (3/4 din latime) cu toate campurile si o coloana laterala dreapta (1/4) cu status-uri si toggle-uri.',
                                'en' => 'The creation form opens. The form has a main column (3/4 width) with all fields and a right sidebar column (1/4) with statuses and toggles.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza campurile obligatorii (Nume artist si Slug) si apoi apasa butonul [Salveaza] din partea de jos a paginii.',
                                'en' => 'Fill in the required fields (Artist name and Slug) and then click the [Salveaza] button at the bottom of the page.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Inainte de a crea un artist nou, verifica sectiunea "Artisti existenti" care apare sub campul de nume. Daca artistul exista deja in sistem, il poti adauga direct ca partener fara a-l crea din nou.',
                                'en' => 'Before creating a new artist, check the "Existing artists" section that appears below the name field. If the artist already exists in the system, you can add them directly as a partner without creating them again.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume artist', 'en' => 'Artist name'],
                            'description' => [
                                'ro' => 'Numele artistului care va aparea pe site. Maxim 255 de caractere. Din acest nume se genereaza automat slug-ul (adresa URL).',
                                'en' => 'The artist name that will appear on the site. Maximum 255 characters. The slug (URL address) is auto-generated from this name.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Slug',
                            'description' => [
                                'ro' => 'Identificator URL unic, generat automat din numele artistului. Trebuie sa fie unic si poate contine doar litere, cifre, cratima si underscore (alpha_dash). Se poate edita manual daca doresti o adresa personalizata.',
                                'en' => 'Unique URL identifier, auto-generated from the artist name. Must be unique and can only contain letters, numbers, dashes and underscores (alpha_dash). Can be manually edited for a custom address.',
                            ],
                            'required' => true,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Slug-ul se genereaza automat doar la creare. Daca modifici numele dupa salvare, slug-ul NU se va actualiza automat.',
                                'en' => 'The slug is auto-generated only on creation. If you change the name after saving, the slug will NOT update automatically.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca in campul de cautare apare un avertisment cu artisti similari, verifica mai intai daca artistul exista deja. Crearea unui duplicat va genera confuzie in sistem.',
                                'en' => 'If a warning with similar artists appears in the search field, first verify if the artist already exists. Creating a duplicate will cause confusion in the system.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 2: Search Existing Artists
                [
                    'id' => 'search-existing',
                    'title' => ['ro' => 'Cautare artisti existenti si adaugare ca partener', 'en' => 'Search existing artists and add as partner'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Cand incepi sa scrii numele unui artist in formularul de creare, sistemul cauta automat in baza de date dupa artisti cu nume similar.',
                                'en' => 'When you start typing an artist name in the creation form, the system automatically searches the database for artists with similar names.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sub campul "Nume artist", apare o lista cu rezultatele cautarii. Fiecare artist este marcat cu unul din cele doua statusuri:',
                                'en' => 'Below the "Artist name" field, a list appears with search results. Each artist is marked with one of two statuses:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Verde cu bifa (Partener - Editeaza) - artistul este deja partenerul tau. Apasa pe link pentru a-l edita direct.',
                                'en' => 'Green with checkmark (Partner - Edit) - the artist is already your partner. Click the link to edit them directly.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Portocaliu cu + (Adauga partener) - artistul exista in sistem dar nu este partenerul tau. Apasa pe link pentru a naviga la pagina Artisti Parteneri unde il poti adauga.',
                                'en' => 'Orange with + (Add partner) - the artist exists in the system but is not your partner. Click the link to navigate to the Partner Artists page where you can add them.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca nu apare niciun rezultat, inseamna ca artistul nu exista in sistem si poti continua cu crearea unui artist nou.',
                                'en' => 'If no results appear, it means the artist does not exist in the system and you can proceed with creating a new artist.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Cautarea se activeaza automat dupa ce introduci minim 2 caractere in campul de nume. Rezultatele se actualizeaza in timp real pe masura ce scrii.',
                                'en' => 'The search activates automatically after you enter at least 2 characters in the name field. Results update in real-time as you type.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Aceasta sectiune de cautare apare doar in formularul de creare, nu si la editare.',
                                'en' => 'This search section only appears in the creation form, not when editing.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 3: Form Fields
                [
                    'id' => 'artist-fields',
                    'title' => ['ro' => 'Campurile formularului de artist', 'en' => 'Artist form fields'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Formularul de artist contine mai multe sectiuni. Sectiunile Contact, Manager, Agent Booking, Agentie de Booking, Social Media si Videoclipuri YouTube sunt pliate implicit si se pot deschide apasand pe ele.',
                                'en' => 'The artist form contains multiple sections. The Contact, Manager, Agent Booking, Booking Agency, Social Media and YouTube Videos sections are collapsed by default and can be opened by clicking on them.',
                            ],
                        ],
                    ],
                    'fields' => [
                        // Identity
                        [
                            'name' => ['ro' => 'Nume artist', 'en' => 'Artist name'],
                            'description' => [
                                'ro' => 'Sectiunea "Identitate Artist". Numele complet al artistului sau al trupei. Camp obligatoriu, maxim 255 caractere.',
                                'en' => '"Artist Identity" section. The full name of the artist or band. Required field, maximum 255 characters.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Slug',
                            'description' => [
                                'ro' => 'Sectiunea "Identitate Artist". Identificator unic pentru URL, generat automat din nume. Trebuie sa fie unic in tot sistemul.',
                                'en' => '"Artist Identity" section. Unique identifier for URL, auto-generated from name. Must be unique across the entire system.',
                            ],
                            'required' => true,
                        ],
                        // Media
                        [
                            'name' => ['ro' => 'Imagine principala', 'en' => 'Main image'],
                            'description' => [
                                'ro' => 'Sectiunea "Media". Imaginea de profil a artistului. Formate acceptate: JPEG, PNG, WebP, GIF. Maxim 10 MB.',
                                'en' => '"Media" section. The artist profile image. Accepted formats: JPEG, PNG, WebP, GIF. Maximum 10 MB.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Logo',
                            'description' => [
                                'ro' => 'Sectiunea "Media". Logo-ul oficial al artistului sau al trupei.',
                                'en' => '"Media" section. The official logo of the artist or band.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Portret', 'en' => 'Portrait'],
                            'description' => [
                                'ro' => 'Sectiunea "Media". Fotografia portret a artistului.',
                                'en' => '"Media" section. The artist portrait photograph.',
                            ],
                            'required' => false,
                        ],
                        // Biography
                        [
                            'name' => ['ro' => 'Biografie (RO)', 'en' => 'Biography (RO)'],
                            'description' => [
                                'ro' => 'Sectiunea "Biografie", tab-ul "Romana". Descrierea artistului in limba romana. Editor rich text cu formatare.',
                                'en' => '"Biography" section, "Romana" tab. The artist description in Romanian. Rich text editor with formatting.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Biografie (EN)', 'en' => 'Biography (EN)'],
                            'description' => [
                                'ro' => 'Sectiunea "Biografie", tab-ul "English". Descrierea artistului in limba engleza.',
                                'en' => '"Biography" section, "English" tab. The artist description in English.',
                            ],
                            'required' => false,
                        ],
                        // Location
                        [
                            'name' => ['ro' => 'Oras', 'en' => 'City'],
                            'description' => [
                                'ro' => 'Sectiunea "Locatie". Orasul de origine al artistului (ex: Bucuresti).',
                                'en' => '"Location" section. The artist\'s home city (e.g. Bucharest).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tara', 'en' => 'Country'],
                            'description' => [
                                'ro' => 'Sectiunea "Locatie". Tara de origine a artistului (ex: Romania).',
                                'en' => '"Location" section. The artist\'s home country (e.g. Romania).',
                            ],
                            'required' => false,
                        ],
                        // Categories
                        [
                            'name' => ['ro' => 'Tip artist', 'en' => 'Artist type'],
                            'description' => [
                                'ro' => 'Sectiunea "Categorii". Selecteaza tipul artistului (ex: Solist, Trupa, DJ, etc.). Poti selecta mai multe tipuri.',
                                'en' => '"Categories" section. Select the artist type (e.g. Solo, Band, DJ, etc.). You can select multiple types.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Genuri muzicale', 'en' => 'Music genres'],
                            'description' => [
                                'ro' => 'Sectiunea "Categorii". Selecteaza genurile muzicale ale artistului. Poti selecta mai multe genuri si poti crea genuri noi direct din acest camp.',
                                'en' => '"Categories" section. Select the artist\'s music genres. You can select multiple genres and create new ones directly from this field.',
                            ],
                            'required' => false,
                        ],
                        // Social Media
                        [
                            'name' => 'Social Media & Link-uri',
                            'description' => [
                                'ro' => 'Sectiune pliata. Contine campuri pentru: Website, Facebook, Instagram, TikTok, YouTube, Spotify, Spotify Artist ID si YouTube Channel ID.',
                                'en' => 'Collapsed section. Contains fields for: Website, Facebook, Instagram, TikTok, YouTube, Spotify, Spotify Artist ID and YouTube Channel ID.',
                            ],
                            'required' => false,
                        ],
                        // YouTube Videos
                        [
                            'name' => ['ro' => 'Videoclipuri YouTube', 'en' => 'YouTube Videos'],
                            'description' => [
                                'ro' => 'Sectiune pliata. Poti adauga pana la 5 link-uri de videoclipuri YouTube. Foloseste butonul [Adauga videoclip] pentru a adauga un link nou.',
                                'en' => 'Collapsed section. You can add up to 5 YouTube video links. Use the [Adauga videoclip] button to add a new link.',
                            ],
                            'required' => false,
                        ],
                        // Contact
                        [
                            'name' => 'Contact',
                            'description' => [
                                'ro' => 'Sectiune pliata. Contine campurile: Email si Telefon ale artistului.',
                                'en' => 'Collapsed section. Contains fields: Email and Phone of the artist.',
                            ],
                            'required' => false,
                        ],
                        // Manager
                        [
                            'name' => 'Manager',
                            'description' => [
                                'ro' => 'Sectiune pliata. Datele managerului artistului: Prenume, Nume, Email si Telefon.',
                                'en' => 'Collapsed section. The artist manager details: First name, Last name, Email and Phone.',
                            ],
                            'required' => false,
                        ],
                        // Agent Booking
                        [
                            'name' => 'Agent Booking',
                            'description' => [
                                'ro' => 'Sectiune pliata. Datele agentului de booking: Prenume, Nume, Email si Telefon.',
                                'en' => 'Collapsed section. The booking agent details: First name, Last name, Email and Phone.',
                            ],
                            'required' => false,
                        ],
                        // Booking Agency
                        [
                            'name' => ['ro' => 'Agentie de Booking', 'en' => 'Booking Agency'],
                            'description' => [
                                'ro' => 'Sectiune pliata. Datele agentiei de booking: Nume agentie, Email, Telefon si Website.',
                                'en' => 'Collapsed section. The booking agency details: Agency name, Email, Phone and Website.',
                            ],
                            'required' => false,
                        ],
                        // Internal Notes
                        [
                            'name' => ['ro' => 'Note interne', 'en' => 'Internal notes'],
                            'description' => [
                                'ro' => 'Sectiune pliata. Note interne despre artist care nu sunt vizibile public. Utile pentru informatii despre contracte, parteneriate, etc.',
                                'en' => 'Collapsed section. Internal notes about the artist that are not publicly visible. Useful for information about contracts, partnerships, etc.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'In coloana laterala dreapta (vizibila doar la editare) gasesti: previzualizarea artistului, toggle-urile de status (Activ/Promovat), statisticile social media si numarul de evenimente.',
                                'en' => 'In the right sidebar column (visible only when editing) you will find: artist preview, status toggles (Active/Featured), social media statistics and events count.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Genurile muzicale pot fi create direct din campul de selectie. Apasa pe optiunea de creare si completeaza numele in romana si/sau engleza.',
                                'en' => 'Music genres can be created directly from the select field. Click the create option and fill in the name in Romanian and/or English.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 4: Partner Artists
                [
                    'id' => 'partner-artists',
                    'title' => ['ro' => 'Gestionarea artistilor parteneri', 'en' => 'Managing partner artists'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral, sub [Artisti], apasa pe [Artisti Parteneri]. Aceasta este o pagina separata dedicata gestionarii parteneriatelor cu artisti.',
                                'en' => 'From the left sidebar, under [Artisti], click on [Artisti Parteneri]. This is a separate page dedicated to managing artist partnerships.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In aceasta pagina vezi un tabel cu toti artistii din sistem. Coloana "Status" arata daca un artist este partenerul tau (bifa verde) sau nu (X gri).',
                                'en' => 'On this page you see a table with all artists in the system. The "Status" column shows if an artist is your partner (green checkmark) or not (gray X).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a adauga un artist ca partener, apasa butonul [Adauga partener] din dreptul artistului dorit. Poti adauga optional note despre parteneriat.',
                                'en' => 'To add an artist as a partner, click the [Adauga partener] button next to the desired artist. You can optionally add notes about the partnership.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a elimina un artist din parteneri, apasa butonul [Elimina] (rosu) si confirma actiunea.',
                                'en' => 'To remove an artist from partners, click the [Elimina] button (red) and confirm the action.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a edita notele unui parteneriat existent, apasa butonul [Note] din dreptul artistului partener.',
                                'en' => 'To edit the notes of an existing partnership, click the [Note] button next to the partner artist.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti adauga mai multi artisti ca parteneri simultan selectand checkbox-urile si folosind actiunea in masa [Adauga ca parteneri].',
                                'en' => 'You can add multiple artists as partners simultaneously by selecting the checkboxes and using the bulk action [Adauga ca parteneri].',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Filtru Status', 'en' => 'Status filter'],
                            'description' => [
                                'ro' => 'Filtreaza tabelul dupa: Toti, Parteneri (doar artistii asociati) sau Disponibili (artisti care nu sunt inca parteneri).',
                                'en' => 'Filter the table by: All, Partners (only associated artists) or Available (artists that are not yet partners).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Oras', 'en' => 'City filter'],
                            'description' => [
                                'ro' => 'Filtreaza artistii dupa orasul de origine.',
                                'en' => 'Filter artists by their home city.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Cautare', 'en' => 'Search'],
                            'description' => [
                                'ro' => 'Cauta dupa numele artistului sau oras. Cautarea normalizeaza diacriticele automat.',
                                'en' => 'Search by artist name or city. The search normalizes diacritics automatically.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Doar artistii adaugati ca parteneri vor aparea in lista principala [Artisti] si pot fi asociati cu evenimentele tale.',
                                'en' => 'Only artists added as partners will appear in the main [Artisti] list and can be associated with your events.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca artistul pe care il cauti nu exista deloc in sistem, poti crea unul nou folosind butonul [Adauga artist nou] din coltul din dreapta sus al paginii Artisti Parteneri.',
                                'en' => 'If the artist you are looking for does not exist in the system at all, you can create a new one using the [Adauga artist nou] button in the top right corner of the Partner Artists page.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Edit Artist
                [
                    'id' => 'edit-artist',
                    'title' => ['ro' => 'Cum editezi un artist existent', 'en' => 'How to edit an existing artist'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral, apasa pe [Artisti] pentru a vedea lista artistilor tai parteneri.',
                                'en' => 'From the left sidebar, click on [Artisti] to see the list of your partner artists.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Gaseste artistul dorit folosind campul de cautare din partea de sus sau filtrele disponibile.',
                                'en' => 'Find the desired artist using the search field at the top or the available filters.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe iconita de editare (creion) din coloana de actiuni.',
                                'en' => 'Click on the edit icon (pencil) in the actions column.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de editare, identic cu cel de creare dar cu informatiile deja completate. In plus, in coloana laterala dreapta apar: previzualizarea artistului, statistici social media si numarul de evenimente.',
                                'en' => 'The edit form opens, identical to the creation form but with information already filled in. Additionally, in the right sidebar column you will see: artist preview, social media statistics and events count.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Modifica campurile dorite si apasa [Salveaza] din partea de jos a paginii.',
                                'en' => 'Modify the desired fields and click [Salveaza] at the bottom of the page.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'La editare, in bara laterala poti vedea statisticile social media (Spotify, YouTube, Instagram, Facebook, TikTok) si numarul total de evenimente (viitoare si incheiate).',
                                'en' => 'When editing, in the sidebar you can see social media statistics (Spotify, YouTube, Instagram, Facebook, TikTok) and total events count (upcoming and past).',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca modifici slug-ul artistului, adresa URL a paginii publice a artistului se va schimba.',
                                'en' => 'If you change the artist slug, the URL of the artist\'s public page will change.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 6: Artist List
                [
                    'id' => 'artist-list',
                    'title' => ['ro' => 'Lista de artisti - cum o folosesti', 'en' => 'Artist list - how to use it'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina [Artisti] afiseaza toti artistii tai parteneri intr-un tabel cu urmatoarele coloane:',
                                'en' => 'The [Artisti] page displays all your partner artists in a table with the following columns:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Imagine', 'en' => 'Image'],
                            'description' => [
                                'ro' => 'Miniatura rotunda a imaginii principale a artistului. Daca nu are imagine, se afiseaza un avatar generat automat.',
                                'en' => 'Circular thumbnail of the artist\'s main image. If no image is set, an auto-generated avatar is displayed.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Nume', 'en' => 'Name'],
                            'description' => [
                                'ro' => 'Numele artistului. Coloana este sortabila si cautabila.',
                                'en' => 'The artist name. The column is sortable and searchable.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Oras', 'en' => 'City'],
                            'description' => [
                                'ro' => 'Orasul de origine al artistului. Coloana este sortabila.',
                                'en' => 'The artist\'s home city. The column is sortable.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tip', 'en' => 'Type'],
                            'description' => [
                                'ro' => 'Tipul artistului afisat ca etichete colorate (badges). Aceasta coloana poate fi ascunsa/afisata.',
                                'en' => 'The artist type displayed as colored badges. This column can be hidden/shown.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Genuri', 'en' => 'Genres'],
                            'description' => [
                                'ro' => 'Genurile muzicale afisate ca etichete albastre (badges). Aceasta coloana poate fi ascunsa/afisata.',
                                'en' => 'Music genres displayed as blue badges. This column can be hidden/shown.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Promovat', 'en' => 'Featured'],
                            'description' => [
                                'ro' => 'Iconita care indica daca artistul este marcat ca promovat. Aceasta coloana poate fi ascunsa/afisata.',
                                'en' => 'Icon indicating if the artist is marked as featured. This column can be hidden/shown.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Activ', 'en' => 'Active'],
                            'description' => [
                                'ro' => 'Iconita care indica daca artistul este activ pe site. Aceasta coloana poate fi ascunsa/afisata.',
                                'en' => 'Icon indicating if the artist is active on the site. This column can be hidden/shown.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Foloseste filtrele din partea de sus a tabelului pentru a gasi rapid artistii doriti. Poti filtra dupa: status promovat (Doar promovati), status activ (Doar activi) si tip artist.',
                                'en' => 'Use the filters at the top of the table to quickly find desired artists. You can filter by: featured status (Featured only), active status (Active only) and artist type.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Badge-ul din meniul lateral de langa [Artisti] arata numarul total de artisti parteneri pe care ii ai.',
                                'en' => 'The badge in the sidebar menu next to [Artisti] shows the total number of partner artists you have.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
