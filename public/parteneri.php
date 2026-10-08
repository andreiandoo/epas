<?php
/**
 * Partners: /partners (v2, direction "product theatre"). The single sales page for operators: everything
 * /devino-partener, /pentru-locatii and /vinde-bilete say, in one place (those pages stay online). The vague
 * Start / Growth / Pro tiers of /pentru-locatii are left out on purpose.
 *
 * Top to bottom: hero with the stage (personalised by ?tip=<type>&loc=<name>, copy shared with /devino-partener in
 * includes/v2/partner-profiles.php), ecosystem numbers, chapter nav, who it's for (+ live categories), the problem and
 * its fix, booking on slots (#booking), a day at the venue (#o-zi), the operator panel (#panou), the ticket office
 * (#ghiseu), the scanning app (#scanare), hardware, partner stories (#povesti: a film and a wall of quotes), everything
 * you get (#ce-primesti) and the modules, visibility / SEO
 * (#vizibilitate), analytics and tracking (#analytics), payments (#plati), the money (#bani, calculator), fiscal / ANAF
 * (#fiscal), how to start (#cum), the Tixello engine (#tehnologie), FAQ (#intrebari), one large partner quote, and the
 * finale: self-service signup or a demo request (#demo, same lead pipeline as /pentru-locatii through for-venues.js).
 *
 * The stories come from includes/v2/partner-testimonials.php. The quotes are shown to everyone; the ones marked
 * 'demo' are stand-ins written by us and carry an "Example" tag plus a line that says so, until the first venues tell
 * their own story. The film is different: a stand-in film would only confuse, so it waits for the real one (?preview=1,
 * which also skips the page cache) with a "Demo" tag, never to visitors; with nothing real to show, the stories
 * sections and their chapter link are left out.
 *
 * The screens are HTML mock-ups with sample figures, marked as such; the catalogue counts come from the API.
 * partners.js runs the stage, the demos, the funnel pings (leads.track, like /devino-partener) and, on large screens
 * with motion allowed, the scroll scenes (GSAP + ScrollTrigger + Lenis, loaded only there). Everything reads without
 * JavaScript and without motion.
 */

$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/partner-profiles.php';
require_once __DIR__ . '/includes/v2/partner-testimonials.php';

$ptAccent = '<strong class="pt-accent">' . v2_te('just 2%*') . '</strong>';
$ptProfiles = v2_partner_profiles($ptAccent);

$ptR = api_cached_many([
    'attractions' => ['key' => 'v2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
]);
$ptAttractions = (int) ($ptR['attractions']['data']['pagination']['total'] ?? 0);
$ptCities = count($V2NAV['allCities'] ?? []);
$ptCategories = array_values(array_filter($V2NAV['categories'] ?? [], fn ($c) => !empty($c['name'])));

// Real results of the Tixello ecosystem viaqui.com runs on: the live totals tixello.com shows (core /ecosystem-stats,
// computed once a day), kept here 6 hours; the last confirmed figures stand in if the API doesn't answer.
$ptEco = api_cached('v2_ecosystem_stats', fn () => api_get('/ecosystem-stats'), 21600);
$ptEcoData = is_array($ptEco['data'] ?? null) ? $ptEco['data'] : [];
$ptEcoValue = static fn (string $key, float $fallback): float => (float) ($ptEcoData[$key] ?? 0) > 0 ? (float) $ptEcoData[$key] : $fallback;
$ptEvents = (int) $ptEcoValue('events', 4294);
$ptCustomers = (int) $ptEcoValue('customers', 96341);
$ptTickets = (int) $ptEcoValue('tickets_sold', 301310);
$ptRevenue = (int) round($ptEcoValue('revenue_eur', 4409557));
// [value, label, what is printed before the number]
$ptStats = [[$ptEvents, v2_t('Events & activities'), ''], [$ptCustomers, v2_t('Customers in the base'), ''], [$ptTickets, v2_t('Tickets sold'), ''], [$ptRevenue, v2_t('Sales generated'), '€']];
// "over …" in the copy: rounded down, so it's never more than the truth
$ptOverThousands = static fn (int $n): string => v2_thousands((int) (floor($n / 1000) * 1000));
$ptOverMillions = static fn (int $n): string => rtrim(rtrim(number_format(floor($n / 100000) / 10, 1, '.', ','), '0'), '.');

$ptWho = [
    ['lock-simple', v2_t('Escape rooms'), v2_t('Time slots, capacity per room, different ticket holders, group tickets and fast check-in.')],
    ['buildings', v2_t('Museums & exhibitions'), v2_t('Admission tickets, temporary exhibitions, guided tours, child/adult prices, free entries and opening hours.')],
    ['castle-turret', v2_t('Parks & leisure'), v2_t('Packages, age groups, day access, extras and capacity.')],
    ['map-pin', v2_t('Caves & nature reserves'), v2_t('Tours, access rules, difficulty level, equipment, guide and seasons.')],
    ['star', v2_t('Workshops & education'), v2_t('Limited places, recommended ages, materials included, school groups.')],
    ['globe-simple', v2_t('Tours & experiences'), v2_t('City walks, food tours, history tours, sightseeing and private experiences.')],
];
// the pain, then what the platform does about it (the fix repeats claims made further down the page)
$ptProblems = [
    ['coins', v2_t('Commissions out of your margin'), v2_t('You pay, on every ticket. At volume, it is a real hole in the budget.'), v2_t('2%*, without touching your price'), v2_t('You set your price and receive it in full at payout.')],
    ['calendar-blank', v2_t('Inflexible booking'), v2_t('Activities have slots, days, capacities. Most platforms do not support them.'), v2_t('Slots, days and capacity'), v2_t('The customer picks the day, the time, the participants and the options.')],
    ['x', v2_t('Lost tracking'), v2_t('Ad blockers and iOS block conversion data, so you pay more for ads.'), v2_t('100% of events tracked'), v2_t('Conversions sent server-side: ads up to 60% cheaper.')],
];
$ptBookingList = [v2_t('Available days picked on a calendar'), v2_t('Time slots with configurable capacity'), v2_t('Detailed booking: participants, options, add-ons'), v2_t('Group packages and prices by age group')];
$ptBookingSteps = [v2_t('Time'), v2_t('Ticket'), v2_t('Extras'), v2_t('Name'), v2_t('Payment'), v2_t('Done')];
$ptDay = [
    ['clock', '08:40', v2_t('You open the day'), v2_t('You see how many tickets are sold for today and how many people are expected in each time slot.')],
    ['shopping-cart-simple', '09:15', v2_t('Online sales'), v2_t('People buy from your activity page or from the widget on your own website. The ticket goes out by email, with a QR code.')],
    ['printer', '10:30', v2_t('Sales at the ticket office'), v2_t('At the entrance, the POS issues the ticket on the spot: cart, cash or card, receipt printed on the thermal printer.')],
    ['scan', '11:00', v2_t('Scanning at the entrance'), v2_t('With a phone or a tablet. The ticket is valid, was already scanned or is not recognised: you see it at once, with sound and vibration.')],
    ['door-open', '18:00', v2_t('You close the register'), v2_t('The register statement shows how much cash you hand over and how much was taken by card, for each operator\'s shift.')],
    ['chart-line-up', '18:20', v2_t('You see the result'), v2_t('The day\'s report adds up online and ticket office, by activity, by time slot and by ticket type.')],
];
$ptPosChecks = [
    v2_t('A cart with your ticket types, packages and extras'),
    v2_t('Cash or card payment, on each operator\'s shift'),
    v2_t('A receipt printed automatically after each order, if you want'),
    v2_t('Register statement: how much cash you hand over, how much was taken by card'),
    v2_t('Register closing at the end of the shift, with the total of the orders'),
    v2_t('Invoices for companies, straight from the order'),
    v2_t('Extra services and rentals, alongside admission tickets'),
    v2_t('Online + on site, in the same system'),
];
$ptPosItems = [[v2_t('Adult admission'), 45], [v2_t('Child admission'), 25], [v2_t('Guided tour'), 60], [v2_t('Family (2+2)'), 120], [v2_t('Audio guide'), 15], [v2_t('Workshop'), 80]];
$ptScanChecks = [
    v2_t('Three clear answers: access approved, already scanned, invalid ticket'),
    v2_t('QR scanning with anti-fraud validation and double-entry prevention'),
    v2_t('Works without a stable connection: you scan offline, it syncs later'),
    v2_t('Code typed by hand, when the ticket is creased or the screen is cracked'),
    v2_t('Access gates and people assigned to each gate'),
    v2_t('Guest list and check-in without a printed ticket'),
    v2_t('The screen stays on while you scan, and the app runs on tablets too'),
    v2_t('Shift reports: how many came in, when, through which gate'),
    v2_t('Sales and traffic in real time, wherever you are'),
];
$ptHardware = [
    ['scan', v2_t('A phone or a tablet'), v2_t('Android or iPhone, for scanning at the entrance and for selling on the spot.')],
    ['printer', v2_t('A thermal printer'), v2_t('58 or 80 mm, for the receipt. It prints from the browser, with no special drivers.')],
    ['squares-four', v2_t('A computer for the ticket office'), v2_t('Any laptop or desktop with a modern browser. The POS runs in the browser.')],
    ['code', v2_t('Your website, if you have one'), v2_t('You put the sales widget on your page and sell straight from there, with the same tickets.')],
    ['file-text', v2_t('Documents and invoices'), v2_t('Invoices for companies, the account documents and the reports stay in the panel.')],
    ['headset', v2_t('People who answer'), v2_t('Support by email and phone, Monday to Friday, 09:00 to 18:00, plus tickets from the panel.')],
];
$ptStack = [
    ['01', v2_t('SEO'), v2_t('Pages that can bring organic traffic.'), v2_t('A venue page, pages for activities, categories, cities and intents such as “things to do with kids”, “weekend”, “indoor”, “under {amount}”.', ['amount' => v2_money(50)]), 'is-seo'],
    ['02', v2_t('Checkout'), v2_t('Fast, clear, modern buying.'), v2_t('Card (including Apple Pay and Google Pay), culture card where accepted, different ticket holders, automatic account, fees shown separately and commercial options.'), 'is-checkout'],
    ['03', v2_t('QR'), v2_t('Digital tickets and fast check-in.'), v2_t('Every ticket has a unique code, a status and a holder, and can be scanned at the entrance for clear access control.'), 'is-qr'],
    ['04', v2_t('Dashboard'), v2_t('Orders, customers, scans and reports.'), v2_t('You see sales, tickets issued, participants, availability, statuses and how your activities perform.'), 'is-dash'],
    ['05', v2_t('Growth'), v2_t('Promotions, vouchers, gift cards.'), v2_t('You can run promo codes, seasonal campaigns, gift cards, bonus points and offers for specific audiences.'), 'is-growth'],
    ['06', v2_t('Trust'), v2_t('A better experience for customers.'), v2_t('The customer sees clearly what they are buying, where they are going, how they get in, what the ticket includes and what happens after payment.'), 'is-trust'],
];
$ptBenefits = [
    ['plus', v2_t('Unlimited activities'), v2_t('Add as many activities as you like, of any type and in any form. No limit, no start-up costs.')],
    ['list', v2_t('Advanced management'), v2_t('Capacities, slots, price variants, availability, add-ons: you control every detail of every activity.')],
    ['tag', v2_t('Discount codes'), v2_t('Create promo codes and discount campaigns, with your own rules, to lift your sales whenever you want.')],
    ['users-three', v2_t('Group packages'), v2_t('Sell packages for groups, families, classes or corporate teams, with dedicated prices and capacities.')],
    ['target', v2_t('Recommendation system'), v2_t('Our own engine shows your activities to the best-matched buyers, from a base of over {count} customers.', ['count' => $ptOverThousands($ptCustomers)])],
    ['lock-simple', v2_t('Secure tickets'), v2_t('QR validation, verification and anti-fraud protection: technology tested in production on Tixello.')],
];
$ptSmall = [
    ['ticket', v2_t('A dedicated page per activity'), v2_t('An SEO-friendly page, a link to share and schema markup for Google.')],
    ['clock', v2_t('Waiting lists'), v2_t('Slot full? The customer joins the waiting list and is told if places open up.')],
    ['qr-code', v2_t('Check-in & access control'), v2_t('QR validation with double-entry prevention, ideal for slots with fixed capacity.')],
    ['map-pin', v2_t('Multi-venue'), v2_t('Manage several venues or sites from a single account.')],
    ['user-circle', v2_t('Roles & team'), v2_t('Add colleagues with permissions (cashier, scanning, manager), without full access.')],
    ['heart', v2_t('Your own branding'), v2_t('Logo, colours and look on your pages, so they look like your brand.')],
    ['star', v2_t('Reviews & ratings'), v2_t('Customers leave reviews that lift conversion for the next buyers.')],
    ['file-text', v2_t('Export & reports'), v2_t('Export orders, participants and takings for reporting and accounting.')],
];
$ptSeoUrls = [
    ['/lisbon/with-kids', v2_t('city + intent')],
    ['/escape-rooms', v2_t('category')],
    ['/venue/mystery-rooms', v2_t('venue page')],
    ['/activity/room-13', v2_t('activity page')],
];
$ptAnatomy = [
    [v2_t('Clear title + description'), v2_t('What it is, where it is, who it is for.')],
    [v2_t('Structured data'), v2_t('Breadcrumbs, FAQ, local entity, activity.')],
    [v2_t('Practical questions'), v2_t('Opening hours, access, age, duration, rules, parking.')],
    [v2_t('Internal linking'), v2_t('Cities, categories, similar activities, guides.')],
];
$ptReach = [
    ['magnifying-glass', v2_t('Your page is built to be found'), v2_t('Every activity and every venue has its own page, optimised for search, with opening hours, prices and availability.')],
    ['map-pin', v2_t('You show up in the city and in the category'), v2_t('You are on the city pages, in the categories and in the editorial guides, next to activities the same people are looking for.')],
    ['gift', v2_t('Gift cards and points'), v2_t('Gift cards and bonus points bring people back, without you building the loyalty programme.')],
    ['star', v2_t('Reviews and recommendations'), v2_t('Customer reviews and automatic recommendations send new people to the right activities.')],
];
$ptAnalytics = [
    v2_t('See exactly which activity, slot and day sell best'),
    v2_t('Understand where buyers come from and which channel brings profit'),
    v2_t('Tune prices and capacity to real demand'),
    v2_t('Follow conversion from visit to sale, in real time'),
    v2_t('Spot the empty slots and fill them with targeted promotions'),
    v2_t('Decide on data, not on guesses'),
];
$ptPayments = [['credit-card', v2_t('Bank card')], ['phone', 'Apple Pay'], ['phone', 'Google Pay'], ['ticket', v2_t('Culture cards')], ['lock-simple', 'Stripe']];
$ptFiscal = [
    ['file-text', v2_t('ANAF documents'), v2_t('Automatic generation of the documents ANAF requires.')],
    ['receipt', v2_t('Tax invoices'), v2_t('Issue tax invoices to customers straight from the platform.')],
    ['check-circle', v2_t('Accounting RO'), v2_t('Integration with accounting systems in Romania.')],
    ['coins', v2_t('Clear payouts'), v2_t('Payouts at regular intervals or on request, with transparent records.')],
];
$ptDocs = [['receipt', v2_t('Tax invoice'), v2_t('series BO · customer')], ['file-text', v2_t('ANAF document'), v2_t('automatic reporting')], ['check-circle', v2_t('Accounting entry'), v2_t('accounting sync RO')]];
$ptSteps = [
    ['user-circle', v2_t('Create your account'), v2_t('Sign up in a few minutes. No start-up fees, no subscription, no card at signup.'), v2_t('≈ 5 minutes')],
    ['plus', v2_t('Add your activities'), v2_t('As many as you like, of any type. Set time slots, days, capacities, price variants and group packages.'), v2_t('unlimited activities')],
    ['arrow-right', v2_t('Go live'), v2_t('Publish and you are on the market, with pages ready to share and tracking connected. You go straight into a base of {count}+ customers.', ['count' => $ptOverThousands($ptCustomers)]), v2_t('go live in 1 day at most')],
    ['coins', v2_t('Sell & get paid'), v2_t('Online and on site, in the same system. viaqui.com collects from the customer and pays you out at regular intervals or on request.'), v2_t('your price, in full')],
];
$ptOps = [
    [v2_t('Onboarding'), v2_t('Venue details, activities, tickets, policies.')],
    [v2_t('Publishing'), v2_t('SEO pages and activities available online.')],
    [v2_t('Selling'), v2_t('Checkout, payments, commissions, QR tickets.')],
    [v2_t('Scanning'), v2_t('Fast validation at the entrance, clear statuses.')],
    [v2_t('Growing'), v2_t('Reports, reviews, promotions, campaigns.')],
];
$ptTixello = [[v2_t('Events & activities'), v2_thousands($ptEvents)], [v2_t('Customers in the base'), v2_thousands($ptCustomers)], [v2_t('Tickets sold'), v2_thousands($ptTickets)], [v2_t('Sales generated'), '€' . v2_thousands($ptRevenue)], [v2_t('Offline scanning'), v2_t('Yes, with sync')]];
$ptFaqGroups = [
    [v2_t('Money & payments'), [
        [v2_t('How much is the commission?'), v2_t('The commission is 2%*, and it is not taken out of your price: it is added on top, in the final price. You set your price and receive it in full at payout. *The 2% applies to exclusive sales through viaqui.com. If you also sell your tickets elsewhere, the commission is 4%: 2% included in the price and 2% added. There is no monthly subscription and no setup fee.')],
        [v2_t('How and when do I get my money?'), v2_t('viaqui.com collects the payment from the customer and pays you out at regular intervals, or on request, whenever you want your money settled. In the panel you see the available balance, what is being processed and how much you have received so far, for each activity, and the documents and invoices stay in your account.')],
        [v2_t('Which payment methods are accepted?'), v2_t('Bank card (Visa, Mastercard, Maestro), Apple Pay and Google Pay, processed securely through Stripe, plus culture cards (Edenred, Sodexo, Up România) where accepted.')],
        [v2_t('How does it lower my ad costs?'), v2_t('The platform integrates with all tracking pixels and with Facebook CAPI, sending 100% of conversion events without them being blocked by ad blockers or iOS. The result: the cost of your ads on Facebook, Instagram, TikTok and Google drops by up to 60%.')],
        [v2_t('Does it help with the tax side?'), v2_t('Yes. Automatic generation of ANAF documents, tax invoices issued to customers and integration with accounting systems in Romania.')],
        [v2_t('Can I create promotions or discount codes?'), v2_t('Yes, the platform can include promo codes, seasonal campaigns, vouchers, bonus points and gift cards, depending on the setup.')],
    ]],
    [v2_t('Product & operations'), [
        [v2_t('What kinds of venues can use the platform?'), v2_t('The platform suits escape rooms, museums, exhibitions, amusement parks, adventure parks, caves, nature reserves, workshops, guided tours, educational farms and other experiences that sell tickets or bookings.')],
        [v2_t('Can I sell activities with slots and by day?'), v2_t('Yes. The customer picks the day from the calendar, the time slot, the number of participants and the options. You control the capacity of each slot and take detailed bookings.')],
        [v2_t('Can I have several activities at the same venue?'), v2_t('Yes. A venue can have a main page and several pages for activities, rooms, tours, packages or access types.')],
        [v2_t('Can I sell on site too, not only online?'), v2_t('Yes. Besides the online dashboard, you have a panel for on-site sales: the POS issues tickets on the spot (admission, extra services and rentals), keeps the cart, takes cash or card, prints the receipt and gives you the register statement and the register closing at the end of the shift. Online and ticket office sales end up in the same report.')],
        [v2_t('How are tickets validated at the entrance?'), v2_t('Every ticket is issued with a unique QR code. Staff scan it with the scanning app, on a phone or a tablet; if a code cannot be read, they can type it. The app shows at once whether the ticket is valid, was already scanned or is not recognised, with vibration and sound, and it works offline too, syncing later.')],
        [v2_t('What happens after I receive an order?'), v2_t('The order appears in the dashboard, the tickets are issued automatically, the customer receives the confirmation, and you can see the participants and validate the tickets at the entrance.')],
        [v2_t('Does it help with SEO?'), v2_t('Yes. The platform is designed for indexable pages: venue, activities, cities, categories and intent pages such as activities for kids, weekend, indoor or outdoor.')],
    ]],
    [v2_t('Getting started & support'), [
        [v2_t('How long does it take to start?'), v2_t('Onboarding takes about 5 minutes, with no start-up costs. You go live within one day at most, depending on how many activities you add. If you send us the venue details so that we prepare your account, activities and ticket types for you, the pace depends on how quickly we receive the opening hours, prices and photos.')],
        [v2_t('What hardware do I need?'), v2_t('A phone or a tablet for scanning and, if you want a printed receipt, a 58 or 80 mm thermal printer. The receipt prints straight from the browser, with no special drivers. The POS stands in for the till, inside the app.')],
        [v2_t('Who answers if something goes wrong?'), v2_t('You have support by email and phone, Monday to Friday, between 09:00 and 18:00, plus support tickets straight from the panel. For the day of a big event, we agree in advance who is available.')],
    ]],
];
$ptFaqs = array_merge(...array_map(fn ($g) => $g[1], $ptFaqGroups));
$ptRoles = [v2_t('Owner'), v2_t('Venue manager'), v2_t('Marketing'), v2_t('Operations / ticket office'), v2_t('Other')];
$ptCounts = [v2_t('1 activity'), v2_t('2-5 activities'), v2_t('6-15 activities'), v2_t('15+ activities')];
// partner stories: the quotes are shown to everyone, the stand-ins tagged as examples; the film only when it's real
$ptPreview = !empty($_GET['preview']);
$ptStories = v2_partner_testimonials();
$ptVideo = (empty($ptStories['video']['demo']) || $ptPreview) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($ptStories['video']['youtube'] ?? '')) ? $ptStories['video'] : null;
$ptQuotes = array_values(array_filter($ptStories['quotes'] ?? [], fn ($q) => !empty($q['quote'])));
$ptWall = array_values(array_filter($ptQuotes, fn ($q) => empty($q['pull'])));
$ptPull = array_values(array_filter($ptQuotes, fn ($q) => !empty($q['pull'])))[0] ?? null;
$ptWallMoves = count($ptWall) >= 5; // fewer quotes sit still in a grid
$ptInitials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($parts, 0, 2))));
};
$ptDemoTag = static fn (array $item): string => !empty($item['demo']) ? '<span class="pt-demo-tag">' . v2_te('Example') . '</span>' : '';
// said once, above the wall, when any of the quotes on screen is a stand-in
$ptDemoNote = array_filter($ptQuotes, fn ($q) => !empty($q['demo'])) ? v2_t('The stories below are examples written by us, to show what this section looks like. We replace them with real stories as the first venues get here.') : '';

