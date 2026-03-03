<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CustomersManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-customers';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Clienti', 'en' => 'Customers'],
            'description' => [
                'ro' => 'Ghid complet pentru crearea, editarea si gestionarea clientilor pe platforma, inclusiv tipuri de conturi, preferinte si actiuni rapide.',
                'en' => 'Complete guide for creating, editing and managing customers on the platform, including account types, preferences and quick actions.',
            ],
            'icon' => 'heroicon-o-users',
            'sections' => [
                // Section 1: Create Customer
                [
                    'id' => 'create-customer',
                    'title' => ['ro' => 'Cum creezi un client manual', 'en' => 'How to create a customer manually'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Clienti] din grupul "Customers".',
                                'en' => 'From the left sidebar menu, click on [Clienti] in the "Customers" group.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In pagina "Clienti" apasa butonul [Creare User] din coltul din dreapta sus.',
                                'en' => 'On the "Clienti" page, click the [Creare User] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de creare. Formularul are o coloana principala (3/4 din latime) cu toate campurile. Coloana laterala dreapta (1/4) cu previzualizare, statistici si actiuni rapide apare doar la editare.',
                                'en' => 'The creation form opens. The form has a main column (3/4 width) with all fields. The right sidebar column (1/4) with preview, statistics and quick actions only appears when editing.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza campurile obligatorii: Email, First Name si Last Name. Selecteaza un status (implicit "Active").',
                                'en' => 'Fill in the required fields: Email, First Name and Last Name. Select a status (default is "Active").',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Optional, completeaza sectiunile "Personal Details" (telefon, data nasterii, gen, limba) si "Address" (adresa, oras, judet, cod postal, tara).',
                                'en' => 'Optionally, fill in the "Personal Details" section (phone, birth date, gender, language) and "Address" section (street, city, state, postal code, country).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa butonul [Salveaza] din partea de jos a paginii pentru a crea clientul.',
                                'en' => 'Click the [Salveaza] button at the bottom of the page to create the customer.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email a clientului. Camp obligatoriu, maxim 255 caractere. Trebuie sa fie un email valid. IMPORTANT: emailul nu poate fi modificat dupa creare (campul devine dezactivat la editare).',
                                'en' => 'The customer email address. Required field, maximum 255 characters. Must be a valid email. IMPORTANT: the email cannot be changed after creation (the field becomes disabled when editing).',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'First Name',
                            'description' => [
                                'ro' => 'Prenumele clientului. Camp obligatoriu, maxim 100 caractere.',
                                'en' => 'The customer first name. Required field, maximum 100 characters.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Last Name',
                            'description' => [
                                'ro' => 'Numele de familie al clientului. Camp obligatoriu, maxim 100 caractere.',
                                'en' => 'The customer last name. Required field, maximum 100 characters.',
                            ],
                            'required' => true,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Un client creat manual din admin nu va avea parola setata, deci va aparea ca "Guest" in lista. Clientul poate deveni "Registered" doar daca isi creeaza un cont prin site-ul public.',
                                'en' => 'A customer created manually from admin will not have a password set, so it will appear as "Guest" in the list. The customer can only become "Registered" if they create an account through the public website.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Verifica daca emailul clientului nu exista deja in sistem inainte de a-l crea. Emailul trebuie sa fie unic per marketplace.',
                                'en' => 'Check if the customer email does not already exist in the system before creating it. The email must be unique per marketplace.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 2: Customer Fields
                [
                    'id' => 'customer-fields',
                    'title' => ['ro' => 'Campurile formularului de client', 'en' => 'Customer form fields'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Formularul de client contine mai multe sectiuni. Sectiunea "Address" este pliata implicit si se poate deschide apasand pe ea. Sectiunile "Notification Preferences" si "Favorites" sunt vizibile doar la editare.',
                                'en' => 'The customer form contains multiple sections. The "Address" section is collapsed by default and can be opened by clicking on it. The "Notification Preferences" and "Favorites" sections are only visible when editing.',
                            ],
                        ],
                    ],
                    'fields' => [
                        // Account Information
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Sectiunea "Account Information". Adresa de email a clientului. Camp obligatoriu. La editare, campul este dezactivat si nu poate fi modificat.',
                                'en' => '"Account Information" section. The customer email address. Required field. When editing, the field is disabled and cannot be changed.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Sectiunea "Account Information". Statusul contului: "Active" (activ, poate accesa platforma) sau "Suspended" (suspendat, acces blocat). Implicit "Active".',
                                'en' => '"Account Information" section. Account status: "Active" (active, can access the platform) or "Suspended" (suspended, access blocked). Default is "Active".',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Email Verified',
                            'description' => [
                                'ro' => 'Sectiunea "Account Information". Toggle care indica daca emailul clientului a fost verificat. Activeaza-l pentru a marca manual emailul ca verificat.',
                                'en' => '"Account Information" section. Toggle indicating whether the customer email has been verified. Enable it to manually mark the email as verified.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Accepts Marketing',
                            'description' => [
                                'ro' => 'Sectiunea "Account Information". Toggle care indica daca clientul a acceptat sa primeasca comunicari de marketing (newslettere, oferte speciale).',
                                'en' => '"Account Information" section. Toggle indicating whether the customer has consented to receive marketing communications (newsletters, special offers).',
                            ],
                            'required' => false,
                        ],
                        // Personal Details
                        [
                            'name' => 'First Name',
                            'description' => [
                                'ro' => 'Sectiunea "Personal Details". Prenumele clientului. Camp obligatoriu, maxim 100 caractere.',
                                'en' => '"Personal Details" section. Customer first name. Required field, maximum 100 characters.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Last Name',
                            'description' => [
                                'ro' => 'Sectiunea "Personal Details". Numele de familie al clientului. Camp obligatoriu, maxim 100 caractere.',
                                'en' => '"Personal Details" section. Customer last name. Required field, maximum 100 characters.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Phone',
                            'description' => [
                                'ro' => 'Sectiunea "Personal Details". Numarul de telefon al clientului. Camp optional, maxim 50 caractere.',
                                'en' => '"Personal Details" section. Customer phone number. Optional field, maximum 50 characters.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Birth Date',
                            'description' => [
                                'ro' => 'Sectiunea "Personal Details". Data nasterii clientului. Camp optional, se selecteaza din calendar.',
                                'en' => '"Personal Details" section. Customer birth date. Optional field, selected from a date picker.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Gender',
                            'description' => [
                                'ro' => 'Sectiunea "Personal Details". Genul clientului: Male, Female sau Other. Camp optional.',
                                'en' => '"Personal Details" section. Customer gender: Male, Female or Other. Optional field.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Language (Limba)', 'en' => 'Language'],
                            'description' => [
                                'ro' => 'Sectiunea "Personal Details". Limba preferata a clientului: English, Romanian, German, French sau Spanish. Determina limba in care clientul primeste emailuri si notificari.',
                                'en' => '"Personal Details" section. Customer preferred language: English, Romanian, German, French or Spanish. Determines the language in which the customer receives emails and notifications.',
                            ],
                            'required' => false,
                        ],
                        // Address
                        [
                            'name' => 'Street Address',
                            'description' => [
                                'ro' => 'Sectiunea "Address" (pliata). Adresa stradala completa a clientului. Camp optional, maxim 255 caractere. Ocupa toata latimea formularului.',
                                'en' => '"Address" section (collapsed). Full street address of the customer. Optional field, maximum 255 characters. Spans full form width.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'City',
                            'description' => [
                                'ro' => 'Sectiunea "Address" (pliata). Orasul clientului. Camp optional, maxim 100 caractere.',
                                'en' => '"Address" section (collapsed). Customer city. Optional field, maximum 100 characters.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'State/Province',
                            'description' => [
                                'ro' => 'Sectiunea "Address" (pliata). Judetul sau provincia clientului. Camp optional, maxim 100 caractere.',
                                'en' => '"Address" section (collapsed). Customer state or province. Optional field, maximum 100 characters.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Postal Code',
                            'description' => [
                                'ro' => 'Sectiunea "Address" (pliata). Codul postal al clientului. Camp optional, maxim 20 caractere.',
                                'en' => '"Address" section (collapsed). Customer postal code. Optional field, maximum 20 characters.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Country Code',
                            'description' => [
                                'ro' => 'Sectiunea "Address" (pliata). Codul de tara ISO cu 2 litere (ex: RO, US, DE, FR). Camp optional, maxim 2 caractere.',
                                'en' => '"Address" section (collapsed). ISO 2-letter country code (e.g., RO, US, DE, FR). Optional field, maximum 2 characters.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Address" este pliata implicit. Apasa pe titlul sectiunii pentru a o deschide si a vedea campurile de adresa.',
                                'en' => 'The "Address" section is collapsed by default. Click on the section title to expand it and see the address fields.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Codul de tara trebuie sa fie in format ISO 2 litere (ex: RO pentru Romania, DE pentru Germania). Nu folosi numele complet al tarii.',
                                'en' => 'The country code must be in ISO 2-letter format (e.g., RO for Romania, DE for Germany). Do not use the full country name.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 3: Edit Customer
                [
                    'id' => 'edit-customer',
                    'title' => ['ro' => 'Editarea unui client (sectiuni suplimentare)', 'en' => 'Editing a customer (additional sections)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din lista de clienti, apasa pe randul clientului dorit sau pe iconita de editare (creion) din coloana de actiuni.',
                                'en' => 'From the customer list, click on the desired customer row or the edit icon (pencil) in the actions column.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'La editare, pe langa sectiunile de la creare, apar sectiuni suplimentare: "Notification Preferences", "Favorites" in coloana principala, si in coloana laterala dreapta: previzualizare client, statistici, metode de plata si actiuni rapide.',
                                'en' => 'When editing, in addition to the creation sections, additional sections appear: "Notification Preferences", "Favorites" in the main column, and in the right sidebar: customer preview, statistics, payment methods and quick actions.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Notification Preferences" contine toggle-uri pentru preferintele de notificari ale clientului. Acestea sunt setarile pe care clientul le-a ales prin site-ul public.',
                                'en' => 'The "Notification Preferences" section contains toggles for the customer notification preferences. These are the settings the customer has chosen through the public website.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Favorites" (pliata) afiseaza evenimentele din watchlist, artistii favoriti si locatiile favorite ale clientului. Aceasta sectiune este doar pentru vizualizare.',
                                'en' => 'The "Favorites" section (collapsed) displays the customer watchlist events, favorite artists and favorite venues. This section is for viewing only.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In coloana laterala dreapta, cardul de previzualizare arata initialele clientului, numele complet, emailul si badge-uri cu statusul (Active/Suspended), verificarea emailului si tipul contului (Guest/Registered).',
                                'en' => 'In the right sidebar, the preview card shows the customer initials, full name, email and badges with the status (Active/Suspended), email verification and account type (Guest/Registered).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Modifica campurile dorite si apasa [Salveaza] din partea de jos a paginii.',
                                'en' => 'Modify the desired fields and click [Salveaza] at the bottom of the page.',
                            ],
                        ],
                    ],
                    'fields' => [
                        // Notification Preferences
                        [
                            'name' => 'Event Reminders',
                            'description' => [
                                'ro' => 'Sectiunea "Notification Preferences" (doar editare). Daca este activat, clientul primeste un reminder cu 24h inainte de eveniment.',
                                'en' => '"Notification Preferences" section (edit only). When enabled, the customer receives a reminder 24 hours before the event.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Newsletter & Offers',
                            'description' => [
                                'ro' => 'Sectiunea "Notification Preferences" (doar editare). Daca este activat, clientul primeste informatii despre evenimente noi si oferte speciale.',
                                'en' => '"Notification Preferences" section (edit only). When enabled, the customer receives information about new events and special offers.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Favorite Updates',
                            'description' => [
                                'ro' => 'Sectiunea "Notification Preferences" (doar editare). Daca este activat, clientul primeste notificari cand evenimentele favorite se apropie.',
                                'en' => '"Notification Preferences" section (edit only). When enabled, the customer receives notifications when favorite events are approaching.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Browsing History',
                            'description' => [
                                'ro' => 'Sectiunea "Notification Preferences" (doar editare). Daca este activat, sistemul salveaza evenimentele vizualizate pentru recomandari personalizate.',
                                'en' => '"Notification Preferences" section (edit only). When enabled, the system saves viewed events for personalized recommendations.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Marketing Cookies',
                            'description' => [
                                'ro' => 'Sectiunea "Notification Preferences" (doar editare). Daca este activat, permite afisarea de reclame personalizate. Implicit dezactivat.',
                                'en' => '"Notification Preferences" section (edit only). When enabled, allows display of personalized ads. Disabled by default.',
                            ],
                            'required' => false,
                        ],
                        // Favorites
                        [
                            'name' => 'Watchlist Events',
                            'description' => [
                                'ro' => 'Sectiunea "Favorites" (doar editare, pliata). Lista evenimentelor pe care clientul le-a adaugat in watchlist. Afiseaza titlul si data fiecarui eveniment. Maximum 10 afisate, cu indicator pentru restul.',
                                'en' => '"Favorites" section (edit only, collapsed). List of events the customer has added to their watchlist. Displays the title and date of each event. Maximum 10 shown, with indicator for the rest.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Favorite Artists',
                            'description' => [
                                'ro' => 'Sectiunea "Favorites" (doar editare, pliata). Lista artistilor favoriti ai clientului, afisati ca etichete (badges). Maximum 20 afisati.',
                                'en' => '"Favorites" section (edit only, collapsed). List of the customer favorite artists, displayed as badges. Maximum 20 shown.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Favorite Venues',
                            'description' => [
                                'ro' => 'Sectiunea "Favorites" (doar editare, pliata). Lista locatiilor favorite ale clientului, afisate ca etichete cu numele si orasul. Maximum 20 afisate.',
                                'en' => '"Favorites" section (edit only, collapsed). List of the customer favorite venues, displayed as badges with name and city. Maximum 20 shown.',
                            ],
                            'required' => false,
                        ],
                        // Right sidebar
                        [
                            'name' => ['ro' => 'Previzualizare client', 'en' => 'Customer preview'],
                            'description' => [
                                'ro' => 'Coloana laterala dreapta (doar editare). Card cu initialele clientului, numele complet, emailul si badge-uri de status (Active/Suspended, Verified, Guest/Registered).',
                                'en' => 'Right sidebar column (edit only). Card with customer initials, full name, email and status badges (Active/Suspended, Verified, Guest/Registered).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Statistics',
                            'description' => [
                                'ro' => 'Coloana laterala dreapta (doar editare). Afiseaza 4 indicatori: Total Orders (numar comenzi), Total Spent (suma cheltuita in RON), Last Login (ultima logare) si Registered (data inregistrarii).',
                                'en' => 'Right sidebar column (edit only). Displays 4 indicators: Total Orders (order count), Total Spent (amount spent in RON), Last Login (last login time) and Registered (registration date).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Payment Methods',
                            'description' => [
                                'ro' => 'Coloana laterala dreapta (doar editare, pliabila). Afiseaza cardurile de plata salvate ale clientului, inclusiv brandul cardului, ultimele 4 cifre, data expirarii si statusul (Default/Expired).',
                                'en' => 'Right sidebar column (edit only, collapsible). Displays the customer saved payment cards, including card brand, last 4 digits, expiry date and status (Default/Expired).',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Emailul clientului nu poate fi modificat dupa creare. Daca este necesar un email nou, trebuie creat un cont nou de client.',
                                'en' => 'The customer email cannot be changed after creation. If a new email is needed, a new customer account must be created.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Meta Info" din coloana laterala dreapta (pliata) afiseaza informatii tehnice: data crearii, ultima actualizare, ultima logare si ID-ul clientului.',
                                'en' => 'The "Meta Info" section in the right sidebar (collapsed) displays technical information: creation date, last update, last login and customer ID.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Preferintele de notificari sunt setarile clientului, nu actiuni administrative. Modificarea lor va afecta ce notificari primeste clientul.',
                                'en' => 'Notification preferences are the customer settings, not administrative actions. Changing them will affect what notifications the customer receives.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 4: Customer List
                [
                    'id' => 'customer-list',
                    'title' => ['ro' => 'Lista de clienti - coloane si filtre', 'en' => 'Customer list - columns and filters'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina [Clienti] afiseaza toti clientii marketplace-ului intr-un tabel sortat implicit dupa data inregistrarii (cele mai recente primele). Badge-ul din meniul lateral arata numarul total de clienti.',
                                'en' => 'The [Clienti] page displays all marketplace customers in a table sorted by default by registration date (most recent first). The sidebar menu badge shows the total number of customers.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe orice rand din tabel pentru a deschide direct formularul de editare al clientului respectiv.',
                                'en' => 'Click on any row in the table to directly open the edit form for that customer.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Name',
                            'description' => [
                                'ro' => 'Numele complet al clientului (prenume + nume). Coloana este sortabila si cautabila (cauta dupa prenume sau nume).',
                                'en' => 'Customer full name (first name + last name). Column is sortable and searchable (searches by first name or last name).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email a clientului. Coloana este sortabila si cautabila.',
                                'en' => 'Customer email address. Column is sortable and searchable.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Phone',
                            'description' => [
                                'ro' => 'Numarul de telefon al clientului. Coloana este cautabila si poate fi ascunsa/afisata prin toggle.',
                                'en' => 'Customer phone number. Column is searchable and can be toggled on/off.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Verified',
                            'description' => [
                                'ro' => 'Iconita care indica daca emailul clientului a fost verificat. Bifa albastra = verificat, X rosu = neverificat.',
                                'en' => 'Icon indicating if the customer email has been verified. Blue checkmark = verified, red X = unverified.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Type',
                            'description' => [
                                'ro' => 'Iconita care indica tipul contului. Iconita gri = Guest (fara parola), iconita verde = Registered (cont complet). Tooltip-ul afiseaza detalii la hover.',
                                'en' => 'Icon indicating account type. Gray icon = Guest (no password), green icon = Registered (full account). Tooltip shows details on hover.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Badge colorat cu statusul contului: verde "active" = activ, rosu "suspended" = suspendat.',
                                'en' => 'Colored badge with account status: green "active" = active, red "suspended" = suspended.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Orders',
                            'description' => [
                                'ro' => 'Numarul total de comenzi plasate de client. Coloana este sortabila, aliniat central.',
                                'en' => 'Total number of orders placed by the customer. Column is sortable, center aligned.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Spent',
                            'description' => [
                                'ro' => 'Suma totala cheltuita de client in RON. Coloana este sortabila.',
                                'en' => 'Total amount spent by the customer in RON. Column is sortable.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Last Login',
                            'description' => [
                                'ro' => 'Data si ora ultimei logari a clientului. Coloana este sortabila si poate fi ascunsa/afisata prin toggle.',
                                'en' => 'Date and time of the customer last login. Column is sortable and can be toggled on/off.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Registered',
                            'description' => [
                                'ro' => 'Data si ora inregistrarii clientului. Coloana este sortabila, ascunsa implicit (poate fi afisata prin toggle).',
                                'en' => 'Date and time of customer registration. Column is sortable, hidden by default (can be shown via toggle).',
                            ],
                            'required' => false,
                        ],
                        // Filters
                        [
                            'name' => ['ro' => 'Filtru Status', 'en' => 'Status filter'],
                            'description' => [
                                'ro' => 'Filtreaza clientii dupa status: Active sau Suspended.',
                                'en' => 'Filter customers by status: Active or Suspended.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Email Verified', 'en' => 'Email Verified filter'],
                            'description' => [
                                'ro' => 'Filtreaza clientii dupa statusul verificarii emailului: Da (verificat) sau Nu (neverificat).',
                                'en' => 'Filter customers by email verification status: Yes (verified) or No (unverified).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Account Type', 'en' => 'Account Type filter'],
                            'description' => [
                                'ro' => 'Filtreaza clientii dupa tipul contului: Guest Only (doar oaspeti, fara parola) sau Registered Only (doar cu cont complet).',
                                'en' => 'Filter customers by account type: Guest Only (guests without password) or Registered Only (full accounts only).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Has Orders', 'en' => 'Has Orders filter'],
                            'description' => [
                                'ro' => 'Filtreaza clientii care au cel putin o comanda sau clientii fara nicio comanda.',
                                'en' => 'Filter customers who have at least one order or customers with no orders.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Marketing Consent', 'en' => 'Marketing Consent filter'],
                            'description' => [
                                'ro' => 'Filtreaza clientii care au acceptat sau nu comunicarile de marketing.',
                                'en' => 'Filter customers who have accepted or declined marketing communications.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Filtru Importat AmBilet', 'en' => 'Imported AmBilet filter'],
                            'description' => [
                                'ro' => 'Filtreaza clientii importati din platforma AmBilet ("Doar importati") sau clientii creati direct pe noua platforma ("Doar noi").',
                                'en' => 'Filter customers imported from the AmBilet platform ("Imported only") or customers created directly on the new platform ("New only").',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Poti combina mai multe filtre simultan pentru a gasi exact clientii doriti. De exemplu: Status "Active" + Has Orders "Da" + Marketing Consent "Da" pentru clienti activi cu comenzi care accepta marketing.',
                                'en' => 'You can combine multiple filters simultaneously to find exactly the customers you want. For example: Status "Active" + Has Orders "Yes" + Marketing Consent "Yes" for active customers with orders who accept marketing.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Coloanele Phone, Last Login si Registered pot fi afisate sau ascunse folosind butonul de toggle coloane din bara de instrumente a tabelului.',
                                'en' => 'The Phone, Last Login and Registered columns can be shown or hidden using the column toggle button in the table toolbar.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Customer Types
                [
                    'id' => 'customer-types',
                    'title' => ['ro' => 'Tipuri de clienti: Guest vs Registered', 'en' => 'Customer types: Guest vs Registered'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Platforma are doua tipuri de clienti, determinate automat de sistem pe baza existentei unei parole in cont.',
                                'en' => 'The platform has two types of customers, automatically determined by the system based on whether a password exists in the account.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Guest (Oaspete) - un client fara parola setata. Apare cu iconita gri in coloana "Type". Acesti clienti pot fi creati automat la plasarea unei comenzi fara cont, sau manual din admin. Nu se pot loga pe site.',
                                'en' => 'Guest - a customer without a password set. Appears with a gray icon in the "Type" column. These customers can be created automatically when placing an order without an account, or manually from admin. They cannot log in to the website.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Registered (Inregistrat) - un client cu parola setata si cont complet. Apare cu iconita verde in coloana "Type". Acesti clienti s-au inregistrat prin site-ul public si pot accesa zona de cont, istoric comenzi si preferinte.',
                                'en' => 'Registered - a customer with a password set and a full account. Appears with a green icon in the "Type" column. These customers registered through the public website and can access the account area, order history and preferences.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Un client Guest poate deveni Registered daca isi creeaza un cont pe site-ul public folosind acelasi email. Contul Guest existent va fi convertit automat in cont Registered, pastrand toate comenzile si datele anterioare.',
                                'en' => 'A Guest customer can become Registered if they create an account on the public website using the same email. The existing Guest account will be automatically converted to a Registered account, keeping all previous orders and data.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Tipul contului nu poate fi schimbat manual din admin. Conversia Guest -> Registered se face automat doar cand clientul isi creeaza un cont pe site.',
                                'en' => 'The account type cannot be changed manually from admin. The Guest -> Registered conversion happens automatically only when the customer creates an account on the website.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste filtrul "Account Type" din lista de clienti pentru a vedea rapid cati clienti sunt Guest si cati sunt Registered.',
                                'en' => 'Use the "Account Type" filter in the customer list to quickly see how many customers are Guest and how many are Registered.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 6: Customer Actions
                [
                    'id' => 'customer-actions',
                    'title' => ['ro' => 'Actiuni rapide pentru clienti', 'en' => 'Quick actions for customers'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In pagina de editare a unui client, in coloana laterala dreapta, sectiunea "Quick Actions" contine butoane pentru actiuni frecvente.',
                                'en' => 'On the customer edit page, in the right sidebar, the "Quick Actions" section contains buttons for common actions.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[View Orders] - Deschide pagina de comenzi filtrata pentru clientul respectiv. Poti vedea toate comenzile plasate de acest client.',
                                'en' => '[View Orders] - Opens the orders page filtered for this customer. You can see all orders placed by this customer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[View Tickets] - Deschide pagina de bilete filtrata pentru clientul respectiv. Poti vedea toate biletele cumparate de acest client.',
                                'en' => '[View Tickets] - Opens the tickets page filtered for this customer. You can see all tickets purchased by this customer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Send Email] - Deschide aplicatia de email implicita cu adresa clientului pre-completata. Util pentru comunicare directa rapida.',
                                'en' => '[Send Email] - Opens the default email application with the customer address pre-filled. Useful for quick direct communication.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Suspend User] - Suspenda contul clientului. Butonul apare doar daca clientul este activ. Necesita confirmare inainte de executie. Dupa suspendare, clientul nu mai poate accesa platforma.',
                                'en' => '[Suspend User] - Suspends the customer account. The button only appears if the customer is active. Requires confirmation before execution. After suspension, the customer can no longer access the platform.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Reactivate User] - Reactiveaza contul unui client suspendat. Butonul apare doar daca clientul este suspendat. Dupa reactivare, clientul poate accesa din nou platforma.',
                                'en' => '[Reactivate User] - Reactivates a suspended customer account. The button only appears if the customer is suspended. After reactivation, the customer can access the platform again.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Actiunea [Suspend User] necesita confirmare. Un dialog va aparea pentru a te asigura ca doresti intr-adevar sa suspendezi clientul.',
                                'en' => 'The [Suspend User] action requires confirmation. A dialog will appear to make sure you really want to suspend the customer.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Suspendarea unui client nu sterge comenzile sau biletele existente. Clientul pur si simplu nu mai poate accesa contul. Poti reactiva oricand contul.',
                                'en' => 'Suspending a customer does not delete existing orders or tickets. The customer simply can no longer access their account. You can reactivate the account at any time.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Butoanele [Suspend User] si [Reactivate User] nu apar niciodata simultan. Doar actiunea relevanta pentru statusul curent al clientului este vizibila.',
                                'en' => 'The [Suspend User] and [Reactivate User] buttons never appear simultaneously. Only the action relevant to the current customer status is visible.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
