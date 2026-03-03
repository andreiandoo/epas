<?php

namespace App\Filament\Marketplace\Pages\UserManual;

class ReportsManual extends BaseManualPage
{
    protected static ?string $slug = 'manual/reports';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Rapoarte', 'en' => 'Reports'],
            'description' => [
                'ro' => 'Ghid pentru intelegerea rapoartelor financiare, veniturilor si balantelor organizatorilor.',
                'en' => 'Guide for understanding financial reports, income and organizer balances.',
            ],
            'icon' => 'heroicon-o-chart-bar',
            'sections' => [
                [
                    'id' => 'income',
                    'title' => ['ro' => 'Venituri', 'en' => 'Income'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral, deschide grupul [Reports] si apasa pe [Venituri].',
                                'en' => 'From the left sidebar, open the [Reports] group and click on [Venituri].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pagina afiseaza un rezumat al veniturilor marketplace-ului, cu filtre pe perioada de timp.',
                                'en' => 'The page displays a summary of marketplace income, with time period filters.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti vedea veniturile totale, comisioanele incasate si sumele platite catre organizatori.',
                                'en' => 'You can see total revenue, collected commissions and amounts paid to organizers.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Veniturile reflecta doar comenzile confirmate. Comenzile anulate sau rambursate nu sunt incluse in calcul.',
                                'en' => 'Income reflects only confirmed orders. Cancelled or refunded orders are not included in the calculation.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                [
                    'id' => 'tax-reports',
                    'title' => ['ro' => 'Rapoarte fiscale', 'en' => 'Tax Reports'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din grupul [Reports], apasa pe [Tax Reports].',
                                'en' => 'From the [Reports] group, click on [Tax Reports].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Aici gasesti rapoartele fiscale generate pentru fiecare eveniment. Poti vizualiza si descarca rapoartele in format PDF.',
                                'en' => 'Here you find the tax reports generated for each event. You can view and download reports in PDF format.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Fiecare raport contine: numarul de bilete vandute, valoarea totala, TVA-ul colectat si comisioanele.',
                                'en' => 'Each report contains: number of tickets sold, total value, collected VAT and commissions.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Rapoartele fiscale sunt generate automat pe baza comenzilor confirmate. Verifica setarile de TVA in pagina de Setari inainte de a genera rapoarte.',
                                'en' => 'Tax reports are automatically generated based on confirmed orders. Check VAT settings in the Settings page before generating reports.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                [
                    'id' => 'organizer-balances',
                    'title' => ['ro' => 'Balante organizatori', 'en' => 'Organizer Balances'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din grupul [Organizers], apasa pe [Balante].',
                                'en' => 'From the [Organizers] group, click on [Balante].',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pagina afiseaza o lista cu toti organizatorii si balantele lor curente: venituri totale, suma disponibila, suma in asteptare si totalul platit.',
                                'en' => 'The page displays a list of all organizers and their current balances: total revenue, available amount, pending amount and total paid out.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe un organizator pentru a vedea detaliile balantei, inclusiv istoricul tranzactiilor si platilor (payouts).',
                                'en' => 'Click on an organizer to see balance details, including transaction and payout history.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Venituri totale', 'en' => 'Total Revenue'],
                            'description' => [
                                'ro' => 'Suma totala a comenzilor confirmate pentru acest organizator.',
                                'en' => 'Total amount of confirmed orders for this organizer.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Suma disponibila', 'en' => 'Available Balance'],
                            'description' => [
                                'ro' => 'Suma care poate fi platita organizatorului (dupa deducerea comisioanelor si a platilor anterioare).',
                                'en' => 'Amount that can be paid to the organizer (after deducting commissions and previous payouts).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Suma in asteptare', 'en' => 'Pending Balance'],
                            'description' => [
                                'ro' => 'Suma din comenzi recente care nu este inca disponibila pentru plata (perioada de retinere).',
                                'en' => 'Amount from recent orders not yet available for payout (holding period).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Total platit', 'en' => 'Total Paid Out'],
                            'description' => [
                                'ro' => 'Suma totala platita organizatorului pana acum prin deconturi.',
                                'en' => 'Total amount paid to the organizer so far through payouts.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Pentru a crea un decont (payout), mergi pe pagina organizatorului si foloseste actiunea [Create Payout] din sidebar.',
                                'en' => 'To create a payout, go to the organizer page and use the [Create Payout] action from the sidebar.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                [
                    'id' => 'payouts',
                    'title' => ['ro' => 'Deconturi (Payouts)', 'en' => 'Payouts'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din grupul [Organizers], apasa pe [Deconturi] pentru a vedea lista tuturor platilor catre organizatori.',
                                'en' => 'From the [Organizers] group, click on [Deconturi] to see the list of all payments to organizers.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Fiecare decont arata: organizatorul, suma, data crearii, statusul (in asteptare, procesat, anulat) si detaliile bancare.',
                                'en' => 'Each payout shows: organizer, amount, creation date, status (pending, processed, cancelled) and bank details.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe un decont pentru a vedea detaliile complete, inclusiv comenzile si biletele incluse in calcul.',
                                'en' => 'Click on a payout to see full details, including orders and tickets included in the calculation.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Deconturile se creeaza din pagina organizatorului, nu din lista de deconturi. Lista de deconturi este doar pentru vizualizare si verificare.',
                                'en' => 'Payouts are created from the organizer page, not from the payouts list. The payouts list is for viewing and verification only.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                [
                    'id' => 'documents',
                    'title' => ['ro' => 'Documente organizatori', 'en' => 'Organizer Documents'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din grupul [Reports], apasa pe [Documente] pentru a vedea documentele incarcate de organizatori.',
                                'en' => 'From the [Reports] group, click on [Documente] to see documents uploaded by organizers.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Aici gasesti copiile actelor de identitate, certificatelor de inregistrare si altor documente legale necesare pentru verificarea organizatorilor.',
                                'en' => 'Here you find copies of ID documents, registration certificates and other legal documents needed for organizer verification.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Documentele sunt confidentiale. Asigura-te ca doar utilizatorii autorizati au acces la aceasta sectiune.',
                                'en' => 'Documents are confidential. Make sure only authorized users have access to this section.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],
            ],
        ];
    }
}