$ptChapters = [['pentru-cine', v2_t('Who it\'s for')], ['booking', v2_t('Booking')], ['o-zi', v2_t('Operations')], ['ce-primesti', v2_t('What you get')], ['vizibilitate', v2_t('Visibility')], ['bani', v2_t('Money')], ['cum', v2_t('How to start')], ['intrebari', v2_t('Questions')]];
if ($ptVideo || $ptWall) {
    array_splice($ptChapters, 3, 0, [['povesti', v2_t('Stories')]]);
}

// A small QR-like pattern for the mock-ups: finder squares plus a fixed pseudo-random fill (not a real code), drawn as
// one path of horizontal runs so five of them stay light.
$ptQr = static function (string $seed, int $n = 21): string {
    $d = '';
    $finder = static fn (int $x, int $y): bool => ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
    for ($y = 0; $y < $n; $y++) {
        $run = 0;
        for ($x = 0; $x <= $n; $x++) {
            $on = false;
            if ($x < $n && $finder($x, $y)) {
                $fx = $x >= $n - 7 ? $x - ($n - 7) : $x;
                $fy = $y >= $n - 7 ? $y - ($n - 7) : $y;
                $on = ($fx === 0 || $fx === 6 || $fy === 0 || $fy === 6) || ($fx >= 2 && $fx <= 4 && $fy >= 2 && $fy <= 4);
            } elseif ($x < $n) {
                $on = (crc32($seed . ':' . $x . ':' . $y) & 3) === 0;
            }
            if ($on) {
                $run++;
            } elseif ($run) {
                $d .= 'M' . ($x - $run) . ' ' . $y . 'h' . $run . 'v1h-' . $run . 'z';
                $run = 0;
            }
        }
    }
    return '<svg viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
};
/** A row in the mock operator sidebar. */
$ptNav = static function (string $icon, string $label, bool $on = false): string {
    return '<span class="pt-ui-nav' . ($on ? ' is-on' : '') . '">' . v2_ic($icon) . '<b>' . v2_e($label) . '</b></span>';
};
/** A figure in the mock dashboard. */
$ptKpi = static function (string $label, string $value, string $delta = ''): string {
    return '<div class="pt-ui-kpi"><p>' . v2_e($label) . '</p><b>' . v2_e($value) . '</b>'
        . ($delta ? '<small>' . v2_ic('trend-up') . v2_e($delta) . '</small>' : '') . '</div>';
};
/** A section heading: kicker, title, optional lead. */
$ptHead = static function (string $id, string $kicker, string $title, string $lead = '', string $cls = ''): string {
    return '<div class="pt-head ' . $cls . '"><p class="pt-k">' . v2_e($kicker) . '</p><h2 id="' . $id . '">' . $title . '</h2>'
        . ($lead !== '' ? '<p class="pt-sub">' . $lead . '</p>' : '') . '</div>';
};

