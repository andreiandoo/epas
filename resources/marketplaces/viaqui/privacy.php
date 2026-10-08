<?php
/**
 * Privacy policy: /privacy (v2 design, includes/v2/legal.php layout).
 *
 * What TIXELLO S.R.L. collects on viaqui.com and why, who else gets it (the operators of the activities, the
 * processors), how long it stays, the visitor's rights. Written from what the platform does: customer accounts and
 * orders, tickets and check-in, points, referrals and gift cards, reviews, support, newsletter, the venue signup and
 * operator accounts (company data from ANAF), cookies and the tracking allowed in the consent banner. Cookies have
 * their own page (/cookies); this one links to it.
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
    ['cine-suntem', 'Who we are and what this policy covers', <<<HTML
<p>The viaqui.com platform is operated by <strong>TIXELLO S.R.L.</strong>, with the identification details above. For the data processed through the platform (accounts, orders, tickets, the points programme, support, operator signup), TIXELLO S.R.L. is the <strong>data controller</strong> within the meaning of Regulation (EU) 2016/679 (GDPR).</p>
<p>The activities listed on viaqui.com are offered by their operators (venues, organisers, guides). When you buy a ticket, the operator of the activity receives the data it needs in order to welcome you and becomes, for that data, an independent data controller (see the section "Who we share data with").</p>
<p>For any question about your data, write to us at {$lgMail}, with the subject "Personal data".</p>
HTML],
    ['ce-date', 'What data we collect', <<<'HTML'
<ul>
  <li><strong>The customer account:</strong> your name, email address, phone number, password (kept only in encrypted, irreversible form), communication preferences and, if you add it, your date of birth (for the birthday bonus).</li>
  <li><strong>Orders:</strong> the activities, the date and time booked, the type and number of tickets, the names of the ticket holders (if you fill them in), the billing details (name or company, tax identification number, address), the amounts, the payment method and the payment status. You enter your card details directly with the payment processor: we do not see them and do not keep them. For saved cards we receive from the processor only the card type, the last digits and the expiry date.</li>
  <li><strong>Access to the activity:</strong> the status of the ticket and the moment it was scanned at the entrance.</li>
  <li><strong>Points, invitations and gift cards:</strong> your points balance and history, your invitation code and the accounts created through it, the balance and use of gift cards.</li>
  <li><strong>Reviews:</strong> the rating, the text and the photos you publish.</li>
  <li><strong>Support:</strong> the messages sent through the contact form, by email or through the support tickets in your account.</li>
  <li><strong>Newsletter:</strong> your email address, preferences and the date you subscribed.</li>
  <li><strong>Operators (partners):</strong> the data in the signup form (name, email, phone, the name and city of the venue, the website, what you need, your message), the tax identification number (CUI) and the company data taken from the public register of the Romanian National Agency for Fiscal Administration (ANAF), the operator account data (representative, bank details, uploaded documents, the contract) and the partner pages visited before signup (through a session identifier and the parameters of the campaign you came from).</li>
  <li><strong>Technical data:</strong> IP address, browser and device type, pages visited, security and error logs, cookies and similar technologies, described in the <a href="/cookies">Cookie policy</a>.</li>
</ul>
<p>The data comes from you, from your use of the platform, from the payment processor (payment confirmation), from the operator of the activity (ticket scanning) and, for companies, from the public ANAF register.</p>
HTML],
    ['scopuri', 'Why we use the data and on what legal basis', <<<'HTML'
<div class="lg-table" role="region" aria-label="Purposes and legal bases" tabindex="0">
<table>
  <thead><tr><th scope="col">Purpose</th><th scope="col">Legal basis (art. 6 GDPR)</th></tr></thead>
  <tbody>
    <tr><td>The account, sign-in and its settings</td><td>Performance of the contract (para. 1 letter b)</td></tr>
    <tr><td>Orders, payment, issuing and sending tickets, access to the activity</td><td>Performance of the contract (letter b)</td></tr>
    <tr><td>Tax documents and accounting records</td><td>Legal obligation (letter c)</td></tr>
    <tr><td>Support, complaints, cancellations and refunds</td><td>Performance of the contract (letter b) and legal obligation (letter c)</td></tr>
    <tr><td>The points programme, invitations and gift cards</td><td>Performance of the contract (letter b)</td></tr>
    <tr><td>Newsletter and commercial messages</td><td>Consent (letter a), which you can withdraw at any time</td></tr>
    <tr><td>Personalised recommendations on the site</td><td>Consent to personalisation cookies (letter a)</td></tr>
    <tr><td>Analytics (Google Analytics) and campaigns (Meta, Google Ads and TikTok pixels)</td><td>Consent to analytics and marketing cookies (letter a)</td></tr>
    <tr><td>Security, prevention of fraud and abuse, aggregate audience measurement, improving the platform</td><td>Legitimate interest (letter f)</td></tr>
    <tr><td>Operator signup, the operator account and the partnership contract</td><td>Steps prior to entering into the contract and its performance (letter b)</td></tr>
    <tr><td>Checking the company in the public ANAF register</td><td>Legitimate interest (letter f): we work only with real, active companies</td></tr>
    <tr><td>Establishing, exercising or defending legal claims in court</td><td>Legitimate interest (letter f)</td></tr>
  </tbody>
</table>
</div>
<p>We do not make decisions based solely on automated processing that produce legal effects concerning you. Activity recommendations are simple suggestions.</p>
HTML],
    ['destinatari', 'Who we share data with', <<<'HTML'
<p><strong>We do not sell your data.</strong> We share it only as far as needed, as follows:</p>
<ul>
  <li><strong>The operator of the activity</strong> you bought for: your name and the names of the ticket holders, the contact details in the order, the tickets, the date and time of the booking and the check-in status. The operator uses them to organise the activity and for its legal obligations.</li>
  <li><strong>The payment processor</strong> (currently Stripe) and, for cultural cards, the card issuer, for collecting the payment and keeping it secure.</li>
  <li><strong>Infrastructure providers:</strong> hosting and servers, Cloudflare (page delivery and protection against attacks), transactional email providers (for example Brevo), map services (CARTO and OpenStreetMap receive the IP address when a map loads).</li>
  <li><strong>Analytics and advertising</strong> (Google, Meta, TikTok), only if you have accepted analytics or marketing cookies. If the operator of the activity uses the Meta Conversions API, the confirmation of a purchase may reach Meta with the contact details irreversibly encrypted (hashed), for measuring the operator's campaigns.</li>
  <li><strong>GetYourGuide</strong>, a partner whose offers appear on some pages: when you open a GetYourGuide offer, their privacy policy applies.</li>
  <li><strong>Advisers</strong> (accounting, legal), bound by confidentiality.</li>
  <li><strong>Authorities</strong> (for example ANAF, courts, investigative bodies), when the law requires us to.</li>
</ul>
<p>The providers that process data on our behalf do so under a contract, only on our instructions and with appropriate security measures.</p>
HTML],
    ['transferuri', 'Transfers outside the European Economic Area', <<<'HTML'
<p>Some providers (for example Stripe, Google, Meta, Cloudflare) may also process data outside the European Economic Area, including in the United States. Transfers are made only with the safeguards required by the GDPR: the adequacy decision of the European Commission (the EU-U.S. Data Privacy Framework, for certified companies) or standard contractual clauses approved by the Commission.</p>
HTML],
    ['pastrare', 'How long we keep the data', <<<'HTML'
<ul>
  <li><strong>The customer account:</strong> for as long as it is active. If you delete it, the profile data is deleted or anonymised, and the orders stay in the accounting records.</li>
  <li><strong>Orders, tax and accounting documents:</strong> up to 10 years, as required by financial and accounting legislation.</li>
  <li><strong>Tickets and scans:</strong> for as long as we keep the order they belong to.</li>
  <li><strong>Support and complaints:</strong> up to 3 years after the request is closed.</li>
  <li><strong>Newsletter:</strong> until you unsubscribe (the link is in every email).</li>
  <li><strong>Points:</strong> until they expire, under the rules of the programme.</li>
  <li><strong>Operator signups without an active account:</strong> up to 2 years after the last interaction.</li>
  <li><strong>Technical and security logs:</strong> up to 12 months.</li>
  <li><strong>Cookies:</strong> as set out in the <a href="/cookies">Cookie policy</a>.</li>
</ul>
<p>At the end, the data is deleted or anonymised, except for data that the law requires us to keep longer or data needed in an ongoing dispute.</p>
HTML],
    ['drepturi', 'Your rights', <<<HTML
<p>Under the GDPR, you have the right:</p>
<ul>
  <li>to find out what data we hold about you and to receive a copy of it (access);</li>
  <li>to correct data that is wrong or incomplete (rectification);</li>
  <li>to ask for it to be deleted, when there is no longer a legal reason for us to keep it (erasure);</li>
  <li>to ask for processing to be restricted, in the cases provided for by law;</li>
  <li>to receive the data in a structured format and to pass it on to another controller (portability);</li>
  <li>to object to processing based on legitimate interest and, at any time, to direct marketing;</li>
  <li>to withdraw your consent, without affecting the processing carried out until then;</li>
  <li>to lodge a complaint with the Romanian National Supervisory Authority for Personal Data Processing (Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal, ANSPDCP), B-dul G-ral. Gheorghe Magheru nr. 28-30, sector 1, Bucharest, <a href="https://www.dataprotection.ro" rel="noopener" target="_blank">www.dataprotection.ro</a>.</li>
</ul>
<p>You can do much of this directly from your account: under <strong>Settings</strong> you change your details, download your data and delete your account. For the rest, write to us at {$lgMail}. We reply within one month at most; for complex requests the deadline can be extended by a further two months, in which case we let you know. We may ask for additional information to confirm that the request comes from you.</p>
HTML],
    ['securitate', 'How we protect the data', <<<'HTML'
<p>We use encrypted connections (HTTPS), passwords kept only in encrypted form, role-based access to data, two-step sign-in (optional, from Settings), payments through PCI DSS certified processors, backups and access monitoring. If an incident that may affect your data does occur, we notify the ANSPDCP within 72 hours and we also notify you when the risk is high.</p>
<p>You help us too: keep your password to yourself and do not publish your tickets or QR codes, because anyone who has them can get in instead of you.</p>
HTML],
    ['minori', 'Minors', <<<'HTML'
<p>A customer account can be created from the age of 16. For younger children, tickets are bought by a parent or guardian, who fills in only the necessary details about the child (for example the name on the ticket or the age category).</p>
HTML],
    ['cookies', 'Cookies and similar technologies', <<<'HTML'
<p>We use cookies that are necessary for the platform to work and, only with your consent, analytics, personalisation and marketing cookies. The details and the settings are in the <a href="/cookies">Cookie policy</a>. You can change your choice at any time, from the <strong>Cookie settings</strong> link at the bottom of any page.</p>
HTML],
    ['modificari', 'Changes to this policy', <<<'HTML'
<p>We update the policy when what we do with the data, or the law, changes. The date of the last update appears at the top of the page. We tell you about important changes by email or through a message on the platform.</p>
HTML],
];

$pageTitle = v2_t('Privacy policy');
$pageDescription = v2_t('What data viaqui.com collects, why, who it shares it with, how long it keeps it and how you exercise your rights. Operated by TIXELLO S.R.L.');
$canonicalUrl = SITE_URL . '/privacy';

$v2Styles = ['legal.css'];
$v2Scripts = ['legal.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';

v2_legal_render([
    'key' => 'privacy',
    'kicker' => v2_t('Privacy policy'),
    'title' => v2_t('How we look after your data'),
    'lead' => v2_t('In short: we use your data only to make your account, orders and tickets work, we do not sell it, and analytics and advertising start only with your consent. All the details are below.'),
    'sections' => $lgSections,
]);

include __DIR__ . '/includes/v2/footer.php';
