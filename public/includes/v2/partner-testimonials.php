<?php
/**
 * viaqui.com v2: what partners say, shown on /partners. Edit here, nowhere else.
 *
 * - video: one film (YouTube). The page shows its poster and loads the player only when someone presses play.
 * - quotes: short testimonials. The one marked 'pull' => true is the large quote before the finale; the rest form the wall.
 *
 * 'demo' => true marks a stand-in written by us while the real material is prepared. Stand-in quotes are shown to
 * everyone with an "Example" tag and a line above the wall that says they are examples (the owner's call), so nobody
 * takes them for a real customer. The film is the exception: a stand-in film would only confuse, so it shows only in
 * preview (/partners?preview=1). To publish for real, replace the text (or the YouTube id) and set 'demo' => false.
 *
 * The texts go through v2_t() so the stand-ins follow the language of the page; names of people and places do not.
 * A real quote is written here in the language it was said in, without v2_t().
 */

function v2_partner_testimonials(): array
{
    return [
        'video' => [
            'demo' => true,
            'youtube' => 'aqz-KE-bpKQ', // stand-in (Blender open film); the Sf. Ana film goes here once it's edited
            'duration' => '2:30',
            'title' => v2_t('Sf. Ana: tickets online, fast entry at the gate'),
            'quote' => v2_t('We went from queues at the entrance to people arriving with the ticket on their phone. Every morning we see how many are coming and for which time slot, and at the end of the day we know exactly how much was sold online and how much at the ticket office.'),
            'name' => v2_t('First name Last name'),
            'role' => v2_t('Manager'),
            'place' => 'Sf. Ana',
            'results' => [v2_t('Entry without queues'), v2_t('Online + ticket office in the same report')],
        ],
        'quotes' => [
            [
                'demo' => true,
                'quote' => v2_t('The rooms have slots every hour, and people used to message us on Instagram to book. Now they pick the time themselves, pay and get the ticket. We just welcome them.'),
                'name' => 'Andreea M.', 'role' => v2_t('Founder'), 'venue' => v2_t('Escape room'), 'city' => 'Cluj-Napoca',
                'result' => v2_t('Bookings without messages'),
            ],
            [
                'demo' => true,
                'quote' => v2_t('The ticket office and online sales are in the same report. When we close the register we no longer count twice or keep separate spreadsheets for the accountant.'),
                'name' => 'Radu T.', 'role' => v2_t('Administrator'), 'venue' => v2_t('Museum'), 'city' => 'Sibiu',
                'result' => v2_t('Register closed in 5 minutes'),
            ],
            [
                'demo' => true,
                'quote' => v2_t('The commission didn\'t touch our price: what we display is what we receive. That decided it for us, the rest came as a bonus.'),
                'name' => 'Ioana P.', 'role' => v2_t('Manager'), 'venue' => v2_t('Adventure park'), 'city' => 'Brașov',
                'result' => v2_t('The full price, on every ticket'),
            ],
            [
                'demo' => true,
                'quote' => v2_t('We scan with the phone even where the signal is weak, and everything syncs when the internet comes back. For a trail out in nature, that matters enormously.'),
                'name' => 'Mihai D.', 'role' => v2_t('Coordinator'), 'venue' => v2_t('Nature reserve'), 'city' => 'Harghita',
                'result' => v2_t('Offline scanning'),
            ],
            [
                'demo' => true,
                'quote' => v2_t('The workshops have few places and children of different ages. We set up ticket types by age, and parents see straight away what suits them.'),
                'name' => 'Elena S.', 'role' => v2_t('Founder'), 'venue' => v2_t('Creative workshops'), 'city' => 'București',
                'result' => v2_t('Tickets by age'),
            ],
            [
                'demo' => true,
                'quote' => v2_t('Ads were costing us more and more and we didn\'t know what was bringing sales. With tracking connected we see which campaign sells, and we cut what wasn\'t working.'),
                'name' => 'Vlad C.', 'role' => v2_t('Marketing'), 'venue' => v2_t('Guided tours'), 'city' => 'Timișoara',
                'result' => v2_t('Ads that sell'),
            ],
            [
                'demo' => true,
                'pull' => true,
                'quote' => v2_t('We set up the account in one afternoon and were already selling the next day. We needed nothing beyond what we had: a phone for scanning and the printer at the till.'),
                'name' => 'Cristina N.', 'role' => v2_t('Owner'), 'venue' => v2_t('Amusement park'), 'city' => 'Constanța',
                'result' => v2_t('Live the next day'),
            ],
        ],
    ];
}