$pageTitleRaw = v2_t('{site} partners: sell tickets to your activities, 2%* commission that doesn\'t touch your price', ['site' => SITE_NAME]);
$pageDescription = v2_t('Everything a venue gets on viaqui.com: booking by time slot, operator panel, ticket office with receipts, offline scanning app, SEO, analytics and tracking, payouts and tax documents. 2%* commission that doesn\'t touch your price, and no subscription.');
$canonicalUrl = SITE_URL . '/partners';
$ogImage = SITE_URL . '/assets/v2/img/hero-1440.webp';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => v2_t('viaqui.com for partners: ticketing & booking for activities'),
        'serviceType' => v2_t('Platform for selling tickets online and on site for activities and venues'),
        'description' => v2_t('Booking by time slot, operator panel, POS for the ticket office, offline scanning app, SEO pages, analytics and server-side tracking, regular payouts and tax documents. 2% commission that doesn\'t touch your price.'),
        'provider' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL . '/'],
        'areaServed' => ['@type' => 'Place', 'name' => 'Europe'],
        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => v2_t('Leisure venues, museums, escape rooms, parks, workshops, tour and experience operators')],
        'offers' => ['@type' => 'Offer', 'priceCurrency' => SITE_CURRENCY, 'price' => '0', 'description' => v2_t('{amount} start-up cost. 2%* commission on every ticket sold, without touching your price.', ['amount' => v2_money(0)])],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $ptFaqs),
    ],
];

