<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class EventsManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-events';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Evenimente', 'en' => 'Events'],
            'description' => [
                'ro' => 'Ghid complet pentru crearea, editarea si gestionarea evenimentelor pe platforma.',
                'en' => 'Complete guide for creating, editing and managing events on the platform.',
            ],
            'icon' => 'heroicon-o-calendar',
            'sections' => [
                // Section 1: Create Event
                [
                    'id' => 'create-event',
                    'title' => ['ro' => 'Cum creezi un eveniment', 'en' => 'How to create an event'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Evenimente].',
                                'en' => 'From the left sidebar menu, click on [Evenimente].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In pagina "Evenimente" apasa butonul [Creare eveniment] din coltul din dreapta sus.',
                                'en' => 'On the "Evenimente" page, click the [Creare eveniment] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de creare. Formularul are mai multe tab-uri: Detalii, Program, Continut, si altele.',
                                'en' => 'The creation form opens. The form has multiple tabs: Details, Schedule, Content, and others.',
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
                            'name' => ['ro' => 'Titlu eveniment', 'en' => 'Event title'],
                            'description' => [
                                'ro' => 'Numele evenimentului care va aparea pe site. Maxim 255 de caractere. Din acest titlu se genereaza automat slug-ul (adresa URL).',
                                'en' => 'The event name that will appear on the site. Maximum 255 characters. The slug (URL address) is auto-generated from this title.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Detalii', 'en' => 'Details'],
                        ],
                        [
                            'name' => 'Slug',
                            'description' => [
                                'ro' => 'Identificator URL unic, generat automat din titlu in formatul: titlu-eveniment-ID. Se poate edita manual daca doresti o adresa personalizata.',
                                'en' => 'Unique URL identifier, auto-generated from title in format: event-title-ID. Can be manually edited for a custom address.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii', 'en' => 'Details'],
                        ],
                        [
                            'name' => ['ro' => 'Serie eveniment', 'en' => 'Event series'],
                            'description' => [
                                'ro' => 'Codul unic al seriei de bilete (ex: AMB-123). Se genereaza automat la salvare si NU se poate modifica ulterior.',
                                'en' => 'Unique ticket series code (e.g. AMB-123). Auto-generated on save and CANNOT be changed later.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii', 'en' => 'Details'],
                        ],
                        [
                            'name' => ['ro' => 'Categorie', 'en' => 'Category'],
                            'description' => [
                                'ro' => 'Selecteaza categoria evenimentului (Concert, Festival, Teatru, etc.). Poti cauta in lista sau crea o categorie noua direct de aici.',
                                'en' => 'Select the event category (Concert, Festival, Theater, etc.). You can search the list or create a new category directly from here.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii', 'en' => 'Details'],
                        ],
                        [
                            'name' => ['ro' => 'Organizator', 'en' => 'Organizer'],
                            'description' => [
                                'ro' => 'Selecteaza organizatorul evenimentului din lista. Organizatorul trebuie sa fie deja inregistrat in sistem. Acest camp determina cine primeste veniturile din bilete.',
                                'en' => 'Select the event organizer from the list. The organizer must already be registered in the system. This field determines who receives ticket revenue.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Detalii', 'en' => 'Details'],
                        ],
                        [
                            'name' => ['ro' => 'Imagine principala', 'en' => 'Main image'],
                            'description' => [
                                'ro' => 'Imaginea de coperta a evenimentului care va aparea in listari si pe pagina evenimentului. Formate acceptate: JPEG, PNG, WebP, GIF.',
                                'en' => 'The event cover image that will appear in listings and on the event page. Accepted formats: JPEG, PNG, WebP, GIF.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Detalii', 'en' => 'Details'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Titlul trebuie sa fie scurt si descriptiv. Evita sa pui data sau locatia in titlu — acestea se completeaza separat in tab-ul Program.',
                                'en' => 'The title should be short and descriptive. Avoid putting the date or location in the title — these are filled in separately in the Schedule tab.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 2: Event Status Flags
                [
                    'id' => 'event-flags',
                    'title' => ['ro' => 'Status-urile evenimentului', 'en' => 'Event status flags'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In tab-ul [Detalii], sub campurile principale, gasesti o sectiune cu 5 comutatoare (toggle-uri) care controleaza starea evenimentului:',
                                'en' => 'In the [Detalii] tab, below the main fields, you will find a section with 5 toggles that control the event status:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Sold out',
                            'description' => [
                                'ro' => 'Marcheaza evenimentul ca vandut complet. Pe site va aparea eticheta "Sold Out". Se dezactiveaza automat daca activezi "Anulat".',
                                'en' => 'Marks the event as completely sold out. The "Sold Out" label will appear on the site. Automatically disabled if you enable "Cancelled".',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Doar la intrare', 'en' => 'Door sales only'],
                            'description' => [
                                'ro' => 'Indica faptul ca biletele se vand doar la intrare, nu online. Butonul de cumparare de pe site va fi inlocuit cu un mesaj informativ.',
                                'en' => 'Indicates that tickets are sold only at the door, not online. The buy button on the site will be replaced with an informational message.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Anulat', 'en' => 'Cancelled'],
                            'description' => [
                                'ro' => 'Marcheaza evenimentul ca anulat. Dezactiveaza automat: Sold out, Amanat si Promovat. Apare un camp suplimentar pentru motivul anularii.',
                                'en' => 'Marks the event as cancelled. Automatically disables: Sold out, Postponed and Promoted. An additional field appears for the cancellation reason.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Amanat', 'en' => 'Postponed'],
                            'description' => [
                                'ro' => 'Marcheaza evenimentul ca amanat. Apar campuri suplimentare: noua data, ora de inceput, ora de deschidere, ora de sfarsit si motivul amanarii.',
                                'en' => 'Marks the event as postponed. Additional fields appear: new date, start time, door time, end time and postponement reason.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Promovat', 'en' => 'Promoted'],
                            'description' => [
                                'ro' => 'Promoveaza evenimentul pe site (pozitie vizibila). Apare un camp "Promovat pana la" pentru a seta data de expirare a promovarii.',
                                'en' => 'Promotes the event on the site (visible position). A "Promoted until" field appears to set the promotion expiry date.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Nu poti activa simultan "Anulat" si "Amanat" sau "Anulat" si "Sold out". Sistemul dezactiveaza automat combinatiile invalide.',
                                'en' => 'You cannot simultaneously enable "Cancelled" and "Postponed" or "Cancelled" and "Sold out". The system automatically disables invalid combinations.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 3: Schedule Tab
                [
                    'id' => 'schedule',
                    'title' => ['ro' => 'Programul evenimentului (Tab Program)', 'en' => 'Event schedule (Schedule Tab)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Mergi la tab-ul [Program]. Primul camp este "Durata" cu 4 optiuni:',
                                'en' => 'Go to the [Program] tab. The first field is "Duration" with 4 options:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[O singura zi] — evenimentul are loc intr-o singura zi, cu ora de inceput si sfarsit.',
                                'en' => '[O singura zi] — the event takes place in a single day, with start and end time.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Interval] — evenimentul dureaza mai multe zile consecutive (ex: festival de 3 zile). Setezi data de inceput si data de sfarsit.',
                                'en' => '[Interval] — the event spans multiple consecutive days (e.g. 3-day festival). You set start date and end date.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Mai multe zile] — evenimentul are zile specifice (nu neaparat consecutive). Adaugi manual fiecare zi cu data si orele ei.',
                                'en' => '[Mai multe zile] — the event has specific days (not necessarily consecutive). You manually add each day with its date and times.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Recurent] — evenimentul se repeta pe o programare regulata (saptamanal, lunar). Setezi data de start, frecventa si numarul de repetitii.',
                                'en' => '[Recurent] — the event repeats on a regular schedule (weekly, monthly). You set start date, frequency and number of repetitions.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Data eveniment', 'en' => 'Event date'],
                            'description' => [
                                'ro' => 'Data la care are loc evenimentul. Pentru modul "O singura zi" completezi o singura data. Pentru "Interval" completezi data de inceput si sfarsit.',
                                'en' => 'The date when the event takes place. For "Single day" mode fill one date. For "Range" fill start and end date.',
                            ],
                            'required' => true,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                        [
                            'name' => ['ro' => 'Ora de inceput', 'en' => 'Start time'],
                            'description' => [
                                'ro' => 'Ora la care incepe efectiv evenimentul.',
                                'en' => 'The time when the event actually starts.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                        [
                            'name' => ['ro' => 'Ora de deschidere', 'en' => 'Door time'],
                            'description' => [
                                'ro' => 'Ora la care se deschid portile/usile (de obicei inainte de ora de inceput). Aceasta informatie apare pe bilet.',
                                'en' => 'The time when doors open (usually before start time). This information appears on the ticket.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                        [
                            'name' => ['ro' => 'Ora de sfarsit', 'en' => 'End time'],
                            'description' => [
                                'ro' => 'Ora estimata de sfarsit a evenimentului.',
                                'en' => 'The estimated end time of the event.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                        [
                            'name' => ['ro' => 'Locatie', 'en' => 'Venue'],
                            'description' => [
                                'ro' => 'Selecteaza locatia evenimentului din lista de locatii existente. Poti cauta dupa nume. Orasul se completeaza automat din locatia selectata.',
                                'en' => 'Select the event venue from the existing venues list. You can search by name. The city is auto-filled from the selected venue.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                        [
                            'name' => ['ro' => 'Harta de locuri', 'en' => 'Seating layout'],
                            'description' => [
                                'ro' => 'Daca locatia selectata are harti de locuri publicate, poti alege una. Permite vanzarea de bilete cu loc alocat. Apare doar daca locatia are harti disponibile.',
                                'en' => 'If the selected venue has published seating layouts, you can choose one. Enables selling tickets with assigned seats. Only appears if the venue has available layouts.',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                        [
                            'name' => ['ro' => 'Website URL', 'en' => 'Website URL'],
                            'description' => [
                                'ro' => 'Link catre un site extern al evenimentului (daca exista).',
                                'en' => 'Link to an external event website (if any).',
                            ],
                            'required' => false,
                            'tab' => ['ro' => 'Program', 'en' => 'Schedule'],
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Pentru modurile "Mai multe zile" si "Recurent": foloseste butonul [+] pentru a adauga zilele suplimentare, fiecare cu data si orele proprii.',
                                'en' => 'For "Multi-day" and "Recurring" modes: use the [+] button to add additional days, each with its own date and times.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 4: Featured Events
                [
                    'id' => 'featured',
                    'title' => ['ro' => 'Evenimente featured (promovate pe site)', 'en' => 'Featured events (promoted on site)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In tab-ul [Detalii], gasesti sectiunea "Featured" cu 3 comutatoare:',
                                'en' => 'In the [Detalii] tab, you will find the "Featured" section with 3 toggles:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Featured pe Homepage', 'en' => 'Homepage Featured'],
                            'description' => [
                                'ro' => 'Evenimentul apare in sectiunea speciala de pe pagina principala. Poti incarca o imagine separata, specifica pentru homepage.',
                                'en' => 'The event appears in the special section on the main page. You can upload a separate image, specific for the homepage.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Featured general', 'en' => 'General Featured'],
                            'description' => [
                                'ro' => 'Evenimentul este marcat ca "featured" in listari generale (ex: pagina cu toate evenimentele).',
                                'en' => 'The event is marked as "featured" in general listings (e.g. all events page).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Featured in categorie', 'en' => 'Category Featured'],
                            'description' => [
                                'ro' => 'Evenimentul apare ca "featured" in pagina categoriei sale.',
                                'en' => 'The event appears as "featured" on its category page.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Imaginea de homepage featured este separata de imaginea principala a evenimentului. Daca nu incarci una specifica, se va folosi imaginea principala.',
                                'en' => 'The homepage featured image is separate from the main event image. If you don\'t upload a specific one, the main image will be used.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Related Events
                [
                    'id' => 'related-events',
                    'title' => ['ro' => 'Evenimente similare / relationate', 'en' => 'Related events'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In partea de jos a tab-ului [Detalii], gasesti sectiunea "Evenimente similare personalizate".',
                                'en' => 'At the bottom of the [Detalii] tab, you will find the "Custom related events" section.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti selecta manual pana la 8 evenimente care vor fi afisate ca "similare" pe pagina acestui eveniment.',
                                'en' => 'You can manually select up to 8 events that will be displayed as "similar" on this event\'s page.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Daca nu selectezi manual niciun eveniment, sistemul va afisa automat evenimente din aceeasi categorie sau de la acelasi organizator.',
                                'en' => 'If you don\'t manually select any events, the system will automatically show events from the same category or organizer.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 6: Edit an existing event
                [
                    'id' => 'edit-event',
                    'title' => ['ro' => 'Cum editezi un eveniment existent', 'en' => 'How to edit an existing event'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral, apasa pe [Evenimente] pentru a vedea lista tuturor evenimentelor.',
                                'en' => 'From the left sidebar, click on [Evenimente] to see the list of all events.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Gaseste evenimentul dorit folosind campul de cautare din partea de sus sau filtrele disponibile (categorie, status, locatie, organizator, data).',
                                'en' => 'Find the desired event using the search field at the top or the available filters (category, status, venue, organizer, date).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe numele evenimentului sau pe iconita de editare (creion) din coloana de actiuni.',
                                'en' => 'Click on the event name or on the edit icon (pencil) in the actions column.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Modifica campurile dorite si apasa [Salveaza] din partea de jos.',
                                'en' => 'Modify the desired fields and click [Salveaza] at the bottom.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Seria evenimentului (ex: AMB-123) nu se poate modifica dupa creare. Slug-ul se poate schimba, dar asta va modifica adresa URL a evenimentului.',
                                'en' => 'The event series (e.g. AMB-123) cannot be changed after creation. The slug can be changed, but this will modify the event\'s URL.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 7: Event list overview
                [
                    'id' => 'event-list',
                    'title' => ['ro' => 'Lista de evenimente — cum o folosesti', 'en' => 'Event list — how to use it'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina [Evenimente] afiseaza toate evenimentele intr-un tabel cu urmatoarele coloane:',
                                'en' => 'The [Evenimente] page displays all events in a table with the following columns:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Imagine', 'en' => 'Image'],
                            'description' => [
                                'ro' => 'Miniatura imaginii principale a evenimentului.',
                                'en' => 'Thumbnail of the event\'s main image.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Nume eveniment', 'en' => 'Event name'],
                            'description' => [
                                'ro' => 'Titlul evenimentului. Poti cauta dupa titlu folosind campul de cautare.',
                                'en' => 'The event title. You can search by title using the search field.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Data', 'en' => 'Date'],
                            'description' => [
                                'ro' => 'Data evenimentului. Poti sorta coloana pentru a vedea evenimentele in ordine cronologica.',
                                'en' => 'The event date. You can sort the column to see events in chronological order.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Locatie', 'en' => 'Venue'],
                            'description' => [
                                'ro' => 'Numele locatiei unde are loc evenimentul.',
                                'en' => 'The name of the venue where the event takes place.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Categorie', 'en' => 'Category'],
                            'description' => [
                                'ro' => 'Categoria evenimentului (Concert, Festival, etc.).',
                                'en' => 'The event category (Concert, Festival, etc.).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Eticheta colorata cu starea evenimentului: activ, sold out, anulat, amanat.',
                                'en' => 'Colored badge with event status: active, sold out, cancelled, postponed.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Organizator', 'en' => 'Organizer'],
                            'description' => [
                                'ro' => 'Organizatorul asociat evenimentului.',
                                'en' => 'The organizer associated with the event.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Foloseste filtrele din partea de sus a tabelului pentru a gasi rapid evenimentele dorite. Poti filtra dupa: categorie, status, locatie, organizator si interval de date.',
                                'en' => 'Use the filters at the top of the table to quickly find desired events. You can filter by: category, status, venue, organizer and date range.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 8: Import external tickets
                [
                    'id' => 'import-tickets',
                    'title' => ['ro' => 'Import bilete externe', 'en' => 'Import external tickets'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Deschide un eveniment existent si mergi la tab-ul sau pagina de [Import bilete externe].',
                                'en' => 'Open an existing event and go to the [Import bilete externe] tab or page.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Descarca template-ul CSV folosind butonul [Descarca template CSV].',
                                'en' => 'Download the CSV template using the [Descarca template CSV] button.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza fisierul CSV cu datele biletelor (cod bilet, tip bilet, nume beneficiar, email, etc.).',
                                'en' => 'Fill in the CSV file with ticket data (ticket code, ticket type, beneficiary name, email, etc.).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Incarca fisierul CSV si specifica sursa biletelor (numele platformei externe). Apasa [Import] pentru a procesa.',
                                'en' => 'Upload the CSV file and specify the ticket source (external platform name). Click [Import] to process.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Biletele importate vor aparea in lista de bilete cu sursa externa marcata. Asigura-te ca formatul CSV respecta exact template-ul descarcat.',
                                'en' => 'Imported tickets will appear in the ticket list with the external source marked. Make sure the CSV format exactly matches the downloaded template.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],
            ],
        ];
    }
}
