<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CommunicationsManual extends Page
{
    use BaseManualPage;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.user-manual.module';

    #[Url(as: 'lang')]
    public string $locale = 'ro';

    protected static ?string $slug = 'manual-communications';

    protected function getManualContent(): array
    {
        return [
            'title' => ['ro' => 'Comunicare', 'en' => 'Communications'],
            'description' => [
                'ro' => 'Ghid complet pentru gestionarea email template-urilor, newsletterelor si listelor de contact. Afla cum sa configurezi mesajele automate, sa trimiti campanii de email si sa organizezi destinatarii.',
                'en' => 'Complete guide for managing email templates, newsletters and contact lists. Learn how to configure automated messages, send email campaigns and organize recipients.',
            ],
            'icon' => 'heroicon-o-envelope',
            'sections' => [
                // Section 1: Email Templates
                [
                    'id' => 'email-templates',
                    'title' => ['ro' => 'Cum gestionezi email template-urile', 'en' => 'How to manage email templates'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Email Templates] din grupul "Comunicare".',
                                'en' => 'From the left sidebar menu, click on [Email Templates] in the "Comunicare" group.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina cu lista tuturor template-urilor de email afisate intr-un tabel. Fiecare template are un tip (slug), un nume, subiectul si statusul activ/inactiv.',
                                'en' => 'The page opens showing all email templates displayed in a table. Each template has a type (slug), a name, the subject and active/inactive status.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a crea un template nou, apasa butonul [Creare Email Template] din coltul din dreapta sus.',
                                'en' => 'To create a new template, click the [Creare Email Template] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Template Details" selecteaza tipul template-ului din dropdown-ul "Slug". Tipul este unic per marketplace — nu poti avea doua template-uri cu acelasi tip.',
                                'en' => 'In the "Template Details" section, select the template type from the "Slug" dropdown. The type is unique per marketplace — you cannot have two templates with the same type.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza campul "Name" (obligatoriu) care este numele descriptiv al template-ului. Activeaza sau dezactiveaza template-ul folosind toggle-ul "Is Active".',
                                'en' => 'Fill in the "Name" field (required) which is the descriptive name of the template. Enable or disable the template using the "Is Active" toggle.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Email Content", completeaza campul "Subject" (subiectul emailului). Poti folosi variabile precum {{customer_name}} sau {{order_number}} direct in subiect.',
                                'en' => 'In the "Email Content" section, fill in the "Subject" field (email subject). You can use variables like {{customer_name}} or {{order_number}} directly in the subject.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Completeaza corpul emailului in campul "Body HTML" folosind editorul vizual (RichEditor). Poti formata textul, adauga link-uri, imagini si variabile {{variable}}.',
                                'en' => 'Fill in the email body in the "Body HTML" field using the visual editor (RichEditor). You can format text, add links, images and {{variable}} variables.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Optional, completeaza campul "Body Text" — aceasta este versiunea plain-text a emailului, afisata in clientii de email care nu suporta HTML.',
                                'en' => 'Optionally, fill in the "Body Text" field — this is the plain-text version of the email, displayed in email clients that do not support HTML.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salvare] pentru a salva template-ul. Pentru a edita un template existent, apasa pe actiunea [Editare] din coloana de actiuni a tabelului.',
                                'en' => 'Click [Salvare] to save the template. To edit an existing template, click the [Editare] action in the table actions column.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti previzualiza un template folosind actiunea [Preview] care deschide un modal cu emailul randat. Pentru a sterge un template, foloseste actiunea [Sterge].',
                                'en' => 'You can preview a template using the [Preview] action which opens a modal with the rendered email. To delete a template, use the [Sterge] action.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Slug',
                            'description' => [
                                'ro' => 'Tipul template-ului selectat din dropdown. Optiuni: ticket_purchase, welcome, points_earned, refund_approved, refund_rejected, event_reminder, organizer_payout, organizer_report. Fiecare tip poate fi folosit o singura data per marketplace.',
                                'en' => 'The template type selected from the dropdown. Options: ticket_purchase, welcome, points_earned, refund_approved, refund_rejected, event_reminder, organizer_payout, organizer_report. Each type can be used only once per marketplace.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Nume', 'en' => 'Name'],
                            'description' => [
                                'ro' => 'Numele descriptiv al template-ului. Folosit pentru identificare in lista de template-uri.',
                                'en' => 'The descriptive name of the template. Used for identification in the templates list.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Is Active',
                            'description' => [
                                'ro' => 'Toggle care activeaza sau dezactiveaza template-ul. Un template inactiv nu va fi folosit pentru trimiterea automata a emailurilor.',
                                'en' => 'Toggle that enables or disables the template. An inactive template will not be used for automatic email sending.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Subiect', 'en' => 'Subject'],
                            'description' => [
                                'ro' => 'Subiectul emailului trimis. Suporta variabile in format {{variabila}} care sunt inlocuite automat la trimitere.',
                                'en' => 'The subject of the sent email. Supports variables in {{variable}} format which are automatically replaced when sending.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Body HTML',
                            'description' => [
                                'ro' => 'Corpul emailului in format HTML, editat cu editorul vizual RichEditor. Suporta formatare, imagini, link-uri si variabile {{variabila}}.',
                                'en' => 'The email body in HTML format, edited with the RichEditor visual editor. Supports formatting, images, links and {{variable}} variables.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Body Text',
                            'description' => [
                                'ro' => 'Versiunea plain-text a emailului. Este afisata in clientii de email care nu suporta HTML. Daca nu este completat, se genereaza automat din versiunea HTML.',
                                'en' => 'The plain-text version of the email. Displayed in email clients that do not support HTML. If not filled in, it is automatically generated from the HTML version.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Tipurile de template sunt unice per marketplace. Daca ai nevoie de un template de tip "ticket_purchase" si acesta exista deja, editeaza template-ul existent in loc sa creezi unul nou.',
                                'en' => 'Template types are unique per marketplace. If you need a "ticket_purchase" template and one already exists, edit the existing template instead of creating a new one.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Dezactivarea unui template nu il sterge — doar previne utilizarea lui in trimiterea automata de emailuri. Poti sa il reactivezi oricand.',
                                'en' => 'Disabling a template does not delete it — it only prevents its use in automatic email sending. You can reactivate it at any time.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 2: Template Variables
                [
                    'id' => 'template-variables',
                    'title' => ['ro' => 'Intelegerea si utilizarea variabilelor in template-uri', 'en' => 'Understanding and using template variables'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Variabilele sunt placeholder-e in format {{nume_variabila}} care sunt inlocuite automat cu datele reale cand emailul este trimis.',
                                'en' => 'Variables are placeholders in {{variable_name}} format that are automatically replaced with real data when the email is sent.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti folosi variabile atat in campul "Subject" cat si in "Body HTML". Variabilele sunt case-sensitive — scrie-le exact cum apar in lista de variabile.',
                                'en' => 'You can use variables both in the "Subject" field and in "Body HTML". Variables are case-sensitive — write them exactly as they appear in the variables list.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In formularul de editare al unui template, sectiunea "Available Variables" (colapsabila) afiseaza toate variabilele disponibile pentru tipul de template selectat.',
                                'en' => 'In the template edit form, the "Available Variables" section (collapsible) displays all available variables for the selected template type.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa pe sectiunea colapsabila "Available Variables" pentru a o deschide. Vei vedea lista completa de variabile cu descrierea fiecareia.',
                                'en' => 'Click on the collapsible "Available Variables" section to expand it. You will see the complete list of variables with each one\'s description.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Copiaza variabila dorita si insereaz-o in subiect sau in corpul emailului. Exemplu: "Buna, {{customer_name}}! Comanda ta #{{order_number}} a fost confirmata."',
                                'en' => 'Copy the desired variable and paste it into the subject or email body. Example: "Hello, {{customer_name}}! Your order #{{order_number}} has been confirmed."',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => '{{customer_name}}',
                            'description' => [
                                'ro' => 'Numele complet al clientului caruia i se trimite emailul.',
                                'en' => 'The full name of the customer receiving the email.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{customer_email}}',
                            'description' => [
                                'ro' => 'Adresa de email a clientului.',
                                'en' => 'The customer\'s email address.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{order_number}}',
                            'description' => [
                                'ro' => 'Numarul unic al comenzii asociate.',
                                'en' => 'The unique number of the associated order.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{event_name}}',
                            'description' => [
                                'ro' => 'Numele evenimentului asociat comenzii sau actiunii.',
                                'en' => 'The name of the event associated with the order or action.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{event_date}}',
                            'description' => [
                                'ro' => 'Data evenimentului, formatata conform setarilor platformei.',
                                'en' => 'The event date, formatted according to platform settings.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{event_venue}}',
                            'description' => [
                                'ro' => 'Numele locatiei unde se desfasoara evenimentul.',
                                'en' => 'The name of the venue where the event takes place.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{total_amount}}',
                            'description' => [
                                'ro' => 'Suma totala a comenzii, inclusiv moneda (ex: "150.00 RON").',
                                'en' => 'The total order amount, including currency (e.g., "150.00 RON").',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{ticket_type}}',
                            'description' => [
                                'ro' => 'Tipul biletului achizitionat (ex: "General Admission", "VIP").',
                                'en' => 'The purchased ticket type (e.g., "General Admission", "VIP").',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{ticket_count}}',
                            'description' => [
                                'ro' => 'Numarul de bilete din comanda.',
                                'en' => 'The number of tickets in the order.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{refund_amount}}',
                            'description' => [
                                'ro' => 'Suma rambursata (disponibila in template-urile refund_approved si refund_rejected).',
                                'en' => 'The refunded amount (available in refund_approved and refund_rejected templates).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{points_earned}}',
                            'description' => [
                                'ro' => 'Numarul de puncte castigate (disponibil in template-ul points_earned).',
                                'en' => 'The number of points earned (available in the points_earned template).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{payout_amount}}',
                            'description' => [
                                'ro' => 'Suma platii catre organizator (disponibila in template-ul organizer_payout).',
                                'en' => 'The payout amount to the organizer (available in the organizer_payout template).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => '{{marketplace_name}}',
                            'description' => [
                                'ro' => 'Numele marketplace-ului configurat in setari.',
                                'en' => 'The marketplace name configured in settings.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Nu toate variabilele sunt disponibile in toate tipurile de template-uri. De exemplu, {{refund_amount}} functioneaza doar in template-urile de tip refund_approved si refund_rejected. Verifica sectiunea "Available Variables" pentru fiecare tip.',
                                'en' => 'Not all variables are available in all template types. For example, {{refund_amount}} only works in refund_approved and refund_rejected templates. Check the "Available Variables" section for each type.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Daca o variabila nu poate fi rezolvata la trimitere (de exemplu, datele lipsesc), ea va fi inlocuita cu un text gol. Testeaza template-urile folosind actiunea [Preview] inainte de a le activa.',
                                'en' => 'If a variable cannot be resolved when sending (for example, data is missing), it will be replaced with empty text. Test templates using the [Preview] action before activating them.',
                            ],
                            'type' => 'warning',
                        ],
                    ],
                ],

                // Section 3: Newsletters
                [
                    'id' => 'newsletters',
                    'title' => ['ro' => 'Cum creezi si trimiti newslettere', 'en' => 'How to create and send newsletters'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Newslettere] din grupul "Comunicare".',
                                'en' => 'From the left sidebar menu, click on [Newslettere] in the "Comunicare" group.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina cu lista tuturor campaniilor de newsletter. Fiecare campanie afiseaza numele, statusul (Draft, Scheduled, Sending, Sent, Cancelled) si data trimiterii.',
                                'en' => 'The page opens showing all newsletter campaigns. Each campaign displays the name, status (Draft, Scheduled, Sending, Sent, Cancelled) and sending date.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa butonul [Creare Newsletter] din coltul din dreapta sus pentru a crea o campanie noua.',
                                'en' => 'Click the [Creare Newsletter] button in the top right corner to create a new campaign.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Campaign Details", completeaza campul "Name" — numele intern al campaniei care te ajuta sa o identifici in lista.',
                                'en' => 'In the "Campaign Details" section, fill in the "Name" field — the internal campaign name that helps you identify it in the list.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Recipients", selecteaza listele de contact din campul "Contact Lists" (multi-select). Poti selecta si tag-uri de contact din campul "Contact Tags" pentru a filtra destinatarii suplimentar.',
                                'en' => 'In the "Recipients" section, select contact lists from the "Contact Lists" field (multi-select). You can also select contact tags from the "Contact Tags" field to further filter recipients.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Dupa selectarea listelor si tag-urilor, vei vedea o previzualizare a numarului total de destinatari ("Recipient count preview").',
                                'en' => 'After selecting lists and tags, you will see a preview of the total number of recipients ("Recipient count preview").',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Sender", completeaza campurile "From Name" (numele expeditorului), "From Email" (adresa de email a expeditorului) si optional "Reply To" (adresa pentru raspunsuri).',
                                'en' => 'In the "Sender" section, fill in the "From Name" (sender name), "From Email" (sender email address) and optionally "Reply To" (reply address) fields.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "Email Content", completeaza "Subject" (subiectul emailului), "Preview Text" (textul scurt afisat in inbox-ul destinatarului), "HTML Body" (corpul emailului in format vizual) si optional "Plain Text" (versiunea text simplu).',
                                'en' => 'In the "Email Content" section, fill in "Subject" (email subject), "Preview Text" (short text shown in the recipient\'s inbox), "HTML Body" (visual email body) and optionally "Plain Text" (plain text version).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salvare] pentru a salva campania ca Draft. Newsletterul nu va fi trimis pana cand nu initiezi trimiterea sau programarea.',
                                'en' => 'Click [Salvare] to save the campaign as Draft. The newsletter will not be sent until you initiate sending or scheduling.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a trimite imediat un newsletter aflat in status Draft, apasa actiunea [Send]. Newsletterul va incepe sa fie trimis catre toti destinatarii selectati.',
                                'en' => 'To immediately send a newsletter in Draft status, click the [Send] action. The newsletter will begin sending to all selected recipients.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti duplica o campanie existenta folosind actiunea [Duplicate]. Aceasta creeaza o copie a newsletterului in status Draft, utila pentru a reutiliza un format sau continut.',
                                'en' => 'You can duplicate an existing campaign using the [Duplicate] action. This creates a copy of the newsletter in Draft status, useful for reusing a format or content.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume', 'en' => 'Name'],
                            'description' => [
                                'ro' => 'Numele intern al campaniei de newsletter. Nu este vizibil destinatarilor, serveste doar pentru identificare in panou.',
                                'en' => 'The internal name of the newsletter campaign. Not visible to recipients, used only for identification in the panel.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Status',
                            'description' => [
                                'ro' => 'Starea curenta a campaniei: Draft (ciorna, editabila), Scheduled (programata pentru trimitere), Sending (in curs de trimitere), Sent (trimisa complet), Cancelled (anulata).',
                                'en' => 'The current campaign state: Draft (editable draft), Scheduled (scheduled for sending), Sending (currently sending), Sent (fully sent), Cancelled (cancelled).',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Liste de contact', 'en' => 'Contact Lists'],
                            'description' => [
                                'ro' => 'Listele de contact catre care se trimite newsletterul. Poti selecta una sau mai multe liste. Destinatarii finali sunt reuniunea tuturor listelor selectate.',
                                'en' => 'The contact lists to which the newsletter is sent. You can select one or more lists. Final recipients are the union of all selected lists.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Tag-uri de contact', 'en' => 'Contact Tags'],
                            'description' => [
                                'ro' => 'Tag-uri pentru filtrarea suplimentara a destinatarilor. Permite trimiterea doar catre contactele care au anumite tag-uri.',
                                'en' => 'Tags for additional recipient filtering. Allows sending only to contacts that have certain tags.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'From Name',
                            'description' => [
                                'ro' => 'Numele expeditorului afisat in inbox-ul destinatarului (ex: "Echipa Bilete.online").',
                                'en' => 'The sender name displayed in the recipient\'s inbox (e.g., "Bilete.online Team").',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'From Email',
                            'description' => [
                                'ro' => 'Adresa de email a expeditorului. Trebuie sa fie o adresa valida configurata pe domeniul tau.',
                                'en' => 'The sender email address. Must be a valid address configured on your domain.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Reply To',
                            'description' => [
                                'ro' => 'Adresa de email la care vor ajunge raspunsurile destinatarilor. Daca nu este completata, raspunsurile merg la adresa "From Email".',
                                'en' => 'The email address where recipient replies will be sent. If not filled in, replies go to the "From Email" address.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Subiect', 'en' => 'Subject'],
                            'description' => [
                                'ro' => 'Subiectul emailului de newsletter afisat in inbox-ul destinatarilor.',
                                'en' => 'The newsletter email subject displayed in recipients\' inboxes.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Preview Text',
                            'description' => [
                                'ro' => 'Textul scurt afisat langa subiect in inbox-ul destinatarului (pre-header). Ajuta la cresterea ratei de deschidere.',
                                'en' => 'The short text displayed next to the subject in the recipient\'s inbox (pre-header). Helps increase the open rate.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'HTML Body',
                            'description' => [
                                'ro' => 'Corpul emailului in format HTML, editat cu editorul vizual RichEditor. Acesta este continutul principal al newsletterului.',
                                'en' => 'The email body in HTML format, edited with the RichEditor visual editor. This is the main content of the newsletter.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => 'Plain Text',
                            'description' => [
                                'ro' => 'Versiunea text simplu a newsletterului. Afisata in clientii de email care nu suporta HTML.',
                                'en' => 'The plain text version of the newsletter. Displayed in email clients that do not support HTML.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Verifica intotdeauna previzualizarea numarului de destinatari inainte de a trimite un newsletter. Daca numarul este 0, verifica listele si tag-urile selectate.',
                                'en' => 'Always check the recipient count preview before sending a newsletter. If the count is 0, verify the selected lists and tags.',
                            ],
                            'type' => 'warning',
                        ],
                        [
                            'text' => [
                                'ro' => 'Un newsletter poate fi trimis doar din statusul Draft. Dupa ce a fost trimis (status "Sent"), nu mai poate fi modificat. Foloseste actiunea [Duplicate] daca vrei sa reutilizezi continutul.',
                                'en' => 'A newsletter can only be sent from Draft status. After it has been sent ("Sent" status), it can no longer be modified. Use the [Duplicate] action if you want to reuse the content.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 4: Newsletter Scheduling
                [
                    'id' => 'newsletter-scheduling',
                    'title' => ['ro' => 'Programarea newsletterelor', 'en' => 'Scheduling newsletters'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'In loc sa trimiti un newsletter imediat, il poti programa pentru o data si ora viitoare. Programarea este disponibila doar pentru newsletterele in status Draft sau Scheduled.',
                                'en' => 'Instead of sending a newsletter immediately, you can schedule it for a future date and time. Scheduling is available only for newsletters in Draft or Scheduled status.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In formularul de editare al newsletterului, gasesti sectiunea "Scheduling" cu campul "Send At" (data si ora trimiterii).',
                                'en' => 'In the newsletter edit form, find the "Scheduling" section with the "Send At" field (sending date and time).',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Selecteaza data si ora dorita folosind selectorul de data-timp. Ora trebuie sa fie in viitor.',
                                'en' => 'Select the desired date and time using the date-time picker. The time must be in the future.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa actiunea [Schedule] pentru a programa newsletterul. Statusul campaniei se va schimba din "Draft" in "Scheduled".',
                                'en' => 'Click the [Schedule] action to schedule the newsletter. The campaign status will change from "Draft" to "Scheduled".',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Un newsletter programat va fi trimis automat la data si ora setata. Poti vedea data programata in lista de newslettere.',
                                'en' => 'A scheduled newsletter will be sent automatically at the set date and time. You can see the scheduled date in the newsletters list.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru a anula o programare, apasa actiunea [Cancel] pe un newsletter cu status "Scheduled". Statusul revine la "Draft" si poti edita sau reprograma campania.',
                                'en' => 'To cancel a schedule, click the [Cancel] action on a newsletter with "Scheduled" status. The status returns to "Draft" and you can edit or reschedule the campaign.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => 'Send At',
                            'description' => [
                                'ro' => 'Data si ora la care newsletterul va fi trimis automat. Campul este disponibil doar pentru campaniile in status Draft sau Scheduled. Trebuie sa fie o data in viitor.',
                                'en' => 'The date and time when the newsletter will be sent automatically. The field is available only for campaigns in Draft or Scheduled status. Must be a future date.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Programeaza newsletterele pentru orele cu cea mai mare rata de deschidere — de obicei dimineata (9:00-11:00) sau dupa-amiaza devreme (14:00-16:00) in zilele lucratoare.',
                                'en' => 'Schedule newsletters for hours with the highest open rate — usually morning (9:00-11:00) or early afternoon (14:00-16:00) on weekdays.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Dupa trimitere, poti vedea statisticile campaniei: numarul de destinatari, emailuri trimise, rata de deschidere (open rate) si rata de click. Aceste date sunt afisate in pagina de vizualizare a newsletterului.',
                                'en' => 'After sending, you can view campaign statistics: number of recipients, emails sent, open rate and click rate. This data is displayed on the newsletter view page.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 5: Contact Lists
                [
                    'id' => 'contact-lists',
                    'title' => ['ro' => 'Gestionarea listelor de contact', 'en' => 'Managing contact lists'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Din meniul lateral din stanga, apasa pe [Liste de contact] din grupul "Comunicare".',
                                'en' => 'From the left sidebar menu, click on [Liste de contact] in the "Comunicare" group.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Se deschide pagina cu toate listele de contact. Fiecare lista afiseaza numele, tipul (Manual sau Dynamic), numarul de contacte si statusul activ/inactiv.',
                                'en' => 'The page opens showing all contact lists. Each list displays the name, type (Manual or Dynamic), number of contacts and active/inactive status.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa butonul [Creare Lista de contact] din coltul din dreapta sus.',
                                'en' => 'Click the [Creare Lista de contact] button in the top right corner.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'In sectiunea "List Details", completeaza "Name" (obligatoriu), "Description" (optional) si seteaza toggle-ul "Is Active".',
                                'en' => 'In the "List Details" section, fill in "Name" (required), "Description" (optional) and set the "Is Active" toggle.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Selecteaza tipul listei: "Manual" sau "Dynamic". Tipul determina cum sunt adaugati contactele in lista.',
                                'en' => 'Select the list type: "Manual" or "Dynamic". The type determines how contacts are added to the list.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru o lista de tip "Manual", vei vedea un selector multi-select de clienti. Cauta si selecteaza clientii pe care vrei sa ii adaugi in lista.',
                                'en' => 'For a "Manual" type list, you will see a multi-select customer selector. Search and select the customers you want to add to the list.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Pentru o lista de tip "Dynamic", vei vedea un repeater de reguli ("Subscriber Conditions"). Adauga conditii care definesc automat cine face parte din lista.',
                                'en' => 'For a "Dynamic" type list, you will see a rules repeater ("Subscriber Conditions"). Add conditions that automatically define who is part of the list.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Conditiile disponibile pentru listele dinamice includ: status newsletter (abonat/dezabonat), numar de achizitii (purchase count), categorie de eveniment, locatie si varsta.',
                                'en' => 'Available conditions for dynamic lists include: newsletter status (subscribed/unsubscribed), purchase count, event category, location and age.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Apasa [Salvare] pentru a crea lista. Listele dinamice se actualizeaza automat cand conditiile se schimba — nu trebuie sa adaugi manual contacte noi.',
                                'en' => 'Click [Salvare] to create the list. Dynamic lists update automatically when conditions change — you do not need to manually add new contacts.',
                            ],
                        ],
                    ],
                    'fields' => [
                        [
                            'name' => ['ro' => 'Nume', 'en' => 'Name'],
                            'description' => [
                                'ro' => 'Numele listei de contact. Folosit pentru identificare la selectarea destinatarilor in newslettere.',
                                'en' => 'The contact list name. Used for identification when selecting recipients in newsletters.',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Descriere', 'en' => 'Description'],
                            'description' => [
                                'ro' => 'O descriere optionala a listei pentru a ajuta la identificarea scopului ei.',
                                'en' => 'An optional description of the list to help identify its purpose.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => 'Is Active',
                            'description' => [
                                'ro' => 'Toggle care activeaza sau dezactiveaza lista. O lista inactiva nu va fi disponibila pentru selectare in newslettere.',
                                'en' => 'Toggle that enables or disables the list. An inactive list will not be available for selection in newsletters.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Tip lista', 'en' => 'List Type'],
                            'description' => [
                                'ro' => 'Tipul listei: "Manual" (contactele sunt adaugate individual) sau "Dynamic" (contactele sunt determinate automat pe baza regulilor definite).',
                                'en' => 'The list type: "Manual" (contacts are added individually) or "Dynamic" (contacts are automatically determined based on defined rules).',
                            ],
                            'required' => true,
                        ],
                        [
                            'name' => ['ro' => 'Conditii abonati (Dynamic)', 'en' => 'Subscriber Conditions (Dynamic)'],
                            'description' => [
                                'ro' => 'Repeater de reguli pentru listele dinamice. Fiecare regula contine un criteriu (status newsletter, numar achizitii, categorie, locatie, varsta) si o valoare. Contactele care indeplinesc toate conditiile sunt incluse automat.',
                                'en' => 'Rules repeater for dynamic lists. Each rule contains a criterion (newsletter status, purchase count, category, location, age) and a value. Contacts meeting all conditions are automatically included.',
                            ],
                            'required' => false,
                        ],
                        [
                            'name' => ['ro' => 'Selector clienti (Manual)', 'en' => 'Customer Selector (Manual)'],
                            'description' => [
                                'ro' => 'Camp multi-select pentru listele manuale. Cauta si selecteaza clientii individuali pe care vrei sa ii incluzi in lista.',
                                'en' => 'Multi-select field for manual lists. Search and select individual customers you want to include in the list.',
                            ],
                            'required' => false,
                        ],
                    ],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Listele dinamice sunt recomandate pentru segmentare automata — de exemplu, "toti clientii care au cumparat bilete in ultimele 3 luni" sau "clienti din Bucuresti". Nu trebuie sa le actualizezi manual.',
                                'en' => 'Dynamic lists are recommended for automatic segmentation — for example, "all customers who bought tickets in the last 3 months" or "customers from Bucharest". You do not need to update them manually.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti combina liste manuale si dinamice in acelasi newsletter. Destinatarii finali vor fi reuniunea tuturor contactelor din listele selectate, fara duplicate.',
                                'en' => 'You can combine manual and dynamic lists in the same newsletter. Final recipients will be the union of all contacts from selected lists, without duplicates.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Dezactivarea unei liste de contact nu sterge contactele din ea — doar o face indisponibila pentru selectare in newslettere noi.',
                                'en' => 'Disabling a contact list does not delete the contacts in it — it only makes it unavailable for selection in new newsletters.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],

                // Section 6: Contact Tags
                [
                    'id' => 'contact-tags',
                    'title' => ['ro' => 'Utilizarea tag-urilor de contact', 'en' => 'Using contact tags'],
                    'steps' => [
                        [
                            'text' => [
                                'ro' => 'Tag-urile de contact sunt etichete pe care le poti atasa contactelor pentru a le organiza si segmenta mai usor. Tag-urile pot fi folosite ca filtru suplimentar la trimiterea newsletterelor.',
                                'en' => 'Contact tags are labels you can attach to contacts to organize and segment them more easily. Tags can be used as an additional filter when sending newsletters.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Tag-urile pot fi atasate contactelor la crearea sau editarea unui client. In formularul clientului, gasesti campul "Tags" unde poti selecta sau crea tag-uri noi.',
                                'en' => 'Tags can be attached to contacts when creating or editing a customer. In the customer form, find the "Tags" field where you can select or create new tags.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Exemple de tag-uri utile: "VIP", "Abonat newsletter", "Client frecvent", "Participant festival", "Student". Tag-urile sunt flexibile si le poti crea dupa nevoie.',
                                'en' => 'Examples of useful tags: "VIP", "Newsletter subscriber", "Frequent customer", "Festival attendee", "Student". Tags are flexible and you can create them as needed.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'La crearea unui newsletter, in sectiunea "Recipients", poti selecta tag-uri in campul "Contact Tags" pentru a filtra destinatarii. Doar contactele care au cel putin unul dintre tag-urile selectate vor primi emailul.',
                                'en' => 'When creating a newsletter, in the "Recipients" section, you can select tags in the "Contact Tags" field to filter recipients. Only contacts that have at least one of the selected tags will receive the email.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Tag-urile functioneaza in combinatie cu listele de contact: destinatarii finali sunt contactele din listele selectate care au si tag-urile specificate.',
                                'en' => 'Tags work in combination with contact lists: final recipients are contacts from selected lists that also have the specified tags.',
                            ],
                        ],
                        [
                            'text' => [
                                'ro' => 'Poti adauga sau elimina tag-uri in masa folosind actiunile de tabel din lista de clienti. Selecteaza mai multi clienti si foloseste actiunea de masa pentru a le atasa tag-uri.',
                                'en' => 'You can add or remove tags in bulk using table actions in the customers list. Select multiple customers and use the bulk action to attach tags.',
                            ],
                        ],
                    ],
                    'fields' => [],
                    'tips' => [
                        [
                            'text' => [
                                'ro' => 'Foloseste tag-uri consistente si cu denumiri clare. Evita duplicarea tag-urilor cu nume similare (ex: "vip" si "VIP" sunt acelasi lucru daca sistemul este case-insensitive, dar pot fi diferite daca nu).',
                                'en' => 'Use consistent tags with clear names. Avoid duplicating tags with similar names (e.g., "vip" and "VIP" are the same thing if the system is case-insensitive, but may differ if not).',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Tag-urile sunt optionale — poti trimite newslettere doar pe baza listelor de contact, fara a filtra dupa tag-uri. Tag-urile ofera un nivel suplimentar de segmentare.',
                                'en' => 'Tags are optional — you can send newsletters based on contact lists alone, without filtering by tags. Tags provide an additional level of segmentation.',
                            ],
                            'type' => 'info',
                        ],
                        [
                            'text' => [
                                'ro' => 'Combina listele dinamice cu tag-urile pentru segmentare avansata. De exemplu, lista dinamica "clienti cu 3+ achizitii" + tag-ul "VIP" = newsletter targetat pentru cei mai buni clienti.',
                                'en' => 'Combine dynamic lists with tags for advanced segmentation. For example, dynamic list "customers with 3+ purchases" + "VIP" tag = targeted newsletter for best customers.',
                            ],
                            'type' => 'info',
                        ],
                    ],
                ],
            ],
        ];
    }
}