$v2Styles = ['partners.css'];
$v2Scripts = ['partners.js', 'for-venues.js'];
$v2HeaderOverlay = true;
$v2ClientData = [
    'profiles' => $ptProfiles,
    'aliases' => V2_PARTNER_ALIASES,
    'supportEmail' => SUPPORT_EMAIL,
    'demoName' => 'Andrei Popescu',
    'demoGift' => v2_t('Happy birthday! Have a great time!'),
    'libs' => [v2_asset('vendor/gsap-3.15.0.min.js'), v2_asset('vendor/ScrollTrigger-3.15.0.min.js'), v2_asset('vendor/lenis-1.3.26.min.js')],
];
// head.php strips utm_* from the address bar after load; the funnel pings and the demo lead read them from here
$v2HeadExtra = '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO: the stage ===================== -->
  <section class="pt-hero" id="pt-hero" aria-labelledby="pt-h">
    <div class="pt-bg" aria-hidden="true">
      <svg class="deco-arches" viewBox="0 0 400 400" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    </div>
    <div class="pt-in">
      <div class="pt-copy" id="pt-copy">
        <p class="pt-hello pt-rv" id="pt-hello" style="--d:0ms" hidden></p>
        <p class="pt-chip pt-rv" style="--d:40ms"><span class="pt-live" aria-hidden="true"></span><span id="pt-chip-t"><?= v2_te('Ticketing & booking for activities') ?></span></p>
        <h1 class="pt-h" id="pt-h">
          <span class="pt-line" style="--d:120ms"><span class="pt-line-in" id="pt-h1a"><?= v2_te('Sell tickets') ?></span></span>
          <span class="pt-line is-soft" style="--d:220ms"><span class="pt-line-in" id="pt-h1b"><?= v2_te('to your activities.') ?></span></span>
          <span class="pt-line is-mark" style="--d:320ms"><span class="pt-line-in"><span id="pt-h1c"><?= v2_te('Your price stays yours.') ?></span></span></span>
        </h1>
        <p class="pt-lead pt-rv" id="pt-sub" style="--d:460ms"><?= v2_t('Booking by time slot and calendar, an operator panel, a ticket office with receipts, an offline scanning app, analytics and tracking that lowers your ad costs. The commission of {accent} doesn\'t touch your price: you keep the price you set.', ['accent' => $ptAccent]) ?></p>
        <div class="pt-cta pt-rv" style="--d:560ms">
          <a class="btn btn-light pt-go" href="/list-your-venue" data-signup data-track-cta="parteneri_hero_signup"><span id="pt-cta-t"><?= v2_te('Start for free') ?></span><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#demo" data-track-cta="parteneri_hero_demo"><?= v2_ic('calendar-blank') ?><?= v2_te('Request a demo') ?></a>
        </div>
        <ul class="pt-ticks pt-rv" style="--d:660ms">
          <li><?= v2_ic('check') ?><?= v2_te('{amount} start-up cost', ['amount' => v2_money(0)]) ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('No subscription') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Onboarding in ≈5 minutes') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Live within one day at most') ?></li>
        </ul>
      </div>

      <div class="pt-stage" id="pt-stage" aria-hidden="true">
        <div class="pt-scene" id="pt-scene">
          <!-- dashboard -->
          <div class="pt-dash pt-part" style="--d:380ms">
            <div class="pt-win"><i></i><i></i><i></i><span><?= v2_te('viaqui.com · Operator panel') ?></span></div>
            <div class="pt-dash-body">
              <div class="pt-dash-nav"><b class="is-on"></b><b></b><b></b><b></b><b></b></div>
              <div class="pt-dash-main">
                <div class="pt-kpis">
                  <div><small><?= v2_te('Sales today') ?></small><b>€<span id="pt-kpi-sales">12,480</span></b><em><?= v2_ic('trend-up') ?>18%</em></div>
                  <div><small><?= v2_te('Tickets issued') ?></small><b id="pt-kpi-tickets">286</b><em><?= v2_ic('trend-up') ?>42</em></div>
                  <div><small><?= v2_te('Slot occupancy') ?></small><b>84%</b><em class="is-soft"><?= v2_te('today') ?></em></div>
                </div>
                <div class="pt-chart">
                  <svg viewBox="0 0 300 92" preserveAspectRatio="none" focusable="false">
                    <defs><linearGradient id="pt-area-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2BB673" stop-opacity=".35"/><stop offset="1" stop-color="#2BB673" stop-opacity="0"/></linearGradient></defs>
                    <path class="pt-area" d="M0 78 C 30 70, 45 60, 70 62 S 115 40, 140 46 S 185 22, 210 30 S 255 12, 300 8 L300 92 L0 92 Z"/>
                    <path class="pt-curve" pathLength="1" d="M0 78 C 30 70, 45 60, 70 62 S 115 40, 140 46 S 185 22, 210 30 S 255 12, 300 8"/>
                  </svg>
                  <span class="pt-chart-tag"><?= v2_te('Last 7 days') ?></span>
                </div>
                <ul class="pt-orders" id="pt-orders">
                  <li><i class="is-green"></i><b><?= v2_te('Escape room · Room 2') ?></b><small><?= v2_te('4 tickets · 18:00') ?></small><em><?= v2_money(180) ?></em></li>
                  <li><i class="is-yellow"></i><b><?= v2_te('Museum · General admission') ?></b><small><?= v2_te('2 tickets · 11:30') ?></small><em><?= v2_money(60) ?></em></li>
                  <li><i class="is-red"></i><b><?= v2_te('Guided tour · Old town') ?></b><small><?= v2_te('6 tickets · 16:00') ?></small><em><?= v2_money(210) ?></em></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- ticket office -->
          <div class="pt-pos pt-part" style="--d:620ms">
            <div class="pt-printer"><span><?= v2_te('Ticket office 1') ?></span><i></i></div>
            <div class="pt-feed"><div class="pt-receipt" id="pt-receipt">
              <p class="pt-r-brand">viaqui.com</p>
              <p class="pt-r-meta"><?= v2_te('Receipt no. 0147 · 14:32') ?></p>
              <ul><li><span><?= v2_te('2 × Adult') ?></span><b>90.00</b></li><li><span><?= v2_te('1 × Child') ?></span><b>25.00</b></li></ul>
              <p class="pt-r-total"><span><?= v2_te('Total') ?></span><b><?= v2_money(115) ?></b></p>
              <p class="pt-r-pay"><?= v2_te('Card · approved') ?></p>
              <span class="pt-r-qr"><?= $ptQr('receipt', 21) ?></span>
            </div></div>
          </div>

          <!-- scanning phone -->
          <div class="pt-phone pt-part" style="--d:820ms">
            <div class="pt-phone-in">
              <p class="pt-ph-h"><?= v2_te('Scanning · Entrance 1') ?></p>
              <div class="pt-view"><span class="pt-qr"><?= $ptQr('ticket', 21) ?></span><i class="pt-scanline"></i><i class="pt-corners"></i></div>
              <p class="pt-result" id="pt-result"><?= v2_ic('check-circle') ?><span id="pt-result-t"><?= v2_te('Valid ticket') ?></span></p>
              <p class="pt-count"><?= v2_t('{count} / 150 have entered', ['count' => '<b id="pt-in">128</b>']) ?></p>
            </div>
          </div>

          <!-- live events -->
          <p class="pt-toast is-a" id="pt-toast-a"><span class="pt-t-ic"><?= v2_ic('ticket') ?></span><span><b><?= v2_te('New booking') ?></b><small><?= v2_te('Slot 18:00 · 4 people') ?></small></span></p>
          <p class="pt-toast is-b" id="pt-toast-b"><span class="pt-t-ic is-yellow"><?= v2_ic('coins') ?></span><span><b><?= v2_te('Payout requested') ?></b><small><?= v2_te('{amount} · processing', ['amount' => v2_money(4320)]) ?></small></span></p>
          <span class="pt-mock"><?= v2_te('Illustrative mock-up') ?></span>
        </div>
      </div>
    </div>
    <a class="pt-cue" href="#cifre"><span><?= v2_te('Scroll') ?></span><i aria-hidden="true"></i></a>
    <svg class="pt-hero-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== NUMBERS ===================== -->
  <section class="pt-stats" id="cifre" aria-labelledby="pt-stats-h">
    <div class="wrap">
      <p class="pt-stats-cap" id="pt-stats-h"><?= v2_te('Real results in the Tixello ecosystem, which viaqui.com is built on') ?></p>
      <ul class="pt-stats-grid">
        <?php foreach ($ptStats as [$statValue, $statLabel, $statPrefix]): ?>
        <li><b><span class="sr"><?= v2_e($statPrefix) . v2_thousands($statValue) ?></span><span aria-hidden="true"><?= v2_e($statPrefix) ?><span data-count="<?= $statValue ?>"><?= v2_thousands($statValue) ?></span></span></b><span class="pt-stat-l"><?= v2_e($statLabel) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if ($ptCategories): ?>
    <!-- live categories (the links are in "Who it's for") -->
    <div class="pt-marquee" aria-hidden="true">
      <div class="pt-marquee-track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
        <div class="pt-marquee-set"><?php foreach ($ptCategories as $cat): ?><span><?= v2_e($cat['name']) ?></span><?php endforeach; ?></div>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ===================== CHAPTERS ===================== -->
  <nav class="pt-nav" id="pt-nav" aria-label="<?= v2_te('Page chapters') ?>">
    <div class="pt-nav-in">
      <ul class="pt-nav-list">
        <?php foreach ($ptChapters as [$chapterId, $chapterLabel]): ?>
        <li><a href="#<?= $chapterId ?>" data-chapter-link="<?= $chapterId ?>"><?= v2_e($chapterLabel) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="pt-nav-cta">
        <a class="pt-nav-demo" href="#demo" data-track-cta="parteneri_nav_demo"><?= v2_te('Request a demo') ?></a>
        <a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="parteneri_nav_signup"><?= v2_te('Start for free') ?><?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </nav>

  <!-- ===================== WHO ===================== -->
  <section class="pt-sec pt-who" id="pentru-cine" data-chapter="pentru-cine" aria-labelledby="pt-who-h">
    <div class="wrap">
      <div class="pt-split">
        <?= $ptHead('pt-who-h', v2_t('Who it\'s for'), v2_te('You don\'t just sell tickets. You sell an experience that has to be discovered.'), v2_te('The platform is built for different activities, with different access models: time slots, simple tickets, packages, guided tours, day access, groups or private events.')) ?>
        <ul class="pt-who-grid" data-reveal>
          <?php foreach ($ptWho as [$whoIcon, $whoTitle, $whoText]): ?>
          <li class="pt-who-card"><span class="pt-ic"><?= v2_ic($whoIcon) ?></span><h3><?= v2_e($whoTitle) ?></h3><p><?= v2_e($whoText) ?></p></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php if ($ptCategories): ?>
      <div class="pt-cats">
        <h3 class="pt-cats-h"><?= v2_te('Categories available on viaqui.com') ?></h3>
        <ul class="pt-cat-grid" id="grid-categorii" data-reveal>
          <?php foreach ($ptCategories as $cat): ?>
          <li><a class="pt-cat" href="<?= v2_e($cat['href'] ?? '/' . ($cat['slug'] ?? '')) ?>" data-cat-slug="<?= v2_e($cat['slug'] ?? '') ?>">
            <?php if (!empty($cat['image'])): ?>
            <img src="<?= v2_e($cat['thumb'] ?: $cat['image']) ?>"<?= !empty($cat['srcset']) ? ' srcset="' . v2_e($cat['srcset']) . '" sizes="(min-width:1024px) 16vw, (min-width:640px) 33vw, 50vw"' : '' ?> alt="" width="320" height="200" loading="lazy" decoding="async">
            <?php else: ?>
            <span class="pt-cat-ph"><?= v2_ic('ticket') ?></span>
            <?php endif; ?>
            <span class="pt-cat-body"><b><?= v2_e($cat['name']) ?></b><?php if (!empty($cat['desc'])): ?><small><?= v2_e($cat['desc']) ?></small><?php endif; ?></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== PROBLEM → FIX ===================== -->
  <section class="pt-sec pt-problem" id="problema" data-chapter="pentru-cine" aria-labelledby="pt-problem-h">
    <div class="wrap">
      <?= $ptHead('pt-problem-h', v2_t('Today\'s reality'), v2_te('You sell activities, but your tools hold you back.'), v2_te('High commissions taken out of your margin. Rigid booking that cannot handle slots or days. Tracking cut short by ad blockers and iOS, which inflates your ad costs. And no real help in finding new customers.'), 'is-center') ?>
      <ul class="pt-flip-grid" id="pt-flips">
        <?php foreach ($ptProblems as $pi => [$probIcon, $probTitle, $probText, $fixTitle, $fixText]): ?>
        <li class="pt-flip" style="--i:<?= $pi ?>">
          <div class="pt-flip-face is-pain">
            <span class="pt-ic is-red"><?= v2_ic($probIcon) ?></span>
            <h3><span><?= v2_e($probTitle) ?></span></h3>
            <p><?= v2_e($probText) ?></p>
          </div>
          <div class="pt-flip-face is-fix">
            <span class="pt-ic is-green"><?= v2_ic('check') ?></span>
            <p class="pt-fix-k"><?= v2_te('With viaqui.com') ?></p>
            <h3><?= v2_e($fixTitle) ?></h3>
            <p><?= v2_e($fixText) ?></p>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pt-center"><a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="parteneri_problem_solve"><?= v2_te('Solve them all with viaqui.com') ?><?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- ===================== BOOKING (scene) ===================== -->
  <section class="pt-scene-sec pt-booking" id="booking" data-chapter="booking" aria-labelledby="pt-booking-h">
    <div class="pt-scene-in wrap">
      <div class="pt-booking-copy">
        <p class="pt-k is-dark"><?= v2_te('Booking designed for activities') ?></p>
        <h2 id="pt-booking-h"><?= v2_te('Time slots. Days on a calendar. Detailed booking.') ?></h2>
        <p class="pt-sub is-dark"><?= v2_te('viaqui.com doesn\'t just sell “a ticket”. The customer picks the day from the calendar, the time slot, the number of participants and the options, exactly the way an escape room, a guided tour or a workshop works. You control the capacity of each slot.') ?></p>
        <ul class="pt-list is-dark">
          <?php foreach ($ptBookingList as $bookingItem): ?><li><?= v2_ic('check') ?><?= v2_e($bookingItem) ?></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-light pt-mt" href="/list-your-venue" data-signup data-track-cta="parteneri_booking_slots"><?= v2_te('I want booking by slots') ?><?= v2_ic('arrow-right') ?></a>
      </div>

      <!-- animated demo; the text beside it says the same thing, so screen readers skip it -->
      <div class="pt-bk" id="pt-bk" aria-hidden="true">
        <ol class="pt-bk-rail"><?php foreach ($ptBookingSteps as $si => $stepName): ?><li data-rail="<?= $si ?>"<?= $si === 5 ? ' class="is-on"' : ' class="is-past"' ?>><i><?= $si + 1 ?></i><span><?= v2_e($stepName) ?></span></li><?php endforeach; ?></ol>
        <div class="pt-bk-card">
          <div class="pt-bk-head"><b id="pt-bk-title"><?= v2_te('Done!') ?></b><span id="pt-bk-count">6/6</span></div>
          <div class="pt-bk-bar"><i id="pt-bk-progress" style="width:100%"></i></div>
          <div class="pt-bk-body">
            <div class="pt-bk-step" data-step="0" hidden>
              <small><?= v2_te('Saturday, 19 October') ?></small>
              <div class="pt-bk-days"><?php foreach ([[v2_t('Thu'), 17], [v2_t('Fri'), 18], [v2_t('Sat'), 19], [v2_t('Sun'), 20], [v2_t('Mon'), 21]] as $di => [$dayName, $dayNum]): ?><span class="<?= $di === 2 ? 'is-on' : ($di === 4 ? 'is-gone' : '') ?>"><small><?= v2_e($dayName) ?></small><b><?= $dayNum ?></b></span><?php endforeach; ?></div>
              <small><?= v2_te('Available slots') ?></small>
              <div class="pt-bk-slots"><?php foreach (['10:00', '12:00', '14:00', '16:00', '18:00', '20:00'] as $slotIndex => $slotTime): ?><span class="pt-bk-slot<?= $slotIndex === 1 ? ' is-gone' : '' ?>"><?= $slotTime ?><em><?= v2_e(v2_num([6, 0, 4, 2, 8, 5][$slotIndex], 'place', 'places')) ?></em></span><?php endforeach; ?></div>
            </div>
            <div class="pt-bk-step" data-step="1" hidden>
              <small><?= v2_te('Choose the ticket type') ?></small>
              <?php foreach ([[v2_t('Standard admission'), v2_t('1 person'), v2_money(80)], [v2_t('Admission + experience'), v2_t('1 person'), v2_money(120)], [v2_t('Family package'), v2_t('2 adults + 2 children'), v2_money(260)]] as [$tkName, $tkNote, $tkPrice]): ?>
              <div class="pt-bk-row pt-bk-tk"><div><b><?= v2_e($tkName) ?></b><small><?= v2_e($tkNote) ?></small></div><span><?= v2_e($tkPrice) ?><i class="pt-bk-radio"></i></span></div>
              <?php endforeach; ?>
            </div>
            <div class="pt-bk-step" data-step="2" hidden>
              <small><?= v2_te('Add extras & rentals') ?></small>
              <?php foreach ([['map-pin', v2_t('Equipment (rental)'), v2_money(35)], ['star', v2_t('Photo guide'), v2_money(25)], ['gift', v2_t('Snack pack'), v2_money(18)]] as [$exIcon, $exName, $exPrice]): ?>
              <div class="pt-bk-row pt-bk-extra"><div class="pt-bk-exname"><?= v2_ic($exIcon) ?><b><?= v2_e($exName) ?></b></div><span><?= v2_e($exPrice) ?><i class="pt-bk-plus"></i></span></div>
              <?php endforeach; ?>
            </div>
            <div class="pt-bk-step" data-step="3" hidden>
              <small><?= v2_te('Personalise the ticket') ?></small>
              <p class="pt-bk-lbl"><?= v2_te('Name on the ticket') ?></p>
              <div class="pt-bk-input"><span id="pt-bk-typed">Andrei Popescu</span><i class="pt-bk-caret"></i></div>
              <p class="pt-bk-lbl"><?= v2_te('Gift message (optional)') ?></p>
              <div class="pt-bk-textarea" id="pt-bk-gift"><?= v2_te('Happy birthday! Have a great time!') ?></div>
              <p class="pt-bk-ok"><?= v2_ic('check') ?><?= v2_te('Send the ticket by email & WhatsApp') ?></p>
            </div>
            <div class="pt-bk-step" data-step="4" hidden>
              <small><?= v2_te('Order summary') ?></small>
              <div class="pt-bk-sum"><p><span><?= v2_te('Admission + experience') ?></span><span><?= v2_money(120) ?></span></p><p><span><?= v2_te('Equipment (rental)') ?></span><span><?= v2_money(35) ?></span></p><p><span><?= v2_te('Photo guide') ?></span><span><?= v2_money(25) ?></span></p><p class="is-total"><span><?= v2_te('Estimated total') ?></span><span><?= v2_money(180) ?></span></p></div>
              <div class="pt-bk-pay"><span class="is-on">Stripe</span><span>Apple Pay</span><span>Google Pay</span><span><?= v2_te('Culture card') ?></span></div>
              <div class="pt-bk-bar is-pay"><i id="pt-bk-pay" style="width:100%"></i></div>
              <p class="pt-bk-pay-t" id="pt-bk-pay-t"><?= v2_te('Payment confirmed') ?></p>
            </div>
            <div class="pt-bk-step is-done" data-step="5">
              <span class="pt-bk-done"><?= v2_ic('check') ?></span>
              <b><?= v2_te('Order confirmed!') ?></b>
              <p><?= v2_te('Ticket MKT-19024 · 14:00') ?></p>
              <ul class="pt-bk-msgs">
                <?php foreach ([['envelope-simple', v2_t('The ticket was sent by email')], ['phone', v2_t('Confirmation sent on WhatsApp')], ['ticket', v2_t('Valid QR ticket: scan it at the entrance')], ['star', v2_t('Recommended for you: “Sunset photo tour”')]] as [$msgIcon, $msgText]): ?>
                <li class="pt-bk-msg is-on"><?= v2_ic($msgIcon) ?><?= v2_e($msgText) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          <p class="pt-bk-foot"><?= v2_te('viaqui.com booking demo · illustrative mock-up') ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== A DAY AT THE VENUE (scene) ===================== -->
  <section class="pt-day" id="o-zi" data-chapter="o-zi" aria-labelledby="pt-day-h">
    <div class="pt-day-sky" aria-hidden="true"><i class="pt-day-dusk"></i><i class="pt-day-sun"></i></div>
    <div class="pt-day-in">
      <div class="wrap pt-day-head">
        <?= $ptHead('pt-day-h', v2_t('A day at your venue'), v2_te('From the first sale to closing the register'), v2_te('The same tickets, the same reports, whether the person bought from home or at the ticket office.')) ?>
        <p class="pt-day-clock" aria-hidden="true"><?= v2_ic('clock') ?><b id="pt-day-time">08:40</b></p>
      </div>
      <div class="pt-day-viewport">
        <ol class="pt-day-track" id="pt-day-track">
          <?php foreach ($ptDay as $di => [$dayIcon, $dayTime, $dayTitle, $dayText]): ?>
          <li class="pt-day-card" data-time="<?= $dayTime ?>" style="--i:<?= $di ?>">
            <span class="pt-day-ic"><?= v2_ic($dayIcon) ?></span>
            <p class="pt-day-t"><?= $dayTime ?></p>
            <h3><?= v2_e($dayTitle) ?></h3>
            <p><?= v2_e($dayText) ?></p>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <!-- ===================== OPERATOR PANEL ===================== -->
  <section class="pt-sec pt-panel" id="panou" data-chapter="o-zi" aria-labelledby="pt-panel-h">
    <div class="wrap">
      <?= $ptHead('pt-panel-h', v2_t('The operator panel'), v2_t('Sell online and at the ticket office. <span class="pt-nl">From a single account.</span>'), v2_te('Every screen has one purpose and reads at a glance: the operator panel, the POS for selling on the spot, the scanning app on a phone or tablet and the receipt on a thermal printer. Everything sold, wherever it was sold, ends up in the same report. Pick a section and see what it looks like.'), 'is-center') ?>
      <div class="pt-tabs" role="tablist" aria-label="<?= v2_te('Sections of the panel') ?>" data-tabs>
        <?php foreach ([['panou', 'squares-four', v2_t('Panel')], ['vanzari', 'shopping-cart-simple', v2_t('Sales')], ['participanti', 'users-three', v2_t('Participants')], ['sold', 'wallet', v2_t('Balance')], ['marketing', 'megaphone', v2_t('Marketing')]] as $i => [$key, $icon, $label]): ?>
        <button class="pt-tab" type="button" role="tab" id="ptt-<?= $key ?>" aria-controls="ptp-<?= $key ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_ic($icon) ?><?= v2_e($label) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="pt-screen" id="pt-screen">
        <div class="pt-ui">
          <div class="pt-ui-top"><span class="pt-ui-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="pt-ui-url" id="pt-url">viaqui.com/organizator/panou</span></div>
          <div class="pt-ui-body">
            <div class="pt-ui-side" aria-hidden="true">
              <?= $ptNav('squares-four', v2_t('Panel'), true) ?>
              <?= $ptNav('calendar-blank', v2_t('Activities')) ?>
              <?= $ptNav('users-three', v2_t('Participants')) ?>
              <?= $ptNav('shopping-cart-simple', v2_t('Sales')) ?>
              <?= $ptNav('wallet', v2_t('Balance')) ?>
              <?= $ptNav('file-text', v2_t('Documents')) ?>
              <?= $ptNav('tag', v2_t('Promo codes')) ?>
              <?= $ptNav('code', v2_t('Widgets')) ?>
              <?= $ptNav('receipt', v2_t('Billing')) ?>
            </div>
            <div class="pt-ui-main">
              <div class="pt-p" id="ptp-panou" role="tabpanel" aria-labelledby="ptt-panou" data-url="viaqui.com/organizator/panou" tabindex="0">
                <p class="pt-ui-h"><?= v2_te('This month\'s figures') ?></p>
                <div class="pt-ui-kpis is-four">
                  <?= $ptKpi(v2_t('Revenue this month'), v2_money(18240), '+12%') ?>
                  <?= $ptKpi(v2_t('Tickets sold this month'), '412', '+8%') ?>
                  <?= $ptKpi(v2_t('Activities running'), '6') ?>
                  <?= $ptKpi(v2_t('Conversion rate'), '4.8%', v2_t('+0.6 pp')) ?>
                </div>
                <p class="pt-ui-h2"><?= v2_te('Ticket sales') ?></p>
                <div class="pt-ui-chart" aria-hidden="true">
                  <?php foreach ([32, 46, 38, 60, 52, 74, 66, 81, 58, 69, 77, 92] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?>
                </div>
                <div class="pt-ui-quick">
                  <span><?= v2_ic('plus') ?><?= v2_te('New activity') ?></span>
                  <span><?= v2_ic('tag') ?><?= v2_te('Promo code') ?></span>
                  <span><?= v2_ic('scan') ?><?= v2_te('Scanning at the entrance') ?></span>
                </div>
              </div>
              <div class="pt-p" id="ptp-vanzari" role="tabpanel" aria-labelledby="ptt-vanzari" data-url="viaqui.com/organizator/vanzari" tabindex="0" hidden>
                <p class="pt-ui-h"><?= v2_te('Sales') ?></p>
                <div class="pt-ui-chips"><span class="is-on"><?= v2_te('All channels') ?></span><span><?= v2_te('Online') ?></span><span><?= v2_te('Ticket office') ?></span><span><?= v2_te('This month') ?></span></div>
                <div class="pt-ui-scroll">
                  <table class="pt-ui-table">
                    <thead><tr><th><?= v2_te('Order') ?></th><th><?= v2_te('Activity') ?></th><th><?= v2_te('Channel') ?></th><th><?= v2_te('Total') ?></th><th><?= v2_te('Status') ?></th></tr></thead>
                    <tbody>
                      <?php foreach ([['BO-24817', v2_t('Guided tour · 11:00'), v2_t('Online'), v2_money(160), v2_t('Paid'), 'is-ok'], ['BO-24816', v2_t('Adult admission'), v2_t('Ticket office'), v2_money(45), v2_t('Cash'), 'is-info'], ['BO-24815', v2_t('Kids workshop · Saturday'), v2_t('Online'), v2_money(220), v2_t('Paid'), 'is-ok'], ['BO-24814', v2_t('Family admission'), v2_t('Ticket office'), v2_money(120), v2_t('Card'), 'is-info']] as [$no, $act, $chan, $total, $status, $tone]): ?>
                      <tr><td><b><?= $no ?></b></td><td><?= v2_e($act) ?></td><td><?= v2_e($chan) ?></td><td class="pt-right"><?= $total ?></td><td><span class="pt-pill <?= $tone ?>"><?= v2_e($status) ?></span></td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="pt-p" id="ptp-participanti" role="tabpanel" aria-labelledby="ptt-participanti" data-url="viaqui.com/organizator/participanti" tabindex="0" hidden>
                <p class="pt-ui-h"><?= v2_te('Participants') ?></p>
                <div class="pt-ui-search"><?= v2_ic('magnifying-glass') ?><span><?= v2_te('Search by name, email or ticket code') ?></span></div>
                <ul class="pt-ui-list">
                  <?php foreach ([['AM', 'Andrei M.', v2_t('Guided tour · 11:00'), v2_t('Entered 10:58'), 'is-ok'], ['IR', 'Ioana R.', v2_t('Kids workshop · 12:30'), v2_t('Expected'), ''], ['DP', 'Dan P.', v2_t('Adult admission'), v2_t('Entered 10:41'), 'is-ok'], ['MS', 'Maria S.', v2_t('Family admission · 4 people'), v2_t('Expected'), '']] as [$ini, $name, $act, $state, $tone]): ?>
                  <li><span class="pt-ui-av"><?= $ini ?></span><span class="pt-ui-t"><b><?= v2_e($name) ?></b><small><?= v2_e($act) ?></small></span><span class="pt-pill <?= $tone ?>"><?= v2_e($state) ?></span></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="pt-p" id="ptp-sold" role="tabpanel" aria-labelledby="ptt-sold" data-url="viaqui.com/organizator/sold" tabindex="0" hidden>
                <p class="pt-ui-h"><?= v2_te('Balance') ?></p>
                <div class="pt-ui-kpis">
                  <?= $ptKpi(v2_t('Available balance'), v2_money(9420)) ?>
                  <?= $ptKpi(v2_t('Processing'), v2_money(1180)) ?>
                  <?= $ptKpi(v2_t('Total received'), v2_money(64700)) ?>
                </div>
                <div class="pt-ui-payout"><span><?= v2_ic('bank') ?><?= v2_te('The payout goes to the venue\'s account') ?></span><span class="pt-ui-btn"><?= v2_te('Request payout') ?></span></div>
                <ul class="pt-ui-mini">
                  <li><span><?= v2_te('Guided tour') ?></span><b><?= v2_money(3900) ?></b></li>
                  <li><span><?= v2_te('Kids workshop') ?></span><b><?= v2_money(2640) ?></b></li>
                  <li><span><?= v2_te('Daily admission') ?></span><b><?= v2_money(2880) ?></b></li>
                </ul>
              </div>
              <div class="pt-p" id="ptp-marketing" role="tabpanel" aria-labelledby="ptt-marketing" data-url="viaqui.com/organizator/promo" tabindex="0" hidden>
                <p class="pt-ui-h"><?= v2_te('Marketing') ?></p>
                <ul class="pt-ui-list">
                  <li><span class="pt-ui-av is-tag"><?= v2_ic('tag') ?></span><span class="pt-ui-t"><b>AUTUMN10</b><small><?= v2_te('-10% · 214 uses') ?></small></span><span class="pt-pill is-ok"><?= v2_te('Active') ?></span></li>
                  <li><span class="pt-ui-av is-tag"><?= v2_ic('percent') ?></span><span class="pt-ui-t"><b>GROUP20</b><small><?= v2_te('-20% from 10 tickets · 38 uses') ?></small></span><span class="pt-pill is-ok"><?= v2_te('Active') ?></span></li>
                </ul>
                <p class="pt-ui-h2"><?= v2_te('Widget for your website') ?></p>
                <pre class="pt-ui-code">&lt;script src="viaqui.com/widget.js"
  data-locatie="your-museum"&gt;&lt;/script&gt;</pre>
              </div>
            </div>
          </div>
        </div>
        <p class="pt-note"><?= v2_ic('info') ?><?= v2_te('Illustrative mock-up of the panel, with sample data.') ?></p>
      </div>
    </div>
  </section>

  <!-- ===================== TICKET OFFICE ===================== -->
  <section class="pt-sec pt-dark pt-pos-sec" id="ghiseu" data-chapter="o-zi" aria-labelledby="pt-pos-h">
    <div class="wrap pt-two">
      <div class="pt-two-copy">
        <p class="pt-k is-dark"><?= v2_ic('printer') ?><?= v2_te('On site') ?></p>
        <h2 id="pt-pos-h"><?= v2_te('Your ticket office, with receipts and register closing') ?></h2>
        <p class="pt-sub is-dark"><?= v2_t('Besides the dashboard for online orders, you have a <strong class="pt-yellow">panel for managing on-site sales</strong>. The POS issues the ticket on the spot and stands in for the till, inside the app: cart, cash or card payment, receipt printed on a 58 or 80 mm thermal printer, straight from the browser, with no drivers to install. You sell admission, extra services or rentals.') ?></p>
        <ul class="pt-checks is-dark">
          <?php foreach ($ptPosChecks as $t): ?><li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li><?php endforeach; ?>
        </ul>
        <p class="pt-hint is-dark"><?= v2_ic('info') ?><?= v2_te('The on-site sales module is switched on for the venue\'s account.') ?></p>
        <a class="btn btn-light pt-mt" href="/list-your-venue" data-signup data-track-cta="parteneri_local_sales"><?= v2_te('I want to sell online and on site') ?><?= v2_ic('arrow-right') ?></a>
      </div>

      <div class="pt-till" aria-label="<?= v2_te('The POS at the ticket office, a mock-up with sample data') ?>" role="group">
        <div class="pt-tb">
          <div class="pt-tb-head"><b><?= v2_ic('ticket') ?><?= v2_te('InfoPoint: issue tickets') ?></b><span class="pt-tb-open"><?= v2_te('Register open') ?></span></div>
          <div class="pt-tb-body">
            <div class="pt-tb-items">
              <?php foreach ($ptPosItems as [$itemName, $itemPrice]): ?>
              <button class="pt-tb-item" type="button" data-pos-add data-name="<?= v2_e($itemName) ?>" data-price="<?= (int) $itemPrice ?>"><b><?= v2_e($itemName) ?></b><small><?= v2_money((int) $itemPrice) ?></small></button>
              <?php endforeach; ?>
            </div>
            <div class="pt-tb-cart">
              <p class="pt-tb-k"><?= v2_te('Cart') ?></p>
              <ul id="pt-cart" class="pt-tb-lines" aria-live="polite"><li class="pt-tb-empty"><?= v2_te('Tap a ticket to add it') ?></li></ul>
              <p class="pt-tb-total"><span><?= v2_te('Total') ?></span><b id="pt-total"><?= v2_money(0) ?></b></p>
              <div class="pt-tb-pay">
                <button class="pt-tb-btn is-cash" type="button" data-pos-pay="cash" disabled><?= v2_ic('coins') ?><?= v2_te('Cash') ?></button>
                <button class="pt-tb-btn is-card" type="button" data-pos-pay="card" disabled><?= v2_ic('credit-card') ?><?= v2_te('Card') ?></button>
              </div>
              <p class="pt-tb-print" id="pt-print" role="status"><?= v2_ic('printer') ?><span id="pt-print-t"><?= v2_te('Receipt on the thermal printer, after each order') ?></span></p>
            </div>
          </div>
        </div>
        <div class="pt-slip" id="pt-slip" aria-hidden="true">
          <p class="pt-slip-h"><?= v2_te('YOUR MUSEUM') ?></p>
          <p class="pt-slip-sub"><?= v2_te('Non-fiscal receipt · example') ?></p>
          <ul id="pt-slip-lines"></ul>
          <p class="pt-slip-total"><span><?= v2_te('TOTAL') ?></span><b id="pt-slip-total"><?= v2_money(0) ?></b></p>
          <span class="pt-slip-qr"><?= $ptQr('slip', 21) ?></span>
        </div>
        <p class="pt-note is-dark"><?= v2_ic('info') ?><?= v2_te('Illustrative mock-up: try it, nothing is sold.') ?></p>
      </div>
    </div>
  </section>

  <!-- ===================== SCANNING ===================== -->
  <section class="pt-sec pt-scan" id="scanare" data-chapter="o-zi" aria-labelledby="pt-scan-h">
    <div class="wrap pt-two is-rev">
      <div class="pt-two-copy">
        <p class="pt-k"><?= v2_ic('scan') ?><?= v2_te('At the entrance') ?></p>
        <h2 id="pt-scan-h"><?= v2_te('The scanning app, on phones and tablets: Android & iOS') ?></h2>
        <p class="pt-sub"><?= v2_t('It installs on the phone\'s home screen, like any app. It scans with the camera, and if a code cannot be read, you type it. The answer comes at once, with sound and vibration, so the person at the gate doesn\'t have to keep their eyes on the screen. You scan fast, <strong>offline too</strong>, with syncing later, and you see <strong>sales and traffic live</strong>, wherever you are.') ?></p>
        <ul class="pt-checks">
          <?php foreach ($ptScanChecks as $t): ?><li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li><?php endforeach; ?>
        </ul>
        <p class="pt-hint"><?= v2_ic('info') ?><?= v2_te('The scanning app is switched on for the venue\'s account.') ?></p>
      </div>
      <div class="pt-scanwrap" aria-hidden="true">
        <div class="pt-bigphone" id="pt-scanner">
          <div class="pt-bp-top"><span></span></div>
          <div class="pt-bp-screen">
            <p class="pt-bp-bar"><span><?= v2_te('Scanning · Gate 1') ?></span><em class="pt-net" id="pt-net"><i></i><span id="pt-net-t"><?= v2_te('Online') ?></span></em></p>
            <div class="pt-bp-frame"><span class="pt-qr"><?= $ptQr('gate', 21) ?></span><i class="pt-scanline"></i><i class="pt-corners"></i></div>
            <p class="pt-bp-state is-ok" id="pt-state"><?= v2_ic('check-circle') ?><span id="pt-state-t"><?= v2_te('ACCESS APPROVED') ?></span></p>
            <p class="pt-bp-sub" id="pt-state-sub"><?= v2_te('Adult ticket · 11:00') ?></p>
            <div class="pt-bp-stats"><span><b id="pt-rate">18</b><?= v2_te('scans/min') ?></span><span><b id="pt-inside">412</b><?= v2_te('entered') ?></span><span><b id="pt-queue">0</b><?= v2_te('to sync') ?></span></div>
          </div>
        </div>
        <p class="pt-bp-mock"><?= v2_te('Illustrative mock-up') ?></p>
      </div>
    </div>
  </section>

  <!-- ===================== HARDWARE ===================== -->
  <section class="pt-sec pt-hw" id="hardware" data-chapter="o-zi" aria-labelledby="pt-hw-h">
    <div class="wrap">
      <?= $ptHead('pt-hw-h', v2_t('What you need to get started'), v2_te('No server, no licences and no complicated installs.'), v2_te('Most often, venues start with what they already have in the house.')) ?>
      <ul class="pt-hw-grid" data-reveal>
        <?php foreach ($ptHardware as [$icon, $title, $text]): ?>
        <li><span class="pt-ic"><?= v2_ic($icon) ?></span><h3><?= v2_e($title) ?></h3><p><?= v2_e($text) ?></p></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <?php if ($ptVideo || $ptWall): ?>
  <!-- ===================== PARTNER STORIES ===================== -->
  <section class="pt-sec pt-stories" id="povesti" data-chapter="povesti" aria-labelledby="pt-stories-h">
    <div class="pt-stories-glow" aria-hidden="true"></div>
    <div class="wrap">
      <?= $ptHead('pt-stories-h', v2_t('Partner stories'), v2_t('Don\'t take our word for it. <span class="pt-nl">Listen to them.</span>'), v2_te('Venues that already sell through viaqui.com, on what their day looks like now: at the entrance, at the ticket office and in the reports.'), 'is-dark is-center') ?>

      <?php if ($ptDemoNote): ?><p class="pt-demo-note"><?= v2_ic('info') ?><?= v2_e($ptDemoNote) ?></p><?php endif; ?>

      <?php if ($ptVideo): ?>
      <div class="pt-feature<?= empty($ptVideo['quote']) ? ' is-solo' : '' ?>">
        <div class="pt-video" id="pt-video" data-yt="<?= v2_e($ptVideo['youtube']) ?>" data-title="<?= v2_e($ptVideo['title']) ?>">
          <img class="pt-video-poster" src="https://i.ytimg.com/vi/<?= v2_e($ptVideo['youtube']) ?>/maxresdefault.jpg" data-fallback="https://i.ytimg.com/vi/<?= v2_e($ptVideo['youtube']) ?>/hqdefault.jpg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <button class="pt-video-play" type="button" data-track-cta="parteneri_video_play" aria-label="<?= !empty($ptVideo['duration']) ? v2_te('Play the film: {title} ({duration})', ['title' => $ptVideo['title'], 'duration' => $ptVideo['duration']]) : v2_te('Play the film: {title}', ['title' => $ptVideo['title']]) ?>">
            <span class="pt-play" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 5.5v13a1 1 0 0 0 1.52.85l10.4-6.5a1 1 0 0 0 0-1.7L9.52 4.65A1 1 0 0 0 8 5.5Z"/></svg></span>
            <span class="pt-video-cta" aria-hidden="true"><b><?= v2_te('Watch the film') ?></b><small><?= !empty($ptVideo['duration']) ? v2_te('{duration} · loads from YouTube', ['duration' => $ptVideo['duration']]) : v2_te('loads from YouTube') ?></small></span>
          </button>
          <p class="pt-video-title"><?= $ptDemoTag($ptVideo) ?><?= v2_e($ptVideo['title']) ?></p>
        </div>
        <?php if (!empty($ptVideo['quote'])): ?>
        <figure class="pt-feature-quote">
          <span class="pt-qmark" aria-hidden="true">“</span>
          <blockquote><p><?= v2_e($ptVideo['quote']) ?></p></blockquote>
          <figcaption><span class="pt-avatar" aria-hidden="true"><?= v2_e($ptInitials($ptVideo['name'])) ?></span><span><b><?= v2_e($ptVideo['name']) ?></b><small><?= v2_e($ptVideo['role'] . ' · ' . $ptVideo['place']) ?></small></span></figcaption>
          <?php if (!empty($ptVideo['results'])): ?><ul class="pt-results"><?php foreach ($ptVideo['results'] as $r): ?><li><?= v2_ic('check') ?><?= v2_e($r) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </figure>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($ptWall): ?>
    <?php
    $ptQuoteCard = static function (array $q, bool $copy) use ($ptInitials, $ptDemoTag): string {
        return '<figure class="pt-quote"' . ($copy ? ' aria-hidden="true"' : '') . '>'
            . '<p class="pt-quote-top"><span class="pt-quote-venue">' . v2_e($q['venue'] . ' · ' . $q['city']) . '</span>' . $ptDemoTag($q) . '</p>'
            . '<blockquote><p>' . v2_e($q['quote']) . '</p></blockquote>'
            . (!empty($q['result']) ? '<p class="pt-quote-result">' . v2_ic('trend-up') . v2_e($q['result']) . '</p>' : '')
            . '<figcaption><span class="pt-avatar" aria-hidden="true">' . v2_e($ptInitials($q['name'])) . '</span><span><b>' . v2_e($q['name']) . '</b><small>' . v2_e($q['role']) . '</small></span></figcaption>'
            . '</figure>';
    };
    ?>
    <div class="pt-wall<?= $ptWallMoves ? ' is-moving' : '' ?>" id="pt-wall">
      <?php if ($ptWallMoves): ?>
      <div class="pt-wall-row">
        <div class="pt-wall-set"><?php foreach ($ptWall as $q) { echo $ptQuoteCard($q, false); } ?></div>
        <div class="pt-wall-set" aria-hidden="true"><?php foreach ($ptWall as $q) { echo $ptQuoteCard($q, true); } ?></div>
      </div>
      <div class="pt-wall-row is-rev" aria-hidden="true">
        <?php for ($pass = 0; $pass < 2; $pass++): ?><div class="pt-wall-set"><?php foreach (array_reverse($ptWall) as $q) { echo $ptQuoteCard($q, true); } ?></div><?php endfor; ?>
      </div>
      <div class="wrap pt-wall-foot"><button class="pt-wall-toggle" id="pt-wall-toggle" type="button" aria-controls="pt-wall" hidden><span class="pt-wall-ic" aria-hidden="true"></span><span id="pt-wall-toggle-t"><?= v2_te('Pause the scrolling') ?></span></button></div>
      <?php else: ?>
      <div class="wrap"><div class="pt-wall-grid"><?php foreach ($ptWall as $q) { echo $ptQuoteCard($q, false); } ?></div></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ===================== EVERYTHING YOU GET ===================== -->
  <section class="pt-sec pt-get" id="ce-primesti" data-chapter="ce-primesti" aria-labelledby="pt-get-h">
    <div class="wrap">
      <?= $ptHead('pt-get-h', v2_t('What you get · SEO · checkout · QR'), v2_te('Turn your activities into tickets that sell online.'), v2_te('A complete stack for selling your activities: viaqui.com combines optimised public pages, a buying flow, ticket issuing, operations at the entrance and growth tools. Venues get discovered organically, sell tickets fast and manage access with QR, without building a ticketing platform from scratch.'), 'is-center') ?>
      <ul class="pt-bento" data-reveal>
        <?php foreach ($ptStack as [$stackN, $stackK, $stackTitle, $stackText, $stackCls]): ?>
        <li class="pt-tile <?= $stackCls ?>">
          <p class="pt-tile-k"><span><?= $stackN ?></span><?= v2_e($stackK) ?></p>
          <h3><?= v2_e($stackTitle) ?></h3>
          <p><?= v2_e($stackText) ?></p>
          <div class="pt-tile-art" aria-hidden="true">
            <?php if ($stackCls === 'is-seo'): ?>
            <span class="pt-art-search"><?= v2_ic('magnifying-glass') ?><?= v2_te('things to do with kids lisbon') ?></span><span class="pt-art-res"><b><?= v2_te('Escape room for kids, Lisbon') ?></b><small>viaqui.com › lisbon › with-kids</small></span><span class="pt-art-res is-dim"><b></b><small></small></span>
            <?php elseif ($stackCls === 'is-checkout'): ?>
            <span class="pt-art-pay"><i><?= v2_te('Card') ?></i><i>Apple Pay</i><i>Google Pay</i><i class="is-on"><?= v2_te('Culture card') ?></i></span>
            <?php elseif ($stackCls === 'is-qr'): ?>
            <span class="pt-art-ticket">
              <span class="pt-art-qr"><?= $ptQr('stack', 21) ?></span>
              <span class="pt-art-tk"><small><?= v2_te('Ticket MKT-19024') ?></small><b><?= v2_te('Sat, 19 Oct · 14:00') ?></b><span class="pt-art-tk-sub"><?= v2_te('Admission + experience · 1 person') ?></span><span class="pt-art-ok"><?= v2_ic('check-circle') ?><?= v2_te('Valid at the entrance') ?></span></span>
            </span>
            <?php elseif ($stackCls === 'is-dash'): ?>
            <span class="pt-art-bars"><?php foreach ([34, 52, 44, 70, 58, 86, 74] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?></span>
            <?php elseif ($stackCls === 'is-growth'): ?>
            <span class="pt-art-codes"><i class="is-on">WEEKEND10</i><i><?= v2_te('Double points') ?></i><i><?= v2_te('Gift card') ?></i></span>
            <?php else: ?>
            <span class="pt-art-stars"><?php for ($s = 0; $s < 5; $s++): ?><?= v2_ic('star') ?><?php endfor; ?><b>4.9</b></span>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="pt-more">
        <h3 class="pt-more-h"><?= v2_te('A complete platform for selling activities.') ?></h3>
        <ul class="pt-benefits" data-reveal>
          <?php foreach ($ptBenefits as [$benIcon, $benTitle, $benText]): ?>
          <li><span class="pt-ic is-deep"><?= v2_ic($benIcon) ?></span><h4><?= v2_e($benTitle) ?></h4><p><?= v2_e($benText) ?></p></li>
          <?php endforeach; ?>
        </ul>
        <ul class="pt-small" data-reveal>
          <?php foreach ($ptSmall as [$smIcon, $smTitle, $smText]): ?>
          <li><span class="pt-ic is-soft"><?= v2_ic($smIcon) ?></span><div><h4><?= v2_e($smTitle) ?></h4><p><?= v2_e($smText) ?></p></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- ===================== MODULES ===================== -->
  <section class="pt-sec pt-mods" id="module" data-chapter="ce-primesti" aria-labelledby="pt-mod-h">
    <div class="wrap pt-split is-wide">
      <?= $ptHead('pt-mod-h', v2_t('Modules'), v2_te('Pick what you need. The platform can grow with you.')) ?>
      <div class="pt-mod">
        <div class="pt-mod-tabs" role="tablist" aria-label="<?= v2_te('Modules') ?>" data-tabs>
          <button type="button" role="tab" id="pt-tab-tickets" aria-controls="pt-panel-tickets" aria-selected="true"><?= v2_ic('ticket') ?><?= v2_te('Tickets') ?></button>
          <button type="button" role="tab" id="pt-tab-calendar" aria-controls="pt-panel-calendar" aria-selected="false" tabindex="-1"><?= v2_ic('calendar-blank') ?><?= v2_te('Availability') ?></button>
          <button type="button" role="tab" id="pt-tab-growth" aria-controls="pt-panel-growth" aria-selected="false" tabindex="-1"><?= v2_ic('trend-up') ?><?= v2_te('Growth') ?></button>
          <button type="button" role="tab" id="pt-tab-reports" aria-controls="pt-panel-reports" aria-selected="false" tabindex="-1"><?= v2_ic('chart-line-up') ?><?= v2_te('Reports') ?></button>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-tickets" aria-labelledby="pt-tab-tickets" tabindex="0">
          <h3><?= v2_te('Ticket types and packages') ?></h3>
          <p><?= v2_te('Create simple tickets, child/adult tickets, group packages, tickets with a time slot, extras or tickets for tours.') ?></p>
          <div class="pt-mod-grid"><div><?= v2_te('Adult · {price}', ['price' => v2_money(95)]) ?></div><div><?= v2_te('Child · {price}', ['price' => v2_money(45)]) ?></div><div><?= v2_te('Group · {price}', ['price' => v2_money(340)]) ?></div></div>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-calendar" aria-labelledby="pt-tab-calendar" tabindex="0" hidden>
          <h3><?= v2_te('Availability and slots') ?></h3>
          <p><?= v2_te('Control days, hours, capacity, closures, exceptions, seasons and time slots with limited availability.') ?></p>
          <div class="pt-mod-days"><?php for ($day = 1; $day <= 14; $day++): ?><span<?= $day % 4 === 0 ? ' class="is-busy"' : '' ?>><?= $day ?></span><?php endfor; ?></div>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-growth" aria-labelledby="pt-tab-growth" tabindex="0" hidden>
          <h3><?= v2_te('Promotions, gift cards, points') ?></h3>
          <p><?= v2_te('Run promo codes, seasonal campaigns, benefits through bonus points and eligibility for gift cards or vouchers.') ?></p>
          <div class="pt-mod-chips"><span class="is-on">WEEKEND10</span><span class="is-mint"><?= v2_te('Double points') ?></span><span><?= v2_te('Gift card') ?></span></div>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-reports" aria-labelledby="pt-tab-reports" tabindex="0" hidden>
          <h3><?= v2_te('Reports and useful data') ?></h3>
          <p><?= v2_te('See what sells, when, to whom, which activities perform and which time slots convert better.') ?></p>
          <div class="pt-mod-grid is-stats"><div><small><?= v2_te('Sales') ?></small><b>18.4k</b></div><div><small><?= v2_te('Orders') ?></small><b>96</b></div><div><small><?= v2_te('Conversion') ?></small><b>4.2%</b></div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== VISIBILITY / SEO ===================== -->
  <section class="pt-sec pt-seo" id="vizibilitate" data-chapter="vizibilitate" aria-labelledby="pt-seo-h">
    <div class="wrap">
      <div class="pt-seo-grid">
        <div>
          <?= $ptHead('pt-seo-h', v2_t('SEO engine'), v2_te('You don\'t depend on ads alone.'), v2_te('Every activity can become an optimised sales page. Your venue can appear on city, category and intent pages, not just in a generic list.')) ?>
          <div class="pt-browser" aria-hidden="true">
            <p class="pt-browser-bar"><?= v2_ic('lock-simple') ?><span>viaqui.com</span><b id="pt-typed-url">/lisbon/with-kids</b><i class="pt-bk-caret"></i></p>
          </div>
          <ul class="pt-urls">
            <?php foreach ($ptSeoUrls as $ui => [$urlPath, $urlLabel]): ?>
            <li data-url-i="<?= $ui ?>"><b><?= v2_e($urlPath) ?></b><span><?= v2_e($urlLabel) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="pt-anatomy">
          <div class="pt-anatomy-head"><p><?= v2_te('Anatomy of an SEO page') ?></p><h3><?= v2_te('Your activity becomes findable.') ?></h3></div>
          <ol class="pt-anatomy-list" data-reveal>
            <?php foreach ($ptAnatomy as $ai => [$anaTitle, $anaText]): ?>
            <li><span class="pt-anatomy-n"><?= $ai + 1 ?></span><div><b><?= v2_e($anaTitle) ?></b><span><?= v2_e($anaText) ?></span></div></li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>

      <div class="pt-reach">
        <h3 class="pt-reach-h"><?= v2_te('You don\'t sell from a panel alone. You sell from a place where people are already looking.') ?></h3>
        <ul class="pt-reach-nums">
          <?php if ($ptAttractions): ?><li><b><?= v2_thousands($ptAttractions) ?></b><span><?= v2_te('attractions in the catalogue') ?></span></li><?php endif; ?>
          <?php if ($ptCities): ?><li><b><?= $ptCities ?></b><span><?= v2_te('cities with their own pages') ?></span></li><?php endif; ?>
          <?php if ($ptCategories): ?><li><b><?= count($ptCategories) ?></b><span><?= v2_te('categories of experiences') ?></span></li><?php endif; ?>
          <li><b>2%</b><span><?= v2_te('commission, without touching your price') ?></span></li>
        </ul>
        <ul class="pt-reach-list" data-reveal>
          <?php foreach ($ptReach as [$icon, $title, $text]): ?>
          <li><span class="pt-ic"><?= v2_ic($icon) ?></span><div><h4><?= v2_e($title) ?></h4><p><?= v2_e($text) ?></p></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- ===================== ANALYTICS + TRACKING ===================== -->
  <section class="pt-sec pt-dark pt-analytics" id="analytics" data-chapter="vizibilitate" aria-labelledby="pt-analytics-h">
    <div class="wrap">
      <?= $ptHead('pt-analytics-h', v2_t('Data that grows your sales'), v2_t('Advanced analytics + 100% tracking. <span class="pt-hl">Ads up to 60% cheaper.</span>'), '', 'is-dark') ?>
      <div class="pt-two is-top">
        <div>
          <h3 class="pt-sub-h"><?= v2_te('Why analytics matters') ?></h3>
          <ul class="pt-list is-dark is-arrows">
            <?php foreach ($ptAnalytics as $anItem): ?><li><?= v2_ic('arrow-right') ?><?= v2_e($anItem) ?></li><?php endforeach; ?>
          </ul>
          <a class="btn btn-light pt-mt" href="/list-your-venue" data-signup data-track-cta="parteneri_analytics_ads"><?= v2_te('I want cheaper ads') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <div class="pt-glass">
          <h3 class="pt-sub-h"><?= v2_te('Complete tracking, nothing lost') ?></h3>
          <p><?= v2_t('viaqui.com integrates with <strong>all tracking pixels</strong> and with <strong>Facebook CAPI</strong>. It sends <strong class="pt-yellow">100% of conversion events</strong> server-side, so ad blockers no longer stop you, and neither does the iOS update that cuts most tracking.') ?></p>
          <div class="pt-flow" id="pt-flow" aria-hidden="true">
            <div class="pt-flow-row is-lost"><span class="pt-flow-src"><?= v2_ic('globe-simple') ?><?= v2_te('Browser pixel only') ?></span><span class="pt-flow-line"><i></i><i></i><i></i><b class="pt-flow-wall"><?= v2_ic('x') ?><?= v2_te('ad blocker / iOS') ?></b></span><span class="pt-flow-dst is-dim"><?= v2_te('Ads') ?></span></div>
            <div class="pt-flow-row is-ok"><span class="pt-flow-src"><?= v2_ic('lightning') ?><?= v2_te('Pixel + server-side CAPI') ?></span><span class="pt-flow-line"><i></i><i></i><i></i></span><span class="pt-flow-dst"><?= v2_te('Ads') ?></span></div>
          </div>
          <div class="pt-glass-stats"><div><b>100%</b><span><?= v2_te('events tracked') ?></span></div><div><b>−60%</b><span><?= v2_te('ad cost') ?></span></div></div>
          <p class="pt-glass-note"><?= v2_t('It works with ads on <strong>Facebook, Instagram, TikTok and Google</strong>. Correct data = more efficient algorithms = a lower cost per sale.') ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== PAYMENTS ===================== -->
  <section class="pt-sec pt-pay" id="plati" data-chapter="bani" aria-labelledby="pt-pay-h">
    <div class="wrap pt-two">
      <div>
        <?= $ptHead('pt-pay-h', v2_t('Payments for every customer'), v2_te('Every payment method, within the buyer\'s reach.'), v2_te('The simpler the payment, the more you sell. viaqui.com accepts the most used methods: the customer pays in two taps, with no friction.')) ?>
        <p class="pt-sub"><?= v2_t('viaqui.com collects the payment from the customer and pays you out <strong>at regular intervals</strong>, or on request, whenever you want your money settled.') ?></p>
      </div>
      <ul class="pt-pay-grid" data-reveal>
        <?php foreach ($ptPayments as [$payIcon, $payName]): ?><li><?= v2_ic($payIcon) ?><?= v2_e($payName) ?></li><?php endforeach; ?>
        <li class="is-dark"><?= v2_t('and more<br>coming soon') ?></li>
      </ul>
    </div>
  </section>

  <!-- ===================== THE MONEY ===================== -->
  <section class="pt-sec pt-money" id="bani" data-chapter="bani" aria-labelledby="pt-money-h">
    <div class="wrap">
      <div class="pt-money-top">
        <div>
          <p class="pt-k is-dark"><?= v2_te('The difference that changes everything') ?></p>
          <h2 id="pt-money-h" class="pt-money-h"><?= v2_t('Commission {rate}{line}', ['rate' => '<span class="pt-big2" id="pt-big2">2%*</span>', 'line' => '<span class="pt-nl">' . v2_te('Your price stays yours.') . '</span>']) ?></h2>
        </div>
        <div>
          <p class="pt-sub is-dark"><?= v2_t('The 2%* commission is added transparently on top, in the final price. You set your price and receive it <strong class="pt-yellow">in full</strong> at payout, with nothing taken from your margin. If you also sell your tickets elsewhere, the commission is 4%: 2% included in the price and 2% added.') ?></p>
          <p class="pt-sub is-dark"><?= v2_te('Clear costs, with no infrastructure built from scratch: you pay for infrastructure that sells, not for vague promises. No monthly subscription, no setup cost and no fee for each ticket issued at the ticket office.') ?></p>
        </div>
      </div>
      <div class="pt-money-grid">
        <ul class="pt-checks is-dark">
          <li><?= v2_ic('check-circle') ?><?= v2_te('You set the price, you receive the price you set') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('The customer sees the 2%* clearly: honest, no surprises') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('Zero monthly costs, zero start-up fees') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('Tickets sold at the ticket office carry no platform commission') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('You see the available balance and request a payout whenever you want') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('Documents and invoices stay in your account') ?></li>
        </ul>

        <div class="pt-compare" id="pt-compare">
          <p class="pt-compare-h"><?= v2_te('A {amount} ticket: what do you receive?', ['amount' => v2_money(100)]) ?></p>
          <div class="pt-compare-row">
            <div class="pt-compare-top"><span><?= v2_te('A classic platform') ?></span><span class="is-red">−<?= v2_money(9.5) ?></span></div>
            <p class="pt-compare-note"><?= v2_te('8% commission + 1–2% card processing cost, both taken out of your money') ?></p>
            <div class="pt-meter is-red"><i style="--w:90.5%"></i></div>
            <p class="pt-compare-get"><?= v2_t('You receive: {amount}', ['amount' => '<strong class="is-red">~' . v2_money(90.5) . '</strong>']) ?></p>
          </div>
          <div class="pt-compare-row is-ours">
            <div class="pt-compare-top"><span><?= v2_te('viaqui.com (2%* added on top)') ?></span><span class="is-green">100%</span></div>
            <div class="pt-meter"><i style="--w:100%"></i></div>
            <p class="pt-compare-get"><?= v2_t('You receive: {amount}', ['amount' => '<strong class="is-green">' . v2_money(100) . '</strong>']) ?></p>
            <p class="pt-compare-note"><?= v2_te('The commission and the card cost are included in the final price. You receive your price, in full.') ?></p>
          </div>
          <p class="pt-compare-foot"><?= v2_te('At volume, the difference becomes huge.') ?></p>
        </div>

        <div class="pt-calc">
          <p class="pt-calc-h"><?= v2_te('What it means for you') ?></p>
          <div class="pt-calc-row">
            <label for="pt-qty"><?= v2_te('Tickets sold online per month') ?></label>
            <div class="pt-calc-in"><input id="pt-qty" type="number" inputmode="numeric" min="0" max="100000" step="10" value="400"><input class="pt-range" type="range" min="0" max="5000" step="10" value="400" aria-label="<?= v2_te('Tickets sold online per month') ?>" data-mirror="pt-qty"></div>
          </div>
          <div class="pt-calc-row">
            <label for="pt-price"><?= v2_te('Average price per ticket (€)') ?></label>
            <div class="pt-calc-in"><input id="pt-price" type="number" inputmode="numeric" min="0" max="10000" step="5" value="45"><input class="pt-range" type="range" min="0" max="500" step="5" value="45" aria-label="<?= v2_te('Average price per ticket (€)') ?>" data-mirror="pt-price"></div>
          </div>
          <dl class="pt-calc-out">
            <div><dt><?= v2_te('You take in from tickets') ?></dt><dd id="pt-out-rev"><?= v2_money(18000) ?></dd></div>
            <div><dt><?= v2_te('2% commission, added on top of the price') ?></dt><dd id="pt-out-fee"><?= v2_money(360) ?></dd></div>
            <div class="is-total"><dt><?= v2_te('Stays with you') ?></dt><dd id="pt-out-net"><?= v2_money(18000) ?></dd></div>
            <div class="is-vs"><dt><?= v2_te('On a classic platform (−9.5%) you would keep') ?></dt><dd id="pt-out-classic"><?= v2_money(16290) ?></dd></div>
          </dl>
          <p class="pt-calc-note"><?= v2_t('The commission is added to the ticket price, so your price stays whole. The final price is {price} per ticket. The comparison uses the example alongside.', ['price' => '<span id="pt-out-buyer">' . v2_money(45.9) . '</span>']) ?></p>
        </div>
      </div>
      <p class="pt-footnote"><?= v2_t('<strong>*</strong> The 2% commission applies to exclusive sales through viaqui.com. viaqui.com collects the payment from the customer and pays you out at regular intervals or on request.') ?></p>
    </div>
  </section>

  <!-- ===================== FISCAL / ANAF ===================== -->
  <section class="pt-sec pt-fiscal" id="fiscal" data-chapter="bani" aria-labelledby="pt-fiscal-h">
    <div class="wrap">
      <?= $ptHead('pt-fiscal-h', v2_t('Tax, without the headaches'), v2_te('Accounting and ANAF, handled automatically.'), '', 'is-center') ?>
      <ul class="pt-fiscal-grid" data-reveal>
        <?php foreach ($ptFiscal as [$fisIcon, $fisTitle, $fisText]): ?>
        <li><span class="pt-ic"><?= v2_ic($fisIcon) ?></span><h3><?= v2_e($fisTitle) ?></h3><p><?= v2_e($fisText) ?></p></li>
        <?php endforeach; ?>
      </ul>
      <div class="pt-anaf" id="pt-anaf">
        <p class="pt-anaf-k"><?= v2_te('One sale → documents generated automatically, in seconds') ?></p>
        <div class="pt-anaf-grid">
          <div class="pt-order">
            <p class="pt-order-top"><span><?= v2_te('New order') ?></span><i class="pt-live"></i></p>
            <b><?= v2_te('Admission ticket + rental') ?></b>
            <p class="pt-order-no">MKT-19024 · <?= v2_money(180) ?></p>
            <p class="pt-bk-ok"><?= v2_ic('check') ?><?= v2_te('Payment confirmed') ?></p>
          </div>
          <div class="pt-anaf-wire" aria-hidden="true"><i></i></div>
          <div class="pt-docs" aria-hidden="true">
            <?php foreach ($ptDocs as [$docIcon, $docName, $docMeta]): ?>
            <div class="pt-doc is-done"><div class="pt-doc-top"><?= v2_ic($docIcon) ?><span class="pt-doc-state"><?= v2_ic('check') ?></span></div><b><?= v2_e($docName) ?></b><small><?= v2_e($docMeta) ?></small><i class="pt-doc-bar"></i></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="pt-anaf-foot">
          <p><?= v2_t('Zero manual entry. The documents are ready within {time} of every sale.', ['time' => '<strong id="pt-elapsed">3s</strong>']) ?></p>
          <a class="btn btn-light" href="/list-your-venue" data-signup data-track-cta="parteneri_anaf"><?= v2_te('I want my tax paperwork on autopilot') ?><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== HOW TO START ===================== -->
  <section class="pt-sec pt-how" id="cum" data-chapter="cum" aria-labelledby="pt-how-h">
    <div class="wrap">
      <?= $ptHead('pt-how-h', v2_t('From account to first sale'), v2_te('Four steps. Under a day. Zero start-up costs.'), '', 'is-center') ?>
      <ol class="pt-steps" id="pt-steps">
        <?php foreach ($ptSteps as $stepIndex => [$stepIcon, $stepTitle, $stepText, $stepTag]): ?>
        <li class="pt-step" style="--i:<?= $stepIndex ?>"><span class="pt-step-n"><?= $stepIndex + 1 ?></span><span class="pt-ic"><?= v2_ic($stepIcon) ?></span><h3><?= v2_e($stepTitle) ?></h3><p><?= v2_e($stepText) ?></p><p class="pt-tag"><?= v2_e($stepTag) ?></p></li>
        <?php endforeach; ?>
      </ol>
      <div class="pt-center"><a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="parteneri_how_it_works"><?= v2_te('Start now, for free') ?><?= v2_ic('arrow-right') ?></a></div>

      <div class="pt-ops">
        <div class="pt-ops-head">
          <p class="pt-k"><?= v2_te('Operations') ?></p>
          <h3><?= v2_te('From listing to check-in.') ?></h3>
          <p><?= v2_te('The flow is built for small teams: you publish the activity, sell tickets, scan at the entrance and follow the results.') ?></p>
        </div>
        <ol class="pt-ops-list" data-reveal>
          <?php foreach ($ptOps as $oi => [$opTitle, $opText]): ?>
          <li><span class="pt-ops-n"><?= $oi + 1 ?></span><h4><?= v2_e($opTitle) ?></h4><p><?= v2_e($opText) ?></p></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <!-- ===================== TIXELLO ===================== -->
  <section class="pt-sec pt-dark pt-tech" id="tehnologie" data-chapter="cum" aria-labelledby="pt-tech-h">
    <div class="pt-tech-rings" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="wrap pt-two">
      <div>
        <span class="pt-badge"><?= v2_te('Powered by Tixello') ?></span>
        <h2 class="pt-tech-h" id="pt-tech-h"><?= v2_te('Mature infrastructure, tested at scale.') ?></h2>
        <p class="pt-sub is-dark"><?= v2_te('viaqui.com runs on Tixello, the ticketing system that has already processed over €{millions} million in sales and over {tickets} tickets. You get production-grade technology, without building or maintaining it.', ['millions' => $ptOverMillions($ptRevenue), 'tickets' => $ptOverThousands($ptTickets)]) ?></p>
        <a class="btn btn-light pt-mt" href="/list-your-venue" data-signup data-track-cta="parteneri_tixello"><?= v2_te('Become a partner') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="pt-numbers">
        <p class="pt-numbers-k"><?= v2_te('In numbers') ?></p>
        <dl>
          <?php foreach ($ptTixello as [$numLabel, $numValue]): ?><div><dt><?= v2_e($numLabel) ?></dt><dd><?= v2_e($numValue) ?></dd></div><?php endforeach; ?>
        </dl>
      </div>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="pt-sec pt-faq" id="intrebari" data-chapter="intrebari" aria-labelledby="pt-faq-h">
    <div class="wrap pt-faq-grid">
      <div class="pt-faq-side">
        <?= $ptHead('pt-faq-h', v2_t('Frequently asked questions'), v2_te('What you want to know before you start')) ?>
        <p class="pt-faq-alt"><?= v2_t('Can\'t find the answer? Write to us at {email}.', ['email' => '<a href="mailto:' . v2_e(SUPPORT_EMAIL) . '?subject=' . rawurlencode(v2_t('Partnership question for viaqui.com')) . '" data-track-cta="parteneri_faq_email">' . v2_e(SUPPORT_EMAIL) . '</a>']) ?></p>
      </div>
      <div class="pt-faq-groups">
        <?php foreach ($ptFaqGroups as $gi => [$groupName, $groupFaqs]): ?>
        <div class="pt-faq-group">
          <h3><?= v2_e($groupName) ?></h3>
          <?php foreach ($groupFaqs as $fi => [$faqQ, $faqA]): ?>
          <details class="qa"<?= $gi === 0 && $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($ptPull): ?>
  <!-- ===================== ONE LARGE QUOTE ===================== -->
  <section class="pt-voice" data-chapter="intrebari" aria-label="<?= v2_te('What a partner says') ?>">
    <div class="wrap">
      <figure class="pt-voice-in" id="pt-voice">
        <span class="pt-voice-mark" aria-hidden="true">“</span>
        <blockquote><p><?= v2_e($ptPull['quote']) ?></p></blockquote>
        <figcaption>
          <span class="pt-avatar" aria-hidden="true"><?= v2_e($ptInitials($ptPull['name'])) ?></span>
          <span><b><?= v2_e($ptPull['name']) ?><?= $ptDemoTag($ptPull) ?></b><small><?= v2_e($ptPull['role'] . ' · ' . $ptPull['venue'] . ', ' . $ptPull['city']) ?></small></span>
          <?php if (!empty($ptPull['result'])): ?><span class="pt-quote-result"><?= v2_ic('trend-up') ?><?= v2_e($ptPull['result']) ?></span><?php endif; ?>
        </figcaption>
      </figure>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== FINALE: start alone or talk to us ===================== -->
  <section class="pt-finale" id="demo" data-chapter="cum" aria-labelledby="pt-final-h">
    <div class="pt-finale-bg" aria-hidden="true"><svg class="deco-arches" viewBox="0 0 400 400" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg></div>
    <div class="wrap">
      <div class="pt-final-head">
        <span class="pt-badge"><?= v2_te('Become a partner') ?></span>
        <h2 id="pt-final-h"><?= v2_t('Put your activities on sale <span class="pt-nl">and keep your price whole.</span>') ?></h2>
        <p><?= v2_te('No start-up costs. Unlimited activities. Onboarding in 5 minutes, go live today. 2%* commission, without touching your price.') ?></p>
      </div>

      <div class="pt-final-grid">
        <div class="pt-self">
          <p class="pt-self-k"><?= v2_te('Ready to list?') ?></p>
          <h3><?= v2_te('Your venue can become the next activity discovered online.') ?></h3>
          <p><?= v2_te('If you have an activity people should discover, viaqui.com can be the infrastructure that sells it. You create the account yourself, add your activities and go live.') ?></p>
          <ol class="pt-self-steps">
            <?php foreach ($ptSteps as $stepIndex => [, $stepTitle, , $stepTag]): ?><li><i><?= $stepIndex + 1 ?></i><b><?= v2_e($stepTitle) ?></b><span><?= v2_e($stepTag) ?></span></li><?php endforeach; ?>
          </ol>
          <a class="btn btn-light pt-self-go" href="/list-your-venue" data-signup data-track-cta="parteneri_final_signup"><?= v2_te('I want to sell my activities') ?><?= v2_ic('arrow-right') ?></a>
          <p class="pt-self-note"><?= v2_te('No start-up cost · Unlimited activities · Cancel anytime') ?></p>
          <div class="pt-contact">
            <a class="btn btn-outline-light" href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>?subject=<?= rawurlencode(v2_t('Partnership question for viaqui.com')) ?>" data-track-cta="parteneri_email_contact"><?= v2_ic('envelope-simple') ?><?= v2_te('Send us an email') ?></a>
            <p><?= v2_t('Or call {phone}, Monday to Friday, 09:00 to 18:00.', ['phone' => '<a href="tel:+40750292962" data-track-cta="parteneri_phone">+40 750 292 962</a>']) ?></p>
          </div>
        </div>

        <div class="pt-form-card">
          <h3 class="pt-form-h"><?= v2_te('Let\'s look together at what your venue would look like') ?></h3>
          <p class="pt-form-lead"><?= v2_te('We show you the panel on your own data: which activities you would publish, which ticket types fit, which pages should be created, which SEO opportunities you have and what a day at the ticket office would look like. No long presentation.') ?></p>
          <form class="pt-form" id="fv-form" data-lead-source="Parteneri" novalidate>
            <p class="pt-form-err" id="fv-error" role="alert" tabindex="-1" hidden></p>
            <!-- people never see or reach this; a bot that fills it gets a quiet "sent" -->
            <div class="pt-trap" aria-hidden="true"><label for="fv-fax"><?= v2_te('Fax (leave empty)') ?></label><input id="fv-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
            <div class="pt-fields">
              <p class="pt-field"><label for="fv-name"><?= v2_te('Full name') ?><span aria-hidden="true">*</span></label><input id="fv-name" name="contact_name" type="text" autocomplete="name" maxlength="160" required></p>
              <p class="pt-field"><label for="fv-email"><?= v2_te('Email') ?><span aria-hidden="true">*</span></label><input id="fv-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="190" required placeholder="<?= v2_te('email@venue.com') ?>"></p>
              <p class="pt-field"><label for="fv-phone"><?= v2_te('Phone') ?></label><input id="fv-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+351..."></p>
              <p class="pt-field"><label for="fv-role"><?= v2_te('Your role') ?></label><span class="pt-sel"><select id="fv-role" name="role"><option value=""><?= v2_te('Choose') ?></option><?php foreach ($ptRoles as $role): ?><option><?= v2_e($role) ?></option><?php endforeach; ?></select><?= v2_ic('caret-down') ?></span></p>
              <p class="pt-field"><label for="fv-venue"><?= v2_te('Venue name') ?><span aria-hidden="true">*</span></label><input id="fv-venue" name="location_name" type="text" autocomplete="organization" maxlength="200" required placeholder="<?= v2_te('e.g. Mystery Rooms Lisbon') ?>"></p>
              <p class="pt-field"><label for="fv-city"><?= v2_te('City') ?><span aria-hidden="true">*</span></label><input id="fv-city" name="city" type="text" autocomplete="address-level2" maxlength="120" required list="ftr-cities" placeholder="<?= v2_te('e.g. Lisbon') ?>"></p>
              <p class="pt-field"><label for="fv-type"><?= v2_te('Venue type') ?></label><span class="pt-sel"><select id="fv-type" name="venue_type"><option value=""><?= v2_te('Choose') ?></option><?php foreach ($ptCategories as $c): ?><option value="<?= v2_e($c['slug']) ?>"><?= v2_e($c['name']) ?></option><?php endforeach; ?><option value="other"><?= v2_te('Something else') ?></option></select><?= v2_ic('caret-down') ?></span></p>
              <p class="pt-field"><label for="fv-count"><?= v2_te('How many activities do you sell?') ?></label><span class="pt-sel"><select id="fv-count" name="activities_count"><option value=""><?= v2_te('Choose') ?></option><?php foreach ($ptCounts as $count): ?><option><?= v2_e($count) ?></option><?php endforeach; ?></select><?= v2_ic('caret-down') ?></span></p>
              <p class="pt-field is-wide"><label for="fv-message"><?= v2_te('What would you like to solve?') ?></label><textarea id="fv-message" name="message" rows="4" maxlength="1800" placeholder="<?= v2_te('Describe your activities, ticket types, opening hours and the problems you have now with sales or bookings. For example: we only sell at the ticket office and want online too, or we have queues at the entrance at weekends.') ?>"></textarea></p>
              <p class="pt-check is-wide"><input id="fv-consent" name="consent" type="checkbox" required><label for="fv-consent"><?= v2_t('I agree to be contacted for a conversation about listing my venue on viaqui.com. <a href="{url}">Privacy policy</a>', ['url' => '/privacy']) ?></label></p>
            </div>
            <button class="btn btn-primary pt-submit" id="fv-submit" type="submit"><?= v2_te('Send the request') ?></button>
          </form>
          <div class="pt-done" id="fv-done" hidden>
            <span class="pt-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
            <h3 id="fv-done-h" tabindex="-1"><?= v2_te('Thank you!') ?></h3>
            <p><?= v2_t('Your request has reached the viaqui.com team. We will contact you on the next working day at {email} with a proposal for a demonstration.', ['email' => '<strong id="fv-done-email"></strong>']) ?></p>
          </div>
          <div class="pt-prep">
            <b><?= v2_te('What you can prepare beforehand:') ?></b>
            <ul>
              <li><?= v2_ic('check') ?><?= v2_te('the venue name and the city') ?></li>
              <li><?= v2_ic('check') ?><?= v2_te('the types of activities') ?></li>
              <li><?= v2_ic('check') ?><?= v2_te('prices and capacity') ?></li>
              <li><?= v2_ic('check') ?><?= v2_te('opening hours and access rules') ?></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- phones: the ask follows until the finale is in view -->
  <div class="pt-bar" id="pt-bar" hidden>
    <span class="pt-bar-t"><b><?= v2_te('Want to see the panel on your own data?') ?></b><small><?= v2_te('A 20-minute demonstration, no obligation.') ?></small></span>
    <a class="btn btn-outline-light" href="#demo" data-track-cta="parteneri_bar_demo"><?= v2_te('Demo') ?></a>
    <a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="parteneri_bar_signup"><?= v2_te('Start') ?></a>
  </div>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
