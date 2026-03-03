<?php

namespace App\Filament\Marketplace\Pages\UserManual;

class CouponsManual extends BaseManualPage
{
    protected static ?string $slug = 'manual/coupons';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Cupoane', 'en' => 'Coupons'],
            'description' => [
                'ro' => 'Ghid complet pentru crearea si gestionarea codurilor de cupon si a campaniilor de reducere. Configureaza tipuri de discount, targetare pe evenimente si bilete, limite de utilizare si perioade de valabilitate.',
                'en' => 'Complete guide for creating and managing coupon codes and discount campaigns. Configure discount types, event and ticket targeting, usage limits and validity periods.',
            ],
            'icon' => 'heroicon-o-receipt-percent',
            'sections' => [
                // Section 1: Create a coupon code
                [
                    'id' => 'create-coupon',
                    'title' => ['ro' => 'Cum creezi un cod de cupon', 'en' => 'How to create a coupon code'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Cupoane] apoi pe [Coduri cupon].',
                                'en' => 'From the left sidebar menu, click on [Cupoane] then on [Coduri cupon].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In pagina cu lista codurilor de cupon, apasa butonul [Cod cupon nou] din coltul din dreapta sus.',
                                'en' => 'On the coupon codes list page, click the [Cod cupon nou] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide formularul de creare. Completeaza campul [Cod] cu codul dorit. Codul va fi convertit automat in litere mari (uppercase) si trebuie sa fie unic in intregul sistem.',
                                'en' => 'The creation form opens. Fill in the [Cod] field with the desired code. The code will be automatically converted to uppercase and must be unique across the entire system.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Optional, selecteaza o [Campanie] din lista existenta pentru a asocia cuponul unei campanii de marketing.',
                                'en' => 'Optionally, select a [Campanie] from the existing list to associate the coupon with a marketing campaign.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Selecteaza [Status]-ul cuponului: Activ, Inactiv, Epuizat sau Expirat. De obicei, la creare selectezi "Activ" pentru ca cuponul sa poata fi folosit imediat.',
                                'en' => 'Select the coupon [Status]: Active, Inactive, Exhausted or Expired. Usually, when creating you select "Active" so the coupon can be used immediately.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Configureaza tipul de discount, targetarea pe evenimente/bilete, limitele de utilizare si programul de valabilitate (sectiunile urmatoare explica fiecare in detaliu).',
                                'en' => 'Configure the discount type, event/ticket targeting, usage limits and validity schedule (the following sections explain each in detail).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Dupa completarea tuturor campurilor, apasa [Salveaza] pentru a crea cuponul.',
                                'en' => 'After filling in all the fields, click [Salveaza] to create the coupon.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Cod', 'en' => 'Code'],
                            'description' => [
                                'ro' => 'Codul unic al cuponului pe care clientii il vor introduce la checkout. Se converteste automat in litere mari. Trebuie sa fie unic in intregul sistem (nu doar per organizator).',
                                'en' => 'The unique coupon code that customers will enter at checkout. Automatically converted to uppercase. Must be unique across the entire system (not just per organizer).',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Campanie', 'en' => 'Campaign'],
                            'description' => [
                                'ro' => 'Campania de cupoane asociata (optional). Permite gruparea mai multor coduri de cupon sub aceeasi campanie de marketing.',
                                'en' => 'The associated coupon campaign (optional). Allows grouping multiple coupon codes under the same marketing campaign.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Starea curenta a cuponului. Valorile posibile sunt: Activ (poate fi folosit), Inactiv (dezactivat temporar), Epuizat (limita de utilizari atinsa), Expirat (data de expirare a trecut).',
                                'en' => 'The current status of the coupon. Possible values are: Active (can be used), Inactive (temporarily disabled), Exhausted (usage limit reached), Expired (expiration date has passed).',
                            ],
                            'required' => true,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Alege coduri de cupon scurte si usor de retinut (ex: VARA2025, VIP20, EARLYBIRD). Codul va fi afisat clientilor si trebuie sa fie usor de introdus la checkout.',
                                'en' => 'Choose short and easy to remember coupon codes (e.g. SUMMER2025, VIP20, EARLYBIRD). The code will be displayed to customers and must be easy to enter at checkout.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Codul trebuie sa fie unic global. Daca primesti o eroare de unicitate, inseamna ca un alt organizator a folosit deja acel cod. Incearca o varianta diferita.',
                                'en' => 'The code must be globally unique. If you receive a uniqueness error, it means another organizer has already used that code. Try a different variation.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 2: Discount types
                [
                    'id' => 'discount-types',
                    'title' => ['ro' => 'Tipuri de discount', 'en' => 'Understanding discount types'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In formularul de creare/editare a cuponului, sectiunea de discount contine campurile care definesc reducerea aplicata. Exista trei tipuri de discount disponibile:',
                                'en' => 'In the coupon creation/edit form, the discount section contains the fields that define the applied reduction. There are three discount types available:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Procentaj (percentage) — aplica o reducere procentuala din pretul total. De exemplu, 20% reducere din valoarea cosului.',
                                'en' => 'Percentage — applies a percentage reduction from the total price. For example, 20% off the cart value.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Suma fixa (fixed_amount) — scade o suma fixa din pretul total. De exemplu, 50 RON reducere din valoarea comenzii.',
                                'en' => 'Fixed amount — deducts a fixed amount from the total price. For example, 50 RON off the order value.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Transport gratuit (free_shipping) — elimina costul de livrare din comanda. Util pentru comenzile care includ bilete fizice sau merchandising.',
                                'en' => 'Free shipping — removes the delivery cost from the order. Useful for orders that include physical tickets or merchandise.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Campul [Valoare discount] este obligatoriu si reprezinta fie procentul (ex: 20 pentru 20%), fie suma fixa (ex: 50 pentru 50 RON), in functie de tipul selectat.',
                                'en' => 'The [Valoare discount] field is required and represents either the percentage (e.g. 20 for 20%), or the fixed amount (e.g. 50 for 50 RON), depending on the selected type.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Tip discount', 'en' => 'Discount type'],
                            'description' => [
                                'ro' => 'Tipul de reducere aplicat: procentaj (percentage), suma fixa (fixed_amount) sau transport gratuit (free_shipping). Determina modul in care se calculeaza reducerea.',
                                'en' => 'The type of discount applied: percentage, fixed amount (fixed_amount) or free shipping (free_shipping). Determines how the reduction is calculated.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Valoare discount', 'en' => 'Discount value'],
                            'description' => [
                                'ro' => 'Valoarea reducerii. Pentru procentaj, introdu numarul fara simbolul % (ex: 20 pentru 20%). Pentru suma fixa, introdu valoarea in moneda configurata (ex: 50 pentru 50 RON).',
                                'en' => 'The discount value. For percentage, enter the number without the % symbol (e.g. 20 for 20%). For fixed amount, enter the value in the configured currency (e.g. 50 for 50 RON).',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Reducere maxima', 'en' => 'Max discount amount'],
                            'description' => [
                                'ro' => 'Suma maxima de reducere care se poate aplica (optional). Util mai ales pentru cupoanele procentuale — de exemplu, 20% reducere dar maxim 100 RON. Lasa gol pentru fara limita.',
                                'en' => 'The maximum discount amount that can be applied (optional). Especially useful for percentage coupons — for example, 20% off but maximum 100 RON. Leave empty for no limit.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Suma minima achizitie', 'en' => 'Min purchase amount'],
                            'description' => [
                                'ro' => 'Suma minima a comenzii pentru ca cuponul sa fie valid (optional). De exemplu, reducerea se aplica doar daca comanda depaseste 200 RON. Lasa gol pentru fara restrictie.',
                                'en' => 'The minimum order amount for the coupon to be valid (optional). For example, the discount applies only if the order exceeds 200 RON. Leave empty for no restriction.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Pentru cupoanele procentuale, este recomandat sa setezi o reducere maxima (Max discount amount) pentru a evita reduceri disproportionate pe comenzile cu valori foarte mari.',
                                'en' => 'For percentage coupons, it is recommended to set a max discount amount to avoid disproportionate discounts on very large orders.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Suma minima de achizitie se verifica inainte de aplicarea reducerii. Daca comanda nu atinge minimul, cuponul nu va fi acceptat si clientul va vedea un mesaj de eroare.',
                                'en' => 'The minimum purchase amount is checked before applying the discount. If the order does not meet the minimum, the coupon will not be accepted and the customer will see an error message.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 3: Event and ticket targeting
                [
                    'id' => 'targeting',
                    'title' => ['ro' => 'Targetare pe evenimente si bilete', 'en' => 'Event and ticket targeting'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In sectiunea de targetare poti restrictiona cuponul sa functioneze doar pentru anumite evenimente sau tipuri de bilete. Daca nu selectezi nimic, cuponul se aplica pe toate evenimentele si biletele.',
                                'en' => 'In the targeting section you can restrict the coupon to work only for certain events or ticket types. If you do not select anything, the coupon applies to all events and tickets.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Mai intai, selecteaza un [Organizator] din lista. Aceasta filtreaza lista de evenimente disponibile pentru a afisa doar evenimentele organizatorului selectat.',
                                'en' => 'First, select an [Organizator] from the list. This filters the available events list to display only the events of the selected organizer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Din campul [Evenimente aplicabile] selecteaza unul sau mai multe evenimente pentru care cuponul va fi valid. Lista este filtrata automat in functie de organizatorul ales.',
                                'en' => 'From the [Evenimente aplicabile] field, select one or more events for which the coupon will be valid. The list is automatically filtered based on the chosen organizer.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Din campul [Tipuri de bilete aplicabile] selecteaza tipurile de bilete specifice (ex: VIP, General Access) pentru care cuponul functioneaza. Lista este filtrata in functie de evenimentele selectate.',
                                'en' => 'From the [Tipuri de bilete aplicabile] field, select the specific ticket types (e.g. VIP, General Access) for which the coupon works. The list is filtered based on the selected events.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Targetarea functioneaza cascadat: Organizator filtreaza Evenimente, iar Evenimente filtreaza Tipuri de bilete. Fiecare nivel restreange optiunile disponibile la nivelul urmator.',
                                'en' => 'Targeting works in a cascade: Organizer filters Events, and Events filter Ticket types. Each level narrows the options available at the next level.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Organizator', 'en' => 'Organizer'],
                            'description' => [
                                'ro' => 'Selecteaza organizatorul pentru a filtra lista de evenimente disponibile. Campul este folosit doar pentru filtrare si nu limiteaza direct cuponul.',
                                'en' => 'Select the organizer to filter the available events list. This field is used only for filtering and does not directly limit the coupon.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Evenimente aplicabile', 'en' => 'Applicable events'],
                            'description' => [
                                'ro' => 'Evenimentele pentru care cuponul este valid (multi-select). Daca nu selectezi niciun eveniment, cuponul se aplica pe toate evenimentele din sistem.',
                                'en' => 'The events for which the coupon is valid (multi-select). If you do not select any event, the coupon applies to all events in the system.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tipuri de bilete aplicabile', 'en' => 'Applicable ticket types'],
                            'description' => [
                                'ro' => 'Tipurile de bilete specifice pentru care cuponul functioneaza (multi-select). Daca nu selectezi niciun tip, cuponul se aplica pe toate tipurile de bilete ale evenimentelor selectate.',
                                'en' => 'The specific ticket types for which the coupon works (multi-select). If you do not select any type, the coupon applies to all ticket types of the selected events.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Daca lasi campurile de targetare goale, cuponul va fi universal — se va aplica pe orice eveniment si orice tip de bilet. Foloseste targetarea doar daca vrei sa limitezi cuponul la oferte specifice.',
                                'en' => 'If you leave the targeting fields empty, the coupon will be universal — it will apply to any event and any ticket type. Use targeting only if you want to limit the coupon to specific offers.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Selectia de tipuri de bilete depinde de evenimentele alese. Daca schimbi evenimentele, verifica si tipurile de bilete selectate — unele pot deveni invalide.',
                                'en' => 'The ticket type selection depends on the chosen events. If you change the events, also check the selected ticket types — some may become invalid.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 4: Usage limits and schedule
                [
                    'id' => 'usage-limits',
                    'title' => ['ro' => 'Limite de utilizare si program', 'en' => 'Setting usage limits and schedule'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Sectiunea de limite de utilizare iti permite sa controlezi de cate ori poate fi folosit un cupon si in ce perioada de timp.',
                                'en' => 'The usage limits section allows you to control how many times a coupon can be used and within what time period.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Campul [Utilizari maxime total] seteaza numarul total de ori cat poate fi folosit cuponul de toti clientii combinat. De exemplu, daca setezi 100, dupa 100 de utilizari cuponul devine automat "Epuizat".',
                                'en' => 'The [Utilizari maxime total] field sets the total number of times the coupon can be used by all customers combined. For example, if you set 100, after 100 uses the coupon automatically becomes "Exhausted".',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Campul [Utilizari maxime per utilizator] limiteaza cate ori poate folosi un singur client acelasi cupon. Valoarea implicita este 1, ceea ce inseamna ca fiecare client poate folosi cuponul o singura data.',
                                'en' => 'The [Utilizari maxime per utilizator] field limits how many times a single customer can use the same coupon. The default value is 1, meaning each customer can use the coupon only once.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Campul [Utilizari curente] este doar pentru citire (read-only) si afiseaza cate ori a fost folosit cuponul pana acum.',
                                'en' => 'The [Utilizari curente] field is read-only and displays how many times the coupon has been used so far.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea de program, seteaza [Data de inceput] si [Data de expirare] pentru a defini fereastra de valabilitate a cuponului. Cuponul va fi acceptat doar intre aceste doua date.',
                                'en' => 'In the schedule section, set the [Data de inceput] and [Data de expirare] to define the validity window of the coupon. The coupon will only be accepted between these two dates.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Utilizari maxime total', 'en' => 'Max uses total'],
                            'description' => [
                                'ro' => 'Numarul maxim de utilizari ale cuponului (global, toti clientii). Lasa gol pentru utilizari nelimitate. Cand limita este atinsa, statusul devine automat "Epuizat".',
                                'en' => 'The maximum number of coupon uses (global, all customers). Leave empty for unlimited uses. When the limit is reached, the status automatically becomes "Exhausted".',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Utilizari maxime per utilizator', 'en' => 'Max uses per user'],
                            'description' => [
                                'ro' => 'Cate ori poate un singur client sa foloseasca acest cupon. Valoarea implicita este 1. Seteaza o valoare mai mare daca vrei ca clientii sa poata folosi cuponul de mai multe ori.',
                                'en' => 'How many times a single customer can use this coupon. The default value is 1. Set a higher value if you want customers to be able to use the coupon multiple times.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Utilizari curente', 'en' => 'Current uses'],
                            'description' => [
                                'ro' => 'Numarul de utilizari efective ale cuponului pana in prezent. Camp doar pentru citire (read-only), actualizat automat de sistem la fiecare utilizare.',
                                'en' => 'The actual number of coupon uses to date. Read-only field, automatically updated by the system with each use.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Data de inceput', 'en' => 'Starts at'],
                            'description' => [
                                'ro' => 'Data si ora de la care cuponul devine valid (datetime). Inainte de aceasta data, cuponul nu va fi acceptat chiar daca are status "Activ".',
                                'en' => 'The date and time from which the coupon becomes valid (datetime). Before this date, the coupon will not be accepted even if its status is "Active".',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Data de expirare', 'en' => 'Expires at'],
                            'description' => [
                                'ro' => 'Data si ora la care cuponul expira (datetime). Dupa aceasta data, cuponul nu mai poate fi utilizat. Statusul se schimba automat la "Expirat".',
                                'en' => 'The date and time when the coupon expires (datetime). After this date, the coupon can no longer be used. The status automatically changes to "Expired".',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Daca setezi atat limita de utilizari cat si data de expirare, cuponul va fi dezactivat de prima conditie indeplinita — fie epuizarea utilizarilor, fie expirarea datei.',
                                'en' => 'If you set both a usage limit and an expiration date, the coupon will be deactivated by whichever condition is met first — either exhausting the uses or the date expiring.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Monitoreaza campul "Utilizari curente" din lista de cupoane pentru a vedea cat de aproape esti de limita maxima. Planifica din timp inlocuirea cupoanelor care se apropie de epuizare.',
                                'en' => 'Monitor the "Current uses" field in the coupons list to see how close you are to the maximum limit. Plan ahead to replace coupons that are approaching exhaustion.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Coupon options (public, first purchase, combinable)
                [
                    'id' => 'coupon-options',
                    'title' => ['ro' => 'Optiuni cupon: public, prima achizitie, combinabil', 'en' => 'Coupon options: public, first purchase, combinable'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In formularul de creare/editare exista trei toggle-uri (comutatoare) care controleaza comportamentul cuponului:',
                                'en' => 'In the creation/edit form there are three toggles that control the coupon behavior:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Public] — daca este activat, cuponul va fi vizibil si poate fi afisat pe site-ul public (de exemplu, intr-un banner promotional). Daca este dezactivat, cuponul functioneaza doar daca clientul cunoaste si introduce codul manual.',
                                'en' => '[Public] — if enabled, the coupon will be visible and can be displayed on the public website (for example, in a promotional banner). If disabled, the coupon works only if the customer knows and enters the code manually.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Doar prima achizitie] — daca este activat, cuponul poate fi folosit doar de clientii care nu au plasat nicio comanda anterioara pe platforma. Ideal pentru campanii de achizitie clienti noi.',
                                'en' => '[Doar prima achizitie] — if enabled, the coupon can only be used by customers who have not placed any previous orders on the platform. Ideal for new customer acquisition campaigns.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => '[Combinabil] — daca este activat, cuponul poate fi folosit impreuna cu alte cupoane in aceeasi comanda. Daca este dezactivat, doar un singur cupon poate fi aplicat per comanda.',
                                'en' => '[Combinabil] — if enabled, the coupon can be used together with other coupons in the same order. If disabled, only one coupon can be applied per order.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Public', 'en' => 'Is public'],
                            'description' => [
                                'ro' => 'Toggle care determina daca cuponul este vizibil public pe site. Cupoanele publice pot fi promovate in pagina evenimentului sau in sectiunea de oferte.',
                                'en' => 'Toggle that determines whether the coupon is publicly visible on the site. Public coupons can be promoted on the event page or in the offers section.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Doar prima achizitie', 'en' => 'First purchase only'],
                            'description' => [
                                'ro' => 'Toggle care limiteaza cuponul doar la clientii noi (fara comenzi anterioare). Sistemul verifica automat istoricul de comenzi al clientului la aplicarea cuponului.',
                                'en' => 'Toggle that limits the coupon to new customers only (no previous orders). The system automatically checks the customer order history when applying the coupon.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Combinabil', 'en' => 'Combinable'],
                            'description' => [
                                'ro' => 'Toggle care permite sau interzice folosirea cuponului impreuna cu alte cupoane in aceeasi comanda. Implicit este dezactivat (un singur cupon per comanda).',
                                'en' => 'Toggle that allows or prevents using the coupon together with other coupons in the same order. By default it is disabled (one coupon per order).',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Cupoanele marcate ca "Public" pot fi afisate automat pe pagina de checkout. Cupoanele private sunt distribuite manual prin email, social media sau parteneriate.',
                                'en' => 'Coupons marked as "Public" can be automatically displayed on the checkout page. Private coupons are distributed manually through email, social media or partnerships.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Fii atent cand activezi optiunea "Combinabil" — doua cupoane procentuale combinate pot genera reduceri foarte mari. Verifica intotdeauna impactul financiar inainte de a permite combinarea.',
                                'en' => 'Be careful when enabling the "Combinable" option — two percentage coupons combined can generate very large discounts. Always verify the financial impact before allowing combination.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 6: Coupon campaigns
                [
                    'id' => 'campaigns',
                    'title' => ['ro' => 'Campaniile de cupoane', 'en' => 'Coupon campaigns overview'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Campaniile de cupoane iti permit sa grupezi mai multe coduri de cupon sub aceeasi umbrela de marketing. Acceseaza [Cupoane] apoi [Campanii] din meniul lateral.',
                                'en' => 'Coupon campaigns allow you to group multiple coupon codes under the same marketing umbrella. Access [Cupoane] then [Campanii] from the sidebar menu.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Campanie noua] pentru a crea o campanie. Completeaza numele campaniei, descrierea si selecteaza status-ul (activa sau inactiva).',
                                'en' => 'Click [Campanie noua] to create a campaign. Fill in the campaign name, description and select the status (active or inactive).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Seteaza [Data de inceput] si [Data de sfarsit] pentru a defini perioada campaniei. Cuponurile asociate campaniei vor respecta aceste date.',
                                'en' => 'Set [Data de inceput] and [Data de sfarsit] to define the campaign period. Coupons associated with the campaign will respect these dates.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea de reguli, poti seta evenimentele si tipurile de bilete aplicabile la nivel de campanie. Aceste reguli se aplica tuturor cupoanelor din campanie.',
                                'en' => 'In the rules section, you can set the applicable events and ticket types at the campaign level. These rules apply to all coupons in the campaign.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea de mesaje, poti adauga un [Tagline campanie] (un slogan scurt afisat clientilor) si [Termeni si conditii] care se vor afisa pe pagina de checkout.',
                                'en' => 'In the messaging section, you can add a [Tagline campanie] (a short slogan displayed to customers) and [Termeni si conditii] that will be displayed on the checkout page.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Dupa salvarea campaniei, asociaza codurile de cupon individuale cu campania selectand-o din campul [Campanie] la crearea sau editarea fiecarui cupon.',
                                'en' => 'After saving the campaign, associate individual coupon codes with the campaign by selecting it from the [Campanie] field when creating or editing each coupon.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume campanie', 'en' => 'Campaign name'],
                            'description' => [
                                'ro' => 'Numele campaniei de marketing (ex: "Black Friday 2025", "Early Bird Festival"). Folosit pentru identificare interna si in rapoarte.',
                                'en' => 'The marketing campaign name (e.g. "Black Friday 2025", "Early Bird Festival"). Used for internal identification and in reports.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Descriere', 'en' => 'Description'],
                            'description' => [
                                'ro' => 'Descrierea detaliata a campaniei — scopul, publicul tinta si strategia de marketing. Vizibila doar in panoul de administrare.',
                                'en' => 'Detailed campaign description — purpose, target audience and marketing strategy. Visible only in the admin panel.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Starea campaniei — activa sau inactiva. Cand campania este inactiva, cupoanele asociate nu mai pot fi folosite chiar daca individual sunt active.',
                                'en' => 'The campaign status — active or inactive. When the campaign is inactive, associated coupons can no longer be used even if individually they are active.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Interval de date', 'en' => 'Date range'],
                            'description' => [
                                'ro' => 'Perioada de valabilitate a campaniei (data de inceput si data de sfarsit). Cuponurile din campanie nu vor fi acceptate in afara acestui interval.',
                                'en' => 'The campaign validity period (start date and end date). Coupons in the campaign will not be accepted outside this range.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tagline campanie', 'en' => 'Campaign tagline'],
                            'description' => [
                                'ro' => 'Un slogan scurt afisat clientilor pe site (ex: "Reduceri de pana la 50%!"). Folosit in bannere si pagini promotionale.',
                                'en' => 'A short slogan displayed to customers on the site (e.g. "Up to 50% off!"). Used in banners and promotional pages.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Termeni si conditii', 'en' => 'Terms & conditions'],
                            'description' => [
                                'ro' => 'Textul cu termenii si conditiile campaniei, afisat pe pagina de checkout. Include informatii despre restrictii, valabilitate si reguli de utilizare.',
                                'en' => 'The campaign terms and conditions text, displayed on the checkout page. Includes information about restrictions, validity and usage rules.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Campaniile sunt utile pentru organizarea cupoanelor pe perioade promotionale (Black Friday, sarbatori, lansari). Poti dezactiva intreaga campanie cu un singur click in loc sa dezactivezi fiecare cupon individual.',
                                'en' => 'Campaigns are useful for organizing coupons by promotional periods (Black Friday, holidays, launches). You can deactivate the entire campaign with a single click instead of deactivating each coupon individually.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Regulile de targetare setate la nivel de campanie se aplica tuturor cupoanelor asociate. Daca un cupon are si reguli proprii de targetare, se aplica restrictia cea mai stricta (intersectia).',
                                'en' => 'Targeting rules set at the campaign level apply to all associated coupons. If a coupon also has its own targeting rules, the strictest restriction applies (intersection).',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 7: List view, status management, bulk actions
                [
                    'id' => 'manage-coupons',
                    'title' => ['ro' => 'Gestionarea cupoanelor: lista, statusuri si actiuni in masa', 'en' => 'Managing coupons: list view, statuses and bulk actions'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Pagina principala [Coduri cupon] afiseaza un tabel cu toate cupoanele. Coloanele afisate sunt: Cod (copiabil), Campanie, Discount (tip si valoare), Status (badge colorat), Utilizare (curente/maxime) si Data de expirare.',
                                'en' => 'The main [Coduri cupon] page displays a table with all coupons. The displayed columns are: Code (copyable), Campaign, Discount (type and value), Status (colored badge), Usage (current/max) and Expiration date.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Codul cuponului este copiabil — apasa pe iconita de copiere de langa cod pentru a-l copia in clipboard. Util pentru a-l trimite rapid unui client sau partener.',
                                'en' => 'The coupon code is copyable — click the copy icon next to the code to copy it to clipboard. Useful for quickly sending it to a customer or partner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Coloana Discount afiseaza tipul si valoarea reducerii intr-un format compact (ex: "20%" sau "50 RON" sau "Transport gratuit").',
                                'en' => 'The Discount column displays the type and value of the reduction in a compact format (e.g. "20%" or "50 RON" or "Free shipping").',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Coloana Utilizare arata numarul de utilizari curente din totalul maxim (ex: "15/100"). Daca nu exista limita, se afiseaza doar numarul de utilizari curente.',
                                'en' => 'The Usage column shows the number of current uses out of the maximum total (e.g. "15/100"). If there is no limit, only the number of current uses is displayed.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a schimba rapid statusul unui cupon, foloseste actiunea [Activeaza] sau [Dezactiveaza] din meniul de actiuni al fiecarui rand (cele trei puncte din dreapta).',
                                'en' => 'To quickly change a coupon status, use the [Activeaza] or [Dezactiveaza] action from the actions menu of each row (the three dots on the right).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru actiuni in masa, selecteaza mai multe cupoane folosind checkbox-urile din stanga tabelului, apoi alege [Activeaza selectate] sau [Dezactiveaza selectate] din meniul de actiuni in masa.',
                                'en' => 'For bulk actions, select multiple coupons using the checkboxes on the left side of the table, then choose [Activeaza selectate] or [Dezactiveaza selectate] from the bulk actions menu.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti folosi campul de cautare pentru a gasi rapid un cupon dupa cod sau dupa numele campaniei. Filtrele iti permit sa afisezi doar cupoanele cu un anumit status.',
                                'en' => 'You can use the search field to quickly find a coupon by code or campaign name. Filters allow you to display only coupons with a specific status.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Actiunile in masa sunt ideale pentru gestionarea campaniilor mari — de exemplu, dezactivarea simultana a tuturor cupoanelor dupa incheierea unei promotii sau activarea unui lot de cupoane inainte de lansare.',
                                'en' => 'Bulk actions are ideal for managing large campaigns — for example, simultaneously deactivating all coupons after a promotion ends or activating a batch of coupons before launch.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Verifica periodic cupoanele cu status "Activ" si data de expirare apropiata. Desi sistemul schimba automat statusul la "Expirat", este bine sa monitorizezi campaniile active pentru a evita surprize.',
                                'en' => 'Periodically check coupons with "Active" status and approaching expiration date. Although the system automatically changes the status to "Expired", it is good to monitor active campaigns to avoid surprises.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
