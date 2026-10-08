<?php
/**
 * Terms and conditions: /terms (v2 design, includes/v2/legal.php layout).
 *
 * The rules of viaqui.com as the platform works today: TIXELLO S.R.L. sells tickets on behalf of the operators of
 * the activities (the operator is responsible for the activity itself), the order and payment (the ticketing fee and
 * any other cost shown before paying, Stripe, cultural cards with their surcharge), electronic tickets with a QR
 * code, cancellation and refunds (no 14-day withdrawal for dated leisure services, OUG 34/2014 art. 16 lit. l; full
 * refund including the fee when the operator cancels, as on Ambilet), gift cards (12 months), the points programme,
 * reviews, what isn't allowed, the operators' side (signup creates a pending account, nothing public before approval),
 * liability, complaints (ANPC, SAL) and the applicable law.
 *
 * The body of the document is plain English and does not go through v2_t(): a legal text is translated as a whole
 * file per language. Only the page title, the description and the hero go through the language layer.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// static text: 30-minute page cache
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/legal.php';

$lgEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'contact@viaqui.com';
$lgMail = '<a href="mailto:' . v2_e($lgEmail) . '">' . v2_e($lgEmail) . '</a>';

$lgSections = [
    ['despre', 'About viaqui.com and these terms', <<<'HTML'
<p>viaqui.com is a platform where you discover and buy tickets for activities, attractions and experiences across Europe. The platform is operated by <strong>TIXELLO S.R.L.</strong> (the full company details are above), referred to below as "we" or "us".</p>
<p>These terms apply to everyone who uses the site: visitors, customers and the operators who list their activities. When you create an account or place an order, you confirm that you have read and accept them. We process personal data in line with the <a href="/privacy">Privacy policy</a>.</p>
HTML],
    ['definitii', 'What the terms used here mean', <<<'HTML'
<ul>
  <li><strong>The platform:</strong> the viaqui.com site and the services linked to it (the account, the emails, the electronic tickets).</li>
  <li><strong>Customer:</strong> the person who buys tickets through the platform.</li>
  <li><strong>Operator:</strong> the venue, organiser or guide that offers the activity and is responsible for running it.</li>
  <li><strong>Activity:</strong> the attraction, experience, tour, workshop or any other leisure service listed on the platform.</li>
  <li><strong>Ticket:</strong> the electronic proof of the right of access to the activity, with a unique QR code.</li>
  <li><strong>Order:</strong> the purchase of one or more tickets in a single payment.</li>
</ul>
HTML],
    ['rolul-nostru', 'Our role and the role of the operators', <<<'HTML'
<p>We sell the tickets <strong>in the name and on behalf of the operators</strong>. The contract for the activity is concluded between you and the operator, who is responsible for running it, for safety, for quality, for the necessary permits and for the information about the activity (opening times, duration, minimum age, access rules, accessibility, what the ticket includes).</p>
<p>We are responsible for the operation of the platform, for processing the order, for collecting the payment on behalf of the operator and for issuing and sending the tickets. We check operators before their activities become public, but we cannot guarantee that every activity takes place.</p>
HTML],
    ['cont', 'Your account', <<<'HTML'
<ul>
  <li>The account is free and can be created from the age of 16. You can also buy without an account, with your email address.</li>
  <li>The details in your account must be real and up to date. Your password is personal: do not share it with anyone. For extra safety, turn on two-step sign-in in Settings.</li>
  <li>If you notice unauthorised access to your account, change your password and tell us straight away.</li>
  <li>You can delete your account at any time from Settings. Orders stay in our records for as long as the law requires.</li>
  <li>We may suspend or close accounts used for fraud, abuse or breaches of these terms.</li>
</ul>
HTML],
    ['comanda', 'The order', <<<'HTML'
<ul>
  <li>You choose the activity, the date and time (where applicable), the ticket types and the options, then you pay at checkout.</li>
  <li>While you complete the payment, the places you chose are held temporarily. If the payment is not made within the time shown, the hold expires and the places are released.</li>
  <li>The order is confirmed when the payment is accepted. You receive the confirmation and the tickets by email, and they also appear in your account, under <strong>My tickets</strong>.</li>
  <li>Check the details before you pay: the activity, the date, the time, the number of tickets and the email address.</li>
  <li>If a displayed price is obviously wrong (a technical or data-entry error), we tell you and you can choose between the correct price and cancelling the order, with a full refund of the amount paid.</li>
</ul>
HTML],
    ['preturi-plata', 'Prices, fees and payment', <<<'HTML'
<ul>
  <li>Ticket prices are set by the operators and are shown in the currency displayed next to each price, with VAT included where it applies.</li>
  <li>The viaqui.com <strong>ticketing fee</strong> is added on top of the ticket price. You see it separately in the cart and at checkout, before you pay. Any other cost, if there is one, appears there too: for example the payment processing cost, ticket protection, if you choose it, or the surcharge for the cultural card.</li>
  <li>You pay by card (Visa, Mastercard, Maestro), with Apple Pay or with Google Pay, through the payment processor (currently Stripe), with 3D Secure authentication. Where accepted, you can also pay with a cultural card (Edenred, Pluxee, Up), with the surcharge shown at payment.</li>
  <li>We do not see and do not keep your card details: you enter them directly with the payment processor.</li>
  <li>Promotional codes, gift cards and points are applied before payment, according to their own rules. They cannot be combined unless the cart shows that they can.</li>
  <li>Tax documents are issued as required by law. If you need an invoice in the name of a company, fill in the billing details at checkout.</li>
</ul>
HTML],
    ['bilete', 'The tickets', <<<'HTML'
<ul>
  <li>Tickets are electronic. You receive them by email and you find them in your account. You show them at the entrance on your phone or printed.</li>
  <li>Each ticket has a unique QR code, valid for a single entry. At the first scan the ticket is used, and copies of it are no longer valid. Do not publish the tickets and do not send them to anyone other than the people coming with you.</li>
  <li>For discounted tickets (children, pupils, students, pensioners and so on), the operator may ask for proof at the entrance.</li>
  <li>Follow the operator's rules shown on the activity page: arrival time, minimum age, equipment, safety rules. If you are late, the operator's rules apply.</li>
  <li>Reselling tickets for commercial purposes without the operator's consent is forbidden. Tickets obtained by fraud may be cancelled without compensation.</li>
</ul>
HTML],
    ['anulare-rambursare', 'Cancellation, rescheduling and refunds', <<<HTML
<p><strong>Right of withdrawal.</strong> For tickets to leisure activities with a set date or period, the 14-day right of withdrawal does not apply (Government Emergency Ordinance no. 34/2014 (OUG nr. 34/2014), art. 16 letter l).</p>
<ul>
  <li><strong>If the operator cancels the activity</strong>, you get back the full amount paid for the affected tickets, including the ticketing fee.</li>
  <li><strong>If the activity is rescheduled</strong>, the ticket remains valid for the new date. If you cannot make it then, you can ask for a refund within 14 days of the new date being announced.</li>
  <li><strong>If you cancel</strong>, the operator's cancellation policy, shown on the activity page, applies. If the activity has no such policy, the tickets are not refundable. If you chose ticket protection at checkout, its conditions apply as well.</li>
</ul>
<p>You ask for a cancellation or a refund from the <a href="/contact">contact page</a> or at {$lgMail}, with the order number. The money is returned to the same payment method. How long it takes to appear in your account also depends on your bank. On a refund, the points used in the order come back to you, and the points earned for it are cancelled. Amounts paid with a gift card go back onto the card.</p>
HTML],
    ['carduri-cadou', 'Gift cards', <<<'HTML'
<ul>
  <li>A gift card is valid for 12 months from the date of issue, unless a different period is shown at purchase.</li>
  <li>The balance can be used in one or more orders, until it runs out or until the card expires. You can check the balance at any time on the <a href="/voucher">Check a gift card</a> page.</li>
  <li>A gift card cannot be exchanged for money and is not refundable, except in the situations provided for by law.</li>
  <li>The card code works like cash: keep it safe. We are not liable for its use by someone else to whom you gave it or who found it.</li>
</ul>
HTML],
    ['puncte', 'The points programme', <<<'HTML'
<ul>
  <li>You earn points for paid orders. They appear as "pending" and become available after the activity has taken place.</li>
  <li>You can also receive points on your birthday (if your date of birth is in your account) and for friends invited with your code: you both receive them, after your friend creates an account and makes a purchase, within the limits of the programme.</li>
  <li>What the points are worth, how many you earn, how many you can use on one order and when they expire are shown in your account, under <strong>My points</strong>, and in the cart, before payment.</li>
  <li>With points you reduce the price of tickets or enter viaqui.com competitions, under the rules of each competition.</li>
  <li>Points have no cash value, cannot be transferred to another account and expire after the period shown in your account.</li>
  <li>Points earned for cancelled or refunded orders are withdrawn. Points obtained by fraud (multiple accounts, fake invitations, fictitious orders) are cancelled, and the account may be suspended.</li>
  <li>We may change or end the programme with notice given at least 30 days in advance. You can use the points already available until the announced date.</li>
</ul>
HTML],
    ['recenzii', 'Reviews', <<<'HTML'
<ul>
  <li>Reviews must be honest and must be about your own experience of that activity.</li>
  <li>Offensive language, other people's personal data, advertising and content that breaks the law or the rights of others are not allowed.</li>
  <li>We may moderate, hide or delete reviews that do not follow these rules.</li>
  <li>When you publish a review, you give us the non-exclusive, free right to display it, together with the photos, on the platform and in viaqui.com materials.</li>
</ul>
HTML],
    ['reguli', 'What is not allowed', <<<'HTML'
<ul>
  <li>using the platform for fraud or under a false identity;</li>
  <li>using bots or automated programs to buy tickets or to copy the content of the platform;</li>
  <li>trying to get around the security measures or to disrupt the operation of the platform;</li>
  <li>reselling tickets for commercial purposes without the operator's consent;</li>
  <li>copying or republishing the content of the platform without our consent.</li>
</ul>
HTML],
    ['operatori', 'For operators', <<<'HTML'
<ul>
  <li>You sign up on the <a href="/list-your-venue">List your venue</a> page. We create your operator account on the spot, and you can sign in to it immediately.</li>
  <li>Until a viaqui.com team member approves your application (usually within 24 hours at most), nothing you add to the account is public.</li>
  <li>The commission, the collection and payment of amounts, the reports and the other commercial conditions are set out in the partnership contract, which you sign electronically in your account.</li>
  <li>You are responsible for the accuracy of the information published, for running the activities, for the necessary permits, for the safety of participants, for your tax obligations and for the cancellation policy you display.</li>
  <li>You receive participants' data only for organising the activity and you use it in line with the law.</li>
</ul>
HTML],
    ['proprietate', 'Intellectual property', <<<'HTML'
<p>The design, the texts, the viaqui.com brand, the code and the databases of the platform belong to us or are used by us with the consent of their owners. The photos and descriptions of the activities belong to the operators, who allow us to display them. You may not copy them or use them for another purpose without the owner's consent.</p>
HTML],
    ['raspundere', 'Liability', <<<'HTML'
<ul>
  <li>We do everything in our power to keep the platform running without interruption, but there may be pauses for maintenance or technical problems beyond our control.</li>
  <li>We are not liable for the running of the activity, for which the operator is responsible, for the information published by operators or for losses caused by sharing tickets, codes or your password with other people.</li>
  <li>Nothing in these terms limits your consumer rights under the law, or our liability where the law does not allow it to be limited.</li>
</ul>
HTML],
    ['forta-majora', 'Force majeure', <<<'HTML'
<p>Neither we nor the operators are liable for a failure to perform obligations caused by a force majeure event (for example natural disasters, restrictions imposed by the authorities, epidemics). For activities cancelled for this reason, the cancellation and refund rules above apply.</p>
HTML],
    ['reclamatii', 'Complaints and dispute resolution', <<<HTML
<p>If you are unhappy with anything, write to us from the <a href="/contact">contact page</a> or at {$lgMail}. We reply as soon as we can, within 30 days at most.</p>
<p>If we do not reach a solution, you can contact the National Authority for Consumer Protection of Romania (Autoritatea Națională pentru Protecția Consumatorilor, ANPC, <a href="https://anpc.ro" rel="noopener" target="_blank">anpc.ro</a>) or use the alternative dispute resolution procedure (Soluționarea Alternativă a Litigiilor, <a href="https://anpc.ro/ce-este-sal/" rel="noopener" target="_blank">SAL</a>).</p>
<p>These terms are governed by Romanian law. Disputes that are not settled amicably fall under the jurisdiction of the courts of Romania.</p>
HTML],
    ['modificari', 'Changes to the terms', <<<'HTML'
<p>We may update these terms when the platform or the law changes. The date of the last update appears at the top of the page. Orders already placed are subject to the terms in force on the date of the order. We tell you about important changes by email or through a message on the platform.</p>
HTML],
];

$pageTitle = v2_t('Terms and conditions');
$pageDescription = v2_t('The rules of viaqui.com: ordering and payment, tickets, cancellation and refunds, gift cards, the points programme, reviews and the conditions for operators.');
$canonicalUrl = SITE_URL . '/terms';

$v2Styles = ['legal.css'];
$v2Scripts = ['legal.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';

v2_legal_render([
    'key' => 'terms',
    'kicker' => v2_t('Terms and conditions'),
    'title' => v2_t('The rules of viaqui.com, in plain words'),
    'lead' => v2_t('How you buy, how you use your tickets, what happens on cancellation and what rules apply to gift cards, points and reviews. Plus what applies to the operators who list their activities.'),
    'sections' => $lgSections,
]);

include __DIR__ . '/includes/v2/footer.php';
