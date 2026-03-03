<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class TicketsManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-tickets';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Bilete', 'en' => 'Tickets'],
            'description' => [
                'ro' => 'Ghid complet pentru vizualizarea, cautarea si filtrarea biletelor. Biletele sunt create automat prin comenzi si nu pot fi adaugate sau editate manual.',
                'en' => 'Complete guide for viewing, searching and filtering tickets. Tickets are created automatically through orders and cannot be added or edited manually.',
            ],
            'icon' => 'heroicon-o-ticket',
            'sections' => [
                // Section 1: View tickets
                [
                    'id' => 'view-tickets',
                    'title' => ['ro' => 'Cum accesezi si vizualizezi lista de bilete', 'en' => 'How to access and view the tickets list'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Bilete].',
                                'en' => 'From the left sidebar menu, click on [Bilete].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina cu lista tuturor biletelor, afisate intr-un tabel cu mai multe coloane.',
                                'en' => 'The page opens with the list of all tickets, displayed in a table with multiple columns.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Aceasta pagina este doar pentru vizualizare (read-only). Nu poti crea sau edita bilete manual — acestea sunt generate automat cand se plaseaza o comanda sau o invitatie.',
                                'en' => 'This page is read-only. You cannot create or edit tickets manually — they are generated automatically when an order or invitation is placed.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti copia codul unui bilet, cauta dupa cod/nume/email, sau folosi filtrele pentru a gasi biletele dorite.',
                                'en' => 'You can copy a ticket code, search by code/name/email, or use the filters to find specific tickets.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Biletele sunt create automat la plasarea comenzilor. Pentru a genera bilete noi, creaza o comanda sau trimite o invitatie din modulul de comenzi.',
                                'en' => 'Tickets are created automatically when orders are placed. To generate new tickets, create an order or send an invitation from the orders module.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 2: Table columns
                [
                    'id' => 'ticket-columns',
                    'title' => ['ro' => 'Coloanele din tabelul de bilete', 'en' => 'Understanding the ticket table columns'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Tabelul de bilete afiseaza urmatoarele coloane cu informatii despre fiecare bilet:',
                                'en' => 'The tickets table displays the following columns with information about each ticket:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Cod bilet', 'en' => 'Ticket Code'],
                            'description' => [
                                'ro' => 'Codul unic al biletului. Poti copia codul apasand pe iconita de copiere de langa el. Acest cod este cel care apare pe biletul fizic/digital si se scaneaza la intrare.',
                                'en' => 'The unique ticket code. You can copy the code by clicking the copy icon next to it. This is the code that appears on the physical/digital ticket and is scanned at entry.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Eveniment', 'en' => 'Event'],
                            'description' => [
                                'ro' => 'Numele evenimentului pentru care a fost emis biletul.',
                                'en' => 'The name of the event for which the ticket was issued.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tip bilet', 'en' => 'Ticket Type'],
                            'description' => [
                                'ro' => 'Tipul/categoria biletului (ex: General Access, VIP, Early Bird, etc.), asa cum a fost definit in eveniment.',
                                'en' => 'The ticket type/category (e.g. General Access, VIP, Early Bird, etc.), as defined in the event.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Comanda', 'en' => 'Order ID'],
                            'description' => [
                                'ro' => 'ID-ul comenzii din care face parte biletul. Apasa pe link pentru a deschide detaliile comenzii asociate.',
                                'en' => 'The ID of the order the ticket belongs to. Click the link to open the associated order details.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Beneficiar', 'en' => 'Beneficiary'],
                            'description' => [
                                'ro' => 'Numele complet al persoanei care va folosi biletul (beneficiarul).',
                                'en' => 'The full name of the person who will use the ticket (the beneficiary).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email a beneficiarului biletului.',
                                'en' => 'The email address of the ticket beneficiary.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Starea curenta a biletului, afisata ca o eticheta colorata (badge). Statusurile posibile sunt: Valid, Folosit, Anulat, Returnat.',
                                'en' => 'The current status of the ticket, displayed as a colored badge. Possible statuses are: Valid, Used, Cancelled, Refunded.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Loc', 'en' => 'Seat Label'],
                            'description' => [
                                'ro' => 'Eticheta locului alocat (daca evenimentul are harta de locuri). Apare doar pentru evenimentele cu locuri numerotate.',
                                'en' => 'The assigned seat label (if the event has a seating map). Only appears for events with numbered seating.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Data', 'en' => 'Date'],
                            'description' => [
                                'ro' => 'Data la care a fost creat biletul (data comenzii).',
                                'en' => 'The date when the ticket was created (order date).',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Apasa pe iconita de copiere de langa codul biletului pentru a-l copia rapid in clipboard. Util pentru a trimite codul unui client prin email sau chat.',
                                'en' => 'Click the copy icon next to the ticket code to quickly copy it to clipboard. Useful for sending the code to a customer via email or chat.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 3: Ticket statuses
                [
                    'id' => 'ticket-statuses',
                    'title' => ['ro' => 'Ce inseamna fiecare status de bilet', 'en' => 'What each ticket status means'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Fiecare bilet are un status care indica starea sa curenta. Statusurile sunt afisate ca etichete colorate (badge-uri) in tabel:',
                                'en' => 'Each ticket has a status indicating its current state. Statuses are displayed as colored badges in the table:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Valid',
                            'description' => [
                                'ro' => 'Biletul este activ si poate fi folosit la intrare. Acesta este statusul implicit dupa cumparare sau emiterea unei invitatii.',
                                'en' => 'The ticket is active and can be used at entry. This is the default status after purchase or issuing an invitation.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Folosit', 'en' => 'Used'],
                            'description' => [
                                'ro' => 'Biletul a fost scanat si utilizat la intrare. Nu mai poate fi folosit din nou. Statusul se schimba automat la scanarea codului QR sau a codului de bare.',
                                'en' => 'The ticket has been scanned and used at entry. It cannot be used again. The status changes automatically when the QR code or barcode is scanned.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Anulat', 'en' => 'Cancelled'],
                            'description' => [
                                'ro' => 'Biletul a fost anulat si nu mai este valid. Poate fi rezultatul anularii comenzii sau al unei actiuni administrative.',
                                'en' => 'The ticket has been cancelled and is no longer valid. This can be the result of order cancellation or an administrative action.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Returnat', 'en' => 'Refunded'],
                            'description' => [
                                'ro' => 'Biletul a fost returnat si suma a fost rambursata clientului. Biletul nu mai este valid pentru intrare.',
                                'en' => 'The ticket has been refunded and the amount was returned to the customer. The ticket is no longer valid for entry.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Un bilet cu status "Folosit" nu mai poate fi scanat din nou. Daca un client incearca sa reintre, sistemul va afisa un mesaj ca biletul a fost deja utilizat.',
                                'en' => 'A ticket with "Used" status cannot be scanned again. If a customer tries to re-enter, the system will display a message that the ticket has already been used.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 4: Filters and search
                [
                    'id' => 'filters-search',
                    'title' => ['ro' => 'Filtrarea si cautarea biletelor', 'en' => 'Filtering and searching tickets'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In partea de sus a tabelului gasesti campul de cautare. Introdu codul biletului, numele beneficiarului sau adresa de email pentru a gasi rapid un bilet.',
                                'en' => 'At the top of the table you will find the search field. Enter the ticket code, beneficiary name or email address to quickly find a ticket.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Langa campul de cautare, apasa pe iconita de filtrare pentru a deschide panoul de filtre.',
                                'en' => 'Next to the search field, click the filter icon to open the filters panel.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Filtrul [Status] iti permite sa afisezi doar biletele cu un anumit status: Valid, Folosit, Anulat sau Returnat.',
                                'en' => 'The [Status] filter allows you to display only tickets with a specific status: Valid, Used, Cancelled or Refunded.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Filtrul [Tip] iti permite sa separi biletele generate din invitatii de cele cumparate prin comenzi normale.',
                                'en' => 'The [Tip] filter allows you to separate tickets generated from invitations from those purchased through regular orders.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti combina mai multe filtre simultan. De exemplu, poti filtra dupa Status = "Valid" si Tip = "Invitatii" pentru a vedea doar invitatiile active.',
                                'en' => 'You can combine multiple filters simultaneously. For example, you can filter by Status = "Valid" and Type = "Invitations" to see only active invitations.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Cautarea functioneaza simultan pe codul biletului, numele beneficiarului si email. Nu trebuie sa specifici in ce camp cauti — sistemul verifica toate cele 3 campuri automat.',
                                'en' => 'The search works simultaneously on the ticket code, beneficiary name and email. You do not need to specify which field to search — the system checks all 3 fields automatically.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Invitations vs regular tickets
                [
                    'id' => 'invitations-vs-regular',
                    'title' => ['ro' => 'Diferenta intre invitatii si bilete normale', 'en' => 'Difference between invitations and regular tickets'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In sistem exista doua tipuri principale de bilete, in functie de modul in care au fost create:',
                                'en' => 'In the system there are two main types of tickets, depending on how they were created:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Bilete din comenzi normale — sunt biletele cumparate de clienti prin procesul standard de comanda (online sau la casa). Acestea au o comanda asociata cu plata.',
                                'en' => 'Tickets from regular orders — these are tickets purchased by customers through the standard order process (online or at the box office). They have an associated order with payment.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Bilete din invitatii — sunt bilete gratuite emise de organizator sau administrator. Nu au plata asociata si sunt marcate ca invitatii in sistem.',
                                'en' => 'Tickets from invitations — these are free tickets issued by the organizer or administrator. They have no associated payment and are marked as invitations in the system.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Ambele tipuri de bilete functioneaza identic la scanare si la intrare. Diferenta este doar in modul de emitere si in rapoartele financiare.',
                                'en' => 'Both ticket types work identically at scanning and entry. The difference is only in the issuance method and financial reports.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste filtrul [Tip] din tabel pentru a separa cele doua categorii si a vedea cate invitatii au fost emise pentru un eveniment.',
                                'en' => 'Use the [Tip] filter in the table to separate the two categories and see how many invitations were issued for an event.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Invitatiile nu genereaza venituri si nu apar in rapoartele de vanzari. Ele sunt insa contorizate separat in statisticile evenimentului pentru a avea o imagine completa a participantilor.',
                                'en' => 'Invitations do not generate revenue and do not appear in sales reports. However, they are counted separately in event statistics to have a complete picture of attendees.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Numarul de invitatii emise se scade din stocul total de bilete al evenimentului, la fel ca biletele vandute.',
                                'en' => 'The number of invitations issued is deducted from the total ticket stock of the event, just like sold tickets.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],
            ],
        ];
    }
}
