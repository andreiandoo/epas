<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class DashboardManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-dashboard';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Dashboard', 'en' => 'Dashboard'],
            'description' => [
                'ro' => 'Ghid pentru intelegerea paginii principale si a indicatorilor de performanta (KPI-uri).',
                'en' => 'Guide for understanding the main page and key performance indicators (KPIs).',
            ],
            'icon' => 'heroicon-o-home',
            'sections' => [
                [
                    'id' => 'overview',
                    'title' => ['ro' => 'Prezentare generala', 'en' => 'Overview'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Dashboard-ul este prima pagina pe care o vezi cand te autentifici. Apasa pe [Dashboard] in meniul lateral pentru a reveni oricand la aceasta pagina.',
                                'en' => 'The Dashboard is the first page you see when you log in. Click [Dashboard] in the sidebar to return to this page at any time.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pagina contine: carduri cu statistici, grafice de vanzari si bilete, si un top al organizatorilor.',
                                'en' => 'The page contains: statistics cards, sales and ticket charts, and a top organizers ranking.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Datele din dashboard se actualizeaza in timp real. Toate cifrele reflecta starea curenta a platformei.',
                                'en' => 'Dashboard data updates in real time. All figures reflect the current state of the platform.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                [
                    'id' => 'stat-cards',
                    'title' => ['ro' => 'Carduri cu statistici (KPI-uri)', 'en' => 'Statistics cards (KPIs)'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In partea de sus a Dashboard-ului gasesti cardurile cu indicatorii principali:',
                                'en' => 'At the top of the Dashboard you will find the main indicator cards:',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Evenimente active', 'en' => 'Active Events'],
                            'description' => [
                                'ro' => 'Numarul evenimentelor care sunt in curs sau urmeaza sa aiba loc. Include doar evenimentele care nu sunt anulate sau expirate.',
                                'en' => 'Number of events that are ongoing or upcoming. Includes only events that are not cancelled or expired.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Total evenimente', 'en' => 'Total Events'],
                            'description' => [
                                'ro' => 'Numarul total al tuturor evenimentelor din platforma, indiferent de status.',
                                'en' => 'Total number of all events on the platform, regardless of status.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Venituri totale', 'en' => 'Total Revenue'],
                            'description' => [
                                'ro' => 'Suma totala a veniturilor din comenzi confirmate, afisata in moneda principala a marketplace-ului.',
                                'en' => 'Total revenue from confirmed orders, displayed in the marketplace\'s primary currency.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Bilete vandute', 'en' => 'Tickets Sold'],
                            'description' => [
                                'ro' => 'Numarul total de bilete vandute pe platforma.',
                                'en' => 'Total number of tickets sold on the platform.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Total organizatori', 'en' => 'Total Organizers'],
                            'description' => [
                                'ro' => 'Numarul organizatorilor inregistrati. Include si un indicator separat pentru organizatorii in asteptare (care necesita aprobare).',
                                'en' => 'Number of registered organizers. Also includes a separate indicator for pending organizers (requiring approval).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Total clienti', 'en' => 'Total Customers'],
                            'description' => [
                                'ro' => 'Numarul total al clientilor inregistrati pe platforma.',
                                'en' => 'Total number of customers registered on the platform.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Deconturi in asteptare', 'en' => 'Pending Payouts'],
                            'description' => [
                                'ro' => 'Numarul si valoarea totala a deconturilor catre organizatori care nu au fost inca procesate.',
                                'en' => 'Number and total value of payouts to organizers that have not yet been processed.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Daca vezi un numar mare de organizatori in asteptare, mergi la [Organizatori] pentru a-i aproba. Organizatorii in asteptare nu pot crea evenimente.',
                                'en' => 'If you see a large number of pending organizers, go to [Organizatori] to approve them. Pending organizers cannot create events.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                [
                    'id' => 'charts',
                    'title' => ['ro' => 'Grafice de vanzari si bilete', 'en' => 'Sales and ticket charts'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Sub cardurile de statistici gasesti doua grafice interactive:',
                                'en' => 'Below the statistics cards you will find two interactive charts:',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Graficul de vanzari (linie) — arata evolutia veniturilor pe zi in perioada selectata.',
                                'en' => 'Sales chart (line) — shows the revenue evolution per day in the selected period.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Graficul de bilete (bare) — arata numarul de bilete vandute pe zi.',
                                'en' => 'Tickets chart (bars) — shows the number of tickets sold per day.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Foloseste butoanele de perioada din coltul din dreapta sus al graficelor: [7 zile], [30 zile] sau [90 zile] pentru a schimba intervalul afisat.',
                                'en' => 'Use the period buttons in the top right corner of the charts: [7 zile], [30 zile] or [90 zile] to change the displayed interval.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Graficele includ doar comenzile confirmate. Comenzile anulate sau rambursate nu afecteaza cifrele afisate.',
                                'en' => 'Charts include only confirmed orders. Cancelled or refunded orders do not affect the displayed figures.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                [
                    'id' => 'top-organizers',
                    'title' => ['ro' => 'Top organizatori', 'en' => 'Top organizers'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In partea de jos a Dashboard-ului gasesti tabelul "Top Organizatori" cu primii 5 organizatori dupa venituri.',
                                'en' => 'At the bottom of the Dashboard you will find the "Top Organizers" table with the top 5 organizers by revenue.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Tabelul arata: numele organizatorului, numarul de evenimente si veniturile totale.',
                                'en' => 'The table shows: organizer name, number of events and total revenue.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe un organizator pentru a vedea detaliile complete ale acestuia.',
                                'en' => 'Click on an organizer to see their full details.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Top-ul se calculeaza pe baza veniturilor totale din comenzi confirmate. Un organizator cu multe evenimente mici poate depasi unul cu un singur eveniment mare.',
                                'en' => 'The ranking is calculated based on total revenue from confirmed orders. An organizer with many small events can surpass one with a single large event.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
