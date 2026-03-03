<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class OrganizersManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-organizers';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Organizatori', 'en' => 'Organizers'],
            'description' => [
                'ro' => 'Ghid complet pentru crearea, gestionarea si administrarea organizatorilor pe platforma: conturi, comisioane, documente, plati.',
                'en' => 'Complete guide for creating, managing and administering organizers on the platform: accounts, commissions, documents, payouts.',
            ],
            'icon' => 'heroicon-o-briefcase',
            'sections' => [
                // Section 1: Create Organizer
                [
                    'id' => 'create-organizer',
                    'title' => ['ro' => 'Cum creezi un organizator', 'en' => 'How to create an organizer'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Organizatori].',
                                'en' => 'From the left sidebar menu, click on [Organizatori].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In pagina "Organizatori" apasa butonul [Creare organizator] din coltul din dreapta sus.',
                                'en' => 'On the "Organizatori" page, click the [Creare organizator] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de creare. Formularul are doua coloane: cea principala (3/4 din pagina) cu datele organizatorului si o bara laterala (1/4) cu setari de status si comision (disponibila doar la editare).',
                                'en' => 'The creation form opens. The form has two columns: the main one (3/4 of the page) with organizer data and a sidebar (1/4) with status and commission settings (available only when editing).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza campurile obligatorii: Nume, Email si Parola. Apoi adauga informatiile suplimentare despre tipul organizatorului, companie si documente.',
                                'en' => 'Fill in the required fields: Name, Email and Password. Then add additional information about the organizer type, company and documents.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa butonul [Salveaza] din partea de jos a paginii pentru a crea organizatorul.',
                                'en' => 'Click the [Salveaza] button at the bottom of the page to create the organizer.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume', 'en' => 'Name'],
                            'description' => [
                                'ro' => 'Numele de afisare al organizatorului pe platforma. Acesta va fi vizibil public pe paginile evenimentelor.',
                                'en' => 'The organizer display name on the platform. This will be publicly visible on event pages.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email a organizatorului, unica per marketplace. Se foloseste pentru autentificare si comunicare. Nu poate fi duplicata.',
                                'en' => 'The organizer email address, unique per marketplace. Used for authentication and communication. Cannot be duplicated.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Parola', 'en' => 'Password'],
                            'description' => [
                                'ro' => 'Parola contului organizatorului. Obligatorie la creare, optionala la editare. Parola este stocata criptat (hashed).',
                                'en' => 'The organizer account password. Required on creation, optional when editing. The password is stored encrypted (hashed).',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Persoana de contact', 'en' => 'Contact name'],
                            'description' => [
                                'ro' => 'Numele persoanei de contact din organizatie. Vizibil doar in panoul de administrare.',
                                'en' => 'The contact person name from the organization. Visible only in the admin panel.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Telefon', 'en' => 'Phone'],
                            'description' => [
                                'ro' => 'Numarul de telefon al organizatorului.',
                                'en' => 'The organizer phone number.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Descriere', 'en' => 'Description'],
                            'description' => [
                                'ro' => 'O descriere scurta a organizatorului care poate fi afisata public pe site.',
                                'en' => 'A short description of the organizer that can be displayed publicly on the site.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Website',
                            'description' => [
                                'ro' => 'Adresa URL a site-ului organizatorului.',
                                'en' => 'The organizer website URL.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Dupa creare, organizatorul va avea statusul "In asteptare" (pending). Trebuie aprobat manual inainte de a putea crea evenimente.',
                                'en' => 'After creation, the organizer will have "Pending" status. It must be manually approved before it can create events.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Email-ul trebuie sa fie unic in cadrul marketplace-ului. Daca adresa exista deja, vei primi o eroare de validare.',
                                'en' => 'The email must be unique within the marketplace. If the address already exists, you will receive a validation error.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 2: Organizer Types
                [
                    'id' => 'organizer-types',
                    'title' => ['ro' => 'Tipuri de organizator si moduri de lucru', 'en' => 'Organizer types and work modes'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Tip organizator" din formular, gasesti trei campuri care definesc profilul organizatorului:',
                                'en' => 'In the "Organizer type" section of the form, you will find three fields that define the organizer profile:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Tip persoana', 'en' => 'Person type'],
                            'description' => [
                                'ro' => 'Selecteaza daca organizatorul este Persoana Juridica (PJ) sau Persoana Fizica (PF). Aceasta alegere determina ce campuri suplimentare apar in formular: pentru PJ se afiseaza sectiunea de date firma, pentru PF sectiunea de date personale.',
                                'en' => 'Select whether the organizer is a Legal Entity (PJ) or Individual (PF). This choice determines which additional fields appear in the form: for PJ the company data section is shown, for PF the personal data section.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Mod de lucru', 'en' => 'Work mode'],
                            'description' => [
                                'ro' => 'Defineste relatia comerciala cu organizatorul: "Exclusiv" inseamna ca biletele se vand doar pe aceasta platforma; "Non-exclusiv" inseamna ca organizatorul vinde bilete si pe alte platforme.',
                                'en' => 'Defines the commercial relationship with the organizer: "Exclusive" means tickets are sold only on this platform; "Non-exclusive" means the organizer also sells tickets on other platforms.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tip organizator', 'en' => 'Organizer type'],
                            'description' => [
                                'ro' => 'Categoria organizatorului: Agentie, Promoter, Locatie, Artist, ONG sau Altele. Aceasta clasificare ajuta la filtrarea si raportarea organizatorilor.',
                                'en' => 'The organizer category: Agency, Promoter, Venue, Artist, NGO or Other. This classification helps with filtering and reporting organizers.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Tipul de persoana (PJ/PF) afecteaza direct sectiunile vizibile din formular. Pentru PJ vei vedea campuri de firma (CUI, Reg. Com., etc.), iar pentru PF vei vedea campuri de date personale (CNP, act de identitate).',
                                'en' => 'The person type (PJ/PF) directly affects which form sections are visible. For PJ you will see company fields (CUI, Reg. Com., etc.), and for PF you will see personal data fields (CNP, ID document).',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 3: Company Information
                [
                    'id' => 'company-info',
                    'title' => ['ro' => 'Informatii firma si documente', 'en' => 'Company information and documents'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Informatii firma" apare doar daca tipul de persoana este setat pe PJ (Persoana Juridica). Completeaza datele firmei conform certificatului de inregistrare.',
                                'en' => 'The "Company information" section appears only if the person type is set to PJ (Legal Entity). Fill in the company data according to the registration certificate.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Reprezentant" permite adaugarea numelui si prenumelui reprezentantului legal al firmei.',
                                'en' => 'The "Representative" section allows adding the first and last name of the company legal representative.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Garant / Date personale" contine informatii despre garantul contractului: nume, prenume, CNP (13 cifre), adresa, oras si informatii document de identitate.',
                                'en' => 'The "Guarantor / Personal details" section contains information about the contract guarantor: first name, last name, CNP (13 digits), address, city and ID document information.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Documente incarcate" poti urca copii ale documentelor necesare.',
                                'en' => 'In the "Uploaded documents" section you can upload copies of the required documents.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume firma', 'en' => 'Company name'],
                            'description' => [
                                'ro' => 'Denumirea oficiala a firmei conform certificatului de inregistrare.',
                                'en' => 'The official company name according to the registration certificate.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'CUI / Tax ID',
                            'description' => [
                                'ro' => 'Codul Unic de Identificare al firmei (CUI) sau echivalentul fiscal international.',
                                'en' => 'The company Unique Identification Code (CUI) or international tax equivalent.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Registrul Comertului', 'en' => 'Trade Register'],
                            'description' => [
                                'ro' => 'Numarul de inregistrare la Registrul Comertului (ex: J40/1234/2020).',
                                'en' => 'The Trade Register registration number (e.g. J40/1234/2020).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Platitor TVA', 'en' => 'VAT payer'],
                            'description' => [
                                'ro' => 'Comutator (toggle) care indica daca firma este platitoare de TVA.',
                                'en' => 'Toggle that indicates whether the company is a VAT payer.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Adresa firma', 'en' => 'Company address'],
                            'description' => [
                                'ro' => 'Adresa sediului social al firmei.',
                                'en' => 'The company registered office address.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Oras, Judet, Cod postal', 'en' => 'City, County, Zip code'],
                            'description' => [
                                'ro' => 'Localizarea sediului social: oras, judet si cod postal.',
                                'en' => 'Registered office location: city, county and zip code.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Copie act de identitate', 'en' => 'ID card copy'],
                            'description' => [
                                'ro' => 'Incarca o copie a actului de identitate al reprezentantului legal. Formate acceptate: imagine (JPEG, PNG) sau PDF. Dimensiune maxima: 5 MB.',
                                'en' => 'Upload a copy of the legal representative ID card. Accepted formats: image (JPEG, PNG) or PDF. Maximum size: 5 MB.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Copie CUI / Certificat de inregistrare', 'en' => 'CUI copy / Registration certificate'],
                            'description' => [
                                'ro' => 'Incarca o copie a certificatului de inregistrare al firmei sau a CUI-ului. Formate acceptate: imagine (JPEG, PNG) sau PDF. Dimensiune maxima: 5 MB.',
                                'en' => 'Upload a copy of the company registration certificate or CUI. Accepted formats: image (JPEG, PNG) or PDF. Maximum size: 5 MB.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'CNP-ul (Cod Numeric Personal) trebuie sa contina exact 13 cifre. Sistemul valideaza lungimea automata.',
                                'en' => 'The CNP (Personal Numeric Code) must contain exactly 13 digits. The system validates the length automatically.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Documentele incarcate sunt vizibile doar in panoul de administrare si sunt necesare pentru verificarea organizatorului.',
                                'en' => 'Uploaded documents are visible only in the admin panel and are required for organizer verification.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 4: Bank Accounts
                [
                    'id' => 'bank-accounts',
                    'title' => ['ro' => 'Gestionarea conturilor bancare', 'en' => 'Managing bank accounts'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Conturi bancare" este disponibila doar la editarea unui organizator existent (nu la creare).',
                                'en' => 'The "Bank accounts" section is available only when editing an existing organizer (not when creating).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa butonul [+ Adauga cont bancar] pentru a adauga un cont nou. Poti adauga maximum 5 conturi bancare per organizator.',
                                'en' => 'Click the [+ Adauga cont bancar] button to add a new account. You can add a maximum of 5 bank accounts per organizer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza datele contului: numele bancii, IBAN-ul, titularul contului si daca este cont principal.',
                                'en' => 'Fill in the account details: bank name, IBAN, account holder and whether it is the primary account.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a sterge un cont, apasa iconita de stergere (cos de gunoi) din dreptul contului respectiv.',
                                'en' => 'To delete an account, click the delete icon (trash) next to that account.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Numele bancii', 'en' => 'Bank name'],
                            'description' => [
                                'ro' => 'Denumirea bancii la care este deschis contul (ex: Banca Transilvania, ING, BRD).',
                                'en' => 'The name of the bank where the account is opened (e.g. Banca Transilvania, ING, BRD).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'IBAN',
                            'description' => [
                                'ro' => 'Codul IBAN al contului bancar. Acesta este folosit pentru transferurile de plati catre organizator.',
                                'en' => 'The bank account IBAN code. This is used for payment transfers to the organizer.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Titular cont', 'en' => 'Account holder'],
                            'description' => [
                                'ro' => 'Numele titularului contului bancar, asa cum apare in documentele bancare.',
                                'en' => 'The bank account holder name, as it appears in banking documents.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Cont principal', 'en' => 'Primary account'],
                            'description' => [
                                'ro' => 'Comutator (toggle) care marcheaza contul ca fiind cel principal. Platile vor fi directionate implicit catre contul principal.',
                                'en' => 'Toggle that marks the account as primary. Payments will be directed by default to the primary account.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Se recomanda sa ai cel putin un cont bancar marcat ca "principal" pentru a asigura procesarea corecta a platilor.',
                                'en' => 'It is recommended to have at least one bank account marked as "primary" to ensure correct payment processing.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti adauga maximum 5 conturi bancare per organizator. Daca ai nevoie de mai multe, sterge un cont neutilizat inainte de a adauga altul.',
                                'en' => 'You can add a maximum of 5 bank accounts per organizer. If you need more, delete an unused account before adding another.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 5: Commission and Financial Settings
                [
                    'id' => 'commission',
                    'title' => ['ro' => 'Comisioane si setari financiare', 'en' => 'Commissions and financial settings'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Setarile financiare se gasesc in bara laterala din dreapta (coloana 1/4), disponibila doar la editarea unui organizator existent.',
                                'en' => 'Financial settings are found in the right sidebar (1/4 column), available only when editing an existing organizer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Seteaza rata comisionului procentual si/sau comisionul fix pentru fiecare tranzactie. Aceste valori determina cat retine platforma din fiecare vanzare de bilet.',
                                'en' => 'Set the percentage commission rate and/or the fixed commission per transaction. These values determine how much the platform retains from each ticket sale.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Sectiunea "Sumar financiar" afiseaza o privire de ansamblu a situatiei financiare a organizatorului.',
                                'en' => 'The "Financial summary" section displays an overview of the organizer financial situation.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Rata comision (%)', 'en' => 'Commission rate (%)'],
                            'description' => [
                                'ro' => 'Procentul retinut de platforma din fiecare vanzare de bilet. Valori acceptate: intre 0% si 50%. Se aplica peste pretul biletului.',
                                'en' => 'The percentage retained by the platform from each ticket sale. Accepted values: between 0% and 50%. Applied on top of the ticket price.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Comision fix', 'en' => 'Fixed commission'],
                            'description' => [
                                'ro' => 'O suma fixa (in moneda platformei) retinuta de platforma per tranzactie, in plus fata de comisionul procentual.',
                                'en' => 'A fixed amount (in platform currency) retained by the platform per transaction, in addition to the percentage commission.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Verificat la', 'en' => 'Verified at'],
                            'description' => [
                                'ro' => 'Data si ora la care organizatorul a fost verificat. Se completeaza automat cand se apasa actiunea de verificare.',
                                'en' => 'The date and time when the organizer was verified. Auto-filled when the verify action is triggered.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Venituri totale', 'en' => 'Total revenue'],
                            'description' => [
                                'ro' => 'Suma totala a veniturilor generate de organizator din vanzarea biletelor. Camp informativ, nu se poate edita.',
                                'en' => 'Total revenue generated by the organizer from ticket sales. Informational field, cannot be edited.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Sold disponibil', 'en' => 'Available balance'],
                            'description' => [
                                'ro' => 'Suma disponibila pentru plata catre organizator (dupa deducerea comisioanelor si a platilor deja efectuate).',
                                'en' => 'Amount available for payout to the organizer (after deducting commissions and payments already made).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Sold in asteptare', 'en' => 'Pending balance'],
                            'description' => [
                                'ro' => 'Suma aflata in procesare sau in asteptarea confirmarii (ex: tranzactii recente care nu au fost inca finalizate).',
                                'en' => 'Amount being processed or awaiting confirmation (e.g. recent transactions not yet finalized).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Total platit', 'en' => 'Total paid out'],
                            'description' => [
                                'ro' => 'Suma totala platita organizatorului pana in prezent.',
                                'en' => 'Total amount paid out to the organizer to date.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Comisionul procentual si cel fix se pot cumula. De exemplu: 5% + 2 RON per bilet. Daca ambele sunt setate pe 0, platforma nu retine nimic din vanzari.',
                                'en' => 'The percentage and fixed commissions can be combined. For example: 5% + 2 RON per ticket. If both are set to 0, the platform retains nothing from sales.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 6: Organizer Statuses and Actions
                [
                    'id' => 'status-actions',
                    'title' => ['ro' => 'Status-uri si actiuni disponibile', 'en' => 'Statuses and available actions'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Fiecare organizator are un status care determina ce poate face pe platforma. Statusul se gestioneaza din bara laterala dreapta (la editare) sau prin actiunile din tabel.',
                                'en' => 'Each organizer has a status that determines what it can do on the platform. The status is managed from the right sidebar (when editing) or through table actions.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Exista 3 statusuri posibile: In asteptare (pending), Activ (active) si Suspendat (suspended). Fiecare status are actiuni specifice disponibile.',
                                'en' => 'There are 3 possible statuses: Pending, Active and Suspended. Each status has specific available actions.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'In asteptare (Pending)', 'en' => 'Pending'],
                            'description' => [
                                'ro' => 'Statusul initial al unui organizator nou creat. Organizatorul nu poate crea evenimente. Actiune disponibila: [Aproba] - trece organizatorul in status Activ.',
                                'en' => 'The initial status of a newly created organizer. The organizer cannot create events. Available action: [Aproba] - moves the organizer to Active status.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Activ (Active)', 'en' => 'Active'],
                            'description' => [
                                'ro' => 'Organizatorul este aprobat si poate crea si gestiona evenimente. Actiuni disponibile: [Verifica] - marcheaza organizatorul ca verificat (daca nu a fost deja verificat); [Suspenda] - dezactiveaza temporar contul organizatorului.',
                                'en' => 'The organizer is approved and can create and manage events. Available actions: [Verifica] - marks the organizer as verified (if not already verified); [Suspenda] - temporarily deactivates the organizer account.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Suspendat (Suspended)', 'en' => 'Suspended'],
                            'description' => [
                                'ro' => 'Organizatorul este dezactivat temporar si nu poate crea sau gestiona evenimente. Actiune disponibila: [Reactiveaza] - readuce organizatorul in status Activ.',
                                'en' => 'The organizer is temporarily deactivated and cannot create or manage events. Available action: [Reactiveaza] - returns the organizer to Active status.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Verificarea este separata de aprobare. Un organizator poate fi Activ dar neverificat. Verificarea confirma ca documentele si datele au fost validate manual.',
                                'en' => 'Verification is separate from approval. An organizer can be Active but unverified. Verification confirms that documents and data have been manually validated.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Suspendarea unui organizator nu sterge evenimentele sau datele existente. Organizatorul isi pastreaza toate datele si poate fi reactivat oricand.',
                                'en' => 'Suspending an organizer does not delete existing events or data. The organizer keeps all data and can be reactivated at any time.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Din pagina de editare, sectiunea "Actiuni rapide" ofera butoane directe: [Vezi evenimente], [Creeaza eveniment], [Vezi contract], [Vezi balanta], [Creeaza plata], [Suspenda] sau [Reactiveaza].',
                                'en' => 'From the edit page, the "Quick actions" section provides direct buttons: [Vezi evenimente], [Creeaza eveniment], [Vezi contract], [Vezi balanta], [Creeaza plata], [Suspenda] or [Reactiveaza].',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 7: Organizer List
                [
                    'id' => 'organizer-list',
                    'title' => ['ro' => 'Lista de organizatori - cum o folosesti', 'en' => 'Organizer list - how to use it'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina [Organizatori] afiseaza toti organizatorii intr-un tabel cu urmatoarele coloane:',
                                'en' => 'The [Organizatori] page displays all organizers in a table with the following columns:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste campul de cautare din partea de sus pentru a gasi organizatori dupa nume sau email.',
                                'en' => 'Use the search field at the top to find organizers by name or email.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste filtrele disponibile pentru a restringe lista la anumite criterii.',
                                'en' => 'Use the available filters to narrow the list to specific criteria.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Logo',
                            'description' => [
                                'ro' => 'Miniatura logo-ului sau avatarului organizatorului.',
                                'en' => 'Thumbnail of the organizer logo or avatar.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Nume', 'en' => 'Name'],
                            'description' => [
                                'ro' => 'Numele de afisare al organizatorului. Poti sorta coloana alfabetic.',
                                'en' => 'The organizer display name. You can sort the column alphabetically.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Email',
                            'description' => [
                                'ro' => 'Adresa de email a organizatorului.',
                                'en' => 'The organizer email address.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Eticheta colorata cu statusul organizatorului: In asteptare (galben), Activ (verde), Suspendat (rosu).',
                                'en' => 'Colored badge with organizer status: Pending (yellow), Active (green), Suspended (red).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Verificat', 'en' => 'Verified'],
                            'description' => [
                                'ro' => 'Indica daca organizatorul a fost verificat manual (bifa verde) sau nu.',
                                'en' => 'Indicates whether the organizer has been manually verified (green check) or not.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Total evenimente', 'en' => 'Total events'],
                            'description' => [
                                'ro' => 'Numarul total de evenimente create de acest organizator.',
                                'en' => 'The total number of events created by this organizer.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Venituri totale', 'en' => 'Total revenue'],
                            'description' => [
                                'ro' => 'Suma totala a veniturilor generate din vanzarea biletelor.',
                                'en' => 'Total revenue generated from ticket sales.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Sold disponibil', 'en' => 'Available balance'],
                            'description' => [
                                'ro' => 'Suma disponibila pentru plata catre organizator.',
                                'en' => 'Amount available for payout to the organizer.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Filtre disponibile: Status (In asteptare / Activ / Suspendat) si Verificat (Da / Nu). Combina filtrele pentru a gasi rapid organizatorii care necesita actiuni (ex: activi dar neverificati).',
                                'en' => 'Available filters: Status (Pending / Active / Suspended) and Verified (Yes / No). Combine filters to quickly find organizers that need actions (e.g. active but unverified).',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 8: Payouts
                [
                    'id' => 'payouts',
                    'title' => ['ro' => 'Plati catre organizatori (Payouts)', 'en' => 'Organizer payouts'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Platile catre organizatori se gestioneaza pe baza soldului disponibil afisat in profilul fiecarui organizator.',
                                'en' => 'Organizer payouts are managed based on the available balance displayed in each organizer profile.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Din pagina de editare a organizatorului, in sectiunea "Actiuni rapide", apasa butonul [Creeaza plata] pentru a initia un payout.',
                                'en' => 'From the organizer edit page, in the "Quick actions" section, click the [Creeaza plata] button to initiate a payout.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Butonul [Vezi balanta] iti arata un sumar detaliat al veniturilor, comisioanelor retinute, platilor efectuate si soldului ramas.',
                                'en' => 'The [Vezi balanta] button shows you a detailed summary of revenue, retained commissions, completed payments and remaining balance.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Platile se efectueaza catre contul bancar marcat ca "principal". Asigura-te ca organizatorul are cel putin un cont bancar configurat inainte de a crea o plata.',
                                'en' => 'Payments are made to the bank account marked as "primary". Make sure the organizer has at least one bank account configured before creating a payout.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Platile si rapoartele financiare detaliate sunt documentate in sectiunea de Rapoarte din manual. Consulta acel capitol pentru informatii complete despre procesarea platilor.',
                                'en' => 'Detailed payouts and financial reports are documented in the Reports section of the manual. Refer to that chapter for complete information about payment processing.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Inainte de a efectua o plata, verifica ca organizatorul are contul bancar actualizat si ca soldul disponibil este corect. Platile procesate nu pot fi anulate automat.',
                                'en' => 'Before making a payout, verify that the organizer has an up-to-date bank account and that the available balance is correct. Processed payments cannot be automatically reversed.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 9: Ticket Terms
                [
                    'id' => 'ticket-terms',
                    'title' => ['ro' => 'Termeni bilete si setari suplimentare', 'en' => 'Ticket terms and additional settings'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Termeni bilete" din formular, gasesti campuri pentru termenii si conditiile standard si functionalitati suplimentare ale organizatorului.',
                                'en' => 'In the "Ticket terms" section of the form, you will find fields for standard terms and conditions and additional organizer features.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Termeni si conditii standard', 'en' => 'Standard terms and conditions'],
                            'description' => [
                                'ro' => 'Editor text complet (RichEditor) pentru introducerea termenilor si conditiilor standard care se aplica biletelor emise de acest organizator. Acest text poate fi afisat pe bilete sau pe pagina de cumparare.',
                                'en' => 'Full text editor (RichEditor) for entering the standard terms and conditions that apply to tickets issued by this organizer. This text can be displayed on tickets or on the purchase page.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Gamificare', 'en' => 'Gamification'],
                            'description' => [
                                'ro' => 'Comutator (toggle) care activeaza functionalitatea de gamificare pentru organizator. Permite crearea de provocari, puncte si recompense pentru clienti.',
                                'en' => 'Toggle that enables gamification functionality for the organizer. Allows creating challenges, points and rewards for customers.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Invitatii', 'en' => 'Invitations'],
                            'description' => [
                                'ro' => 'Comutator (toggle) care activeaza sistemul de invitatii. Permite organizatorului sa trimita invitatii personalizate pentru evenimente.',
                                'en' => 'Toggle that enables the invitation system. Allows the organizer to send personalized invitations for events.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Termenii si conditiile standard se aplica tuturor evenimentelor organizatorului, cu exceptia cazului in care un eveniment are termeni specifici definiti separat.',
                                'en' => 'Standard terms and conditions apply to all organizer events, unless an event has specific terms defined separately.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
