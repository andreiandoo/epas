<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class OrdersManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-orders';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Comenzi', 'en' => 'Orders'],
            'description' => [
                'ro' => 'Ghid complet pentru vizualizarea si gestionarea comenzilor de bilete pe platforma.',
                'en' => 'Complete guide for viewing and managing ticket orders on the platform.',
            ],
            'icon' => 'heroicon-o-shopping-cart',
            'sections' => [
                // Section 1: View Orders List
                [
                    'id' => 'view-orders',
                    'title' => ['ro' => 'Cum vizualizezi lista de comenzi', 'en' => 'How to view the orders list'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Comenzi].',
                                'en' => 'From the left sidebar menu, click on [Comenzi].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina cu lista tuturor comenzilor afisate intr-un tabel. Comenzile sunt sortate implicit dupa data crearii (cele mai recente primele).',
                                'en' => 'The page opens showing all orders displayed in a table. Orders are sorted by default by creation date (most recent first).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti folosi campul de cautare din partea de sus pentru a gasi rapid o comanda dupa ID sau dupa numele/emailul clientului.',
                                'en' => 'You can use the search field at the top to quickly find an order by ID or by the customer\'s name/email.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a vedea detaliile unei comenzi, apasa pe randul respectiv din tabel sau pe butonul de vizualizare (ochi) din coloana de actiuni.',
                                'en' => 'To see the details of an order, click on its row in the table or on the view button (eye) in the actions column.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'ID Comanda', 'en' => 'Order ID'],
                            'description' => [
                                'ro' => 'Identificatorul unic al comenzii. Se poate cauta direct in campul de cautare.',
                                'en' => 'The unique order identifier. Can be searched directly in the search field.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Client', 'en' => 'Customer'],
                            'description' => [
                                'ro' => 'Numele si adresa de email a clientului care a plasat comanda.',
                                'en' => 'The name and email address of the customer who placed the order.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Eveniment', 'en' => 'Event'],
                            'description' => [
                                'ro' => 'Evenimentul pentru care au fost cumparate biletele.',
                                'en' => 'The event for which the tickets were purchased.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Total',
                            'description' => [
                                'ro' => 'Suma totala a comenzii, afisata in moneda configurata.',
                                'en' => 'The total amount of the order, displayed in the configured currency.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Starea curenta a comenzii, afisata ca o eticheta colorata (pending, confirmed, cancelled, refunded, partially_refunded).',
                                'en' => 'The current order status, displayed as a colored badge (pending, confirmed, cancelled, refunded, partially_refunded).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Metoda de plata', 'en' => 'Payment method'],
                            'description' => [
                                'ro' => 'Metoda de plata folosita de client (card, transfer bancar, etc.).',
                                'en' => 'The payment method used by the customer (card, bank transfer, etc.).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Data crearii', 'en' => 'Created date'],
                            'description' => [
                                'ro' => 'Data si ora la care a fost plasata comanda. Coloana este sortabila.',
                                'en' => 'The date and time when the order was placed. This column is sortable.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Comenzile sunt doar pentru vizualizare — nu poti crea comenzi manual din panou. Comenzile sunt generate automat cand un client cumpara bilete pe site.',
                                'en' => 'Orders are view-only — you cannot create orders manually from the panel. Orders are generated automatically when a customer purchases tickets on the site.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 2: Order Details Page
                [
                    'id' => 'order-details',
                    'title' => ['ro' => 'Pagina de detalii a unei comenzi', 'en' => 'Order details page'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina de detalii este impartita in doua coloane: una principala (3/4 din latime) si una secundara (1/4 din latime).',
                                'en' => 'The details page is split into two columns: a main one (3/4 width) and a secondary one (1/4 width).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In coloana principala (stanga) gasesti: cardul hero al comenzii (status, ID, total), sectiunea Client, sectiunea Eveniment (colapsabila), sectiunea Bilete (cu actiuni de descarcare) si Timeline-ul comenzii.',
                                'en' => 'In the main column (left) you will find: the order hero card (status, ID, total), Client section, Event section (collapsible), Tickets section (with download actions) and the order Timeline.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In coloana secundara (dreapta) gasesti: defalcarea pretului, detaliile comisioanelor, actiuni rapide si detaliile platii.',
                                'en' => 'In the secondary column (right) you will find: price breakdown, commission details, quick actions and payment details.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Cardul hero din partea de sus afiseaza status-ul comenzii cu o eticheta colorata, ID-ul comenzii si suma totala.',
                                'en' => 'The hero card at the top displays the order status with a colored badge, the order ID and the total amount.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea Client afiseaza numele, emailul si alte informatii despre cumparator.',
                                'en' => 'The Client section displays the name, email and other information about the buyer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea Eveniment (colapsabila) arata detaliile evenimentului asociat comenzii — titlu, data, locatie.',
                                'en' => 'The Event section (collapsible) shows the details of the event associated with the order — title, date, venue.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea Bilete listeaza toate biletele din comanda cu tipul, pretul si actiuni pentru descarcare individuala.',
                                'en' => 'The Tickets section lists all tickets in the order with type, price and actions for individual download.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Timeline-ul arata istoricul complet al comenzii: cand a fost creata, confirmata, modificata sau rambursata.',
                                'en' => 'The Timeline shows the complete order history: when it was created, confirmed, modified or refunded.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Defalcare pret', 'en' => 'Price breakdown'],
                            'description' => [
                                'ro' => 'Afisata in coloana dreapta — include pretul biletelor, taxele, comisioanele si totalul final.',
                                'en' => 'Displayed in the right column — includes ticket price, taxes, commissions and the final total.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Detalii comision', 'en' => 'Commission details'],
                            'description' => [
                                'ro' => 'Afiseaza procentul si suma comisionului aplicat, precum si suma neta care revine organizatorului.',
                                'en' => 'Displays the commission percentage and amount applied, as well as the net amount going to the organizer.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Detalii plata', 'en' => 'Payment details'],
                            'description' => [
                                'ro' => 'Metoda de plata, ID-ul tranzactiei, status-ul platii si data procesarii.',
                                'en' => 'Payment method, transaction ID, payment status and processing date.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea Eveniment este colapsabila — apasa pe antetul ei pentru a o deschide sau inchide. Util cand vrei sa te concentrezi pe bilete sau pe detaliile platii.',
                                'en' => 'The Event section is collapsible — click on its header to expand or collapse it. Useful when you want to focus on tickets or payment details.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 3: Order Statuses
                [
                    'id' => 'order-statuses',
                    'title' => ['ro' => 'Status-urile comenzilor', 'en' => 'Order statuses'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Fiecare comanda are un status care indica starea ei curenta. Status-ul apare ca o eticheta colorata atat in lista de comenzi cat si in pagina de detalii.',
                                'en' => 'Each order has a status indicating its current state. The status appears as a colored badge both in the orders list and in the details page.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Pending',
                            'description' => [
                                'ro' => 'Comanda a fost plasata dar plata nu a fost inca confirmata. Aceasta este starea initiala a fiecarei comenzi noi. Clientul poate sa fi initiat plata dar procesatorul inca nu a trimis confirmarea.',
                                'en' => 'The order has been placed but payment has not been confirmed yet. This is the initial state of every new order. The customer may have initiated payment but the processor has not yet sent confirmation.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Confirmed',
                            'description' => [
                                'ro' => 'Plata a fost primita si confirmata cu succes. Biletele au fost generate si sunt disponibile pentru descarcare. Clientul a primit emailul de confirmare cu biletele.',
                                'en' => 'Payment has been received and confirmed successfully. Tickets have been generated and are available for download. The customer has received the confirmation email with tickets.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Cancelled',
                            'description' => [
                                'ro' => 'Comanda a fost anulata. Poate fi anulata de sistem (plata esuata, timeout), de administrator sau de client. Biletele asociate nu mai sunt valide.',
                                'en' => 'The order has been cancelled. It can be cancelled by the system (failed payment, timeout), by an administrator or by the customer. Associated tickets are no longer valid.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Refunded',
                            'description' => [
                                'ro' => 'Intreaga suma a comenzii a fost rambursata catre client. Biletele au fost invalidate. Aceasta actiune este de obicei initiata printr-o cerere de rambursare (Refund Request).',
                                'en' => 'The entire order amount has been refunded to the customer. Tickets have been invalidated. This action is usually initiated through a Refund Request.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Partially Refunded',
                            'description' => [
                                'ro' => 'O parte din suma comenzii a fost rambursata (de exemplu, doar un bilet din mai multe). Biletele nerambursate raman valide. Suma rambursata si cea ramasa sunt afisate in detaliile comenzii.',
                                'en' => 'Part of the order amount has been refunded (for example, only one ticket out of several). Non-refunded tickets remain valid. The refunded and remaining amounts are displayed in the order details.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Schimbarea manuala a status-ului unei comenzi trebuie facuta cu atentie. De exemplu, trecerea la "Confirmed" nu efectueaza automat plata — este doar o actualizare a status-ului afisat.',
                                'en' => 'Manually changing an order\'s status should be done with care. For example, switching to "Confirmed" does not automatically process payment — it is only an update of the displayed status.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 4: Order Actions
                [
                    'id' => 'order-actions',
                    'title' => ['ro' => 'Actiuni disponibile pentru comenzi', 'en' => 'Available order actions'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In pagina de detalii a comenzii, in coloana dreapta la sectiunea "Actiuni rapide", ai acces la urmatoarele actiuni:',
                                'en' => 'In the order details page, in the right column at the "Quick actions" section, you have access to the following actions:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Retrimite confirmare] — retrimite emailul de confirmare catre client, inclusiv biletele atasate. Util daca clientul nu a primit emailul initial sau l-a sters accidental.',
                                'en' => '[Retrimite confirmare] — resends the confirmation email to the customer, including attached tickets. Useful if the customer did not receive the initial email or accidentally deleted it.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Descarca bilete] — descarca toate biletele din comanda intr-un singur fisier PDF. Util pentru verificare sau pentru a le trimite manual clientului.',
                                'en' => '[Descarca bilete] — downloads all tickets from the order in a single PDF file. Useful for verification or for manually sending them to the customer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Tipareste factura] — genereaza si deschide factura asociata comenzii pentru tiparire sau descarcare in format PDF.',
                                'en' => '[Tipareste factura] — generates and opens the invoice associated with the order for printing or downloading in PDF format.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Schimba status] — deschide un dialog modal in care poti selecta noul status al comenzii. Aceasta actiune modifica doar status-ul din sistem si nu proceseaza plati sau rambursari automate.',
                                'en' => '[Schimba status] — opens a modal dialog where you can select the new order status. This action only changes the status in the system and does not process automatic payments or refunds.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Actiunea [Retrimite confirmare] trimite emailul folosind template-ul de confirmare configurat. Asigura-te ca template-ul de email este corect setat in sectiunea Comunicare.',
                                'en' => 'The [Retrimite confirmare] action sends the email using the configured confirmation template. Make sure the email template is correctly set up in the Communications section.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Actiunea [Schimba status] nu efectueaza rambursari automate. Pentru a procesa o rambursare, foloseste modulul de Cereri de rambursare (Refund Requests).',
                                'en' => 'The [Schimba status] action does not process automatic refunds. To process a refund, use the Refund Requests module.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 5: Filters and Search
                [
                    'id' => 'filters-search',
                    'title' => ['ro' => 'Filtrare si cautare comenzi', 'en' => 'Filtering and searching orders'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In partea de sus a tabelului de comenzi gasesti campul de cautare si butonul de filtre pentru a gasi rapid comenzile dorite.',
                                'en' => 'At the top of the orders table you will find the search field and the filters button to quickly find the desired orders.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Campul de cautare permite gasirea comenzilor dupa ID-ul comenzii, numele clientului sau adresa de email a clientului.',
                                'en' => 'The search field allows finding orders by order ID, customer name or customer email address.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe butonul [Filtre] pentru a deschide panoul de filtre avansate.',
                                'en' => 'Click the [Filtre] button to open the advanced filters panel.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Filtru Status', 'en' => 'Status filter'],
                            'description' => [
                                'ro' => 'Filtreaza comenzile dupa status: Pending, Confirmed, Cancelled, Refunded sau Partially Refunded. Poti selecta un singur status sau mai multe.',
                                'en' => 'Filter orders by status: Pending, Confirmed, Cancelled, Refunded or Partially Refunded. You can select one or multiple statuses.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Metoda de plata', 'en' => 'Payment method filter'],
                            'description' => [
                                'ro' => 'Filtreaza comenzile dupa metoda de plata folosita (card, transfer bancar, etc.).',
                                'en' => 'Filter orders by the payment method used (card, bank transfer, etc.).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Interval de date', 'en' => 'Date range filter'],
                            'description' => [
                                'ro' => 'Selecteaza o data de inceput si o data de sfarsit pentru a vedea doar comenzile plasate in acel interval.',
                                'en' => 'Select a start date and an end date to see only the orders placed within that range.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Organizator', 'en' => 'Organizer filter'],
                            'description' => [
                                'ro' => 'Filtreaza comenzile dupa organizatorul evenimentului asociat. Util cand gestionezi mai multi organizatori.',
                                'en' => 'Filter orders by the organizer of the associated event. Useful when managing multiple organizers.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Poti combina mai multe filtre simultan. De exemplu, poti filtra dupa status "Confirmed" si un interval de date pentru a vedea toate comenzile confirmate dintr-o anumita perioada.',
                                'en' => 'You can combine multiple filters simultaneously. For example, you can filter by status "Confirmed" and a date range to see all confirmed orders from a specific period.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Coloana "Data crearii" este sortabila — apasa pe antetul coloanei pentru a ordona comenzile cronologic (crescator sau descrescator).',
                                'en' => 'The "Created date" column is sortable — click on the column header to sort orders chronologically (ascending or descending).',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 6: Refunds
                [
                    'id' => 'refunds',
                    'title' => ['ro' => 'Cereri de rambursare', 'en' => 'Refund requests'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Cererile de rambursare sunt gestionate printr-un modul separat: [Cereri de rambursare] (Refund Requests). Acest modul nu face parte din ecranul de Comenzi, ci este accesibil separat din meniul lateral.',
                                'en' => 'Refund requests are managed through a separate module: [Cereri de rambursare] (Refund Requests). This module is not part of the Orders screen but is accessible separately from the sidebar menu.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Cand un client solicita o rambursare, se creeaza o cerere in modulul de rambursari care contine: comanda asociata, motivul cererii, suma solicitata si status-ul procesarii.',
                                'en' => 'When a customer requests a refund, a request is created in the refund module containing: the associated order, the reason for the request, the requested amount and the processing status.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Dupa aprobarea sau procesarea unei rambursari, status-ul comenzii asociate se actualizeaza automat la "Refunded" sau "Partially Refunded", in functie de suma rambursata.',
                                'en' => 'After approving or processing a refund, the associated order status is automatically updated to "Refunded" or "Partially Refunded", depending on the refunded amount.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Nu schimba manual status-ul unei comenzi la "Refunded" fara a procesa efectiv rambursarea prin modulul dedicat. Status-ul trebuie sa reflecte realitatea financiara a tranzactiei.',
                                'en' => 'Do not manually change an order\'s status to "Refunded" without actually processing the refund through the dedicated module. The status should reflect the financial reality of the transaction.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],
            ],
        ];
    }
}
