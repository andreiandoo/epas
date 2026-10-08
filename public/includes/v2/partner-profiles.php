<?php
/**
 * viaqui.com v2: the personalised operator landing copy (?tip=<type>&loc=<name>), shared by /devino-partener and
 * /partners. v2_partner_profiles($accent) gives the 12 activity profiles, with the commission accent (HTML of the
 * page) inside each subtitle; V2_PARTNER_ALIASES maps the ?tip values in use onto a profile. The scripts only ever
 * copy this server copy into the hero, never text from the address (except the ?loc name, set as text).
 *
 * Each profile carries whole sentences, so they can be translated: 'chip' (the line above the headline) and 'cta' (the
 * signup button) are written out per profile instead of being glued to 'label' in the script.
 */

function v2_partner_profiles(string $accent): array
{
    $vars = ['accent' => $accent];
    return [
        'escape' => ['label' => v2_t('escape room'), 'chip' => v2_t('Ticketing & booking for escape rooms'), 'cta' => v2_t('Put your escape room online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your escape rooms.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('60-minute slots, capacity per room, detailed bookings and an app that scans offline. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'muzeu' => ['label' => v2_t('museum'), 'chip' => v2_t('Ticketing & booking for museums'), 'cta' => v2_t('Put your museum online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your museum.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Admission tickets by day and time slot, guided visits and packages for school groups, plus tax documents issued automatically. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'parc-distractii' => ['label' => v2_t('amusement park'), 'chip' => v2_t('Ticketing & booking for amusement parks'), 'cta' => v2_t('Put your amusement park online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your park.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Admission tickets, passes, add-ons for attractions and rentals, all in one system, online and on site. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'parc-aventura' => ['label' => v2_t('adventure park'), 'chip' => v2_t('Ticketing & booking for adventure parks'), 'cta' => v2_t('Put your adventure park online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your adventure trails.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Trails by difficulty level, slots by capacity, equipment rental and offline scanning out in the field. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'natura' => ['label' => v2_t('outdoor experience'), 'chip' => v2_t('Ticketing & booking for outdoor experiences'), 'cta' => v2_t('Put your outdoor experience online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your outdoor experiences.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Guided tours, trails and outdoor activities with slots by day and hour, plus offline scanning where there is no signal. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'acvarii-zoo' => ['label' => v2_t('zoo / aquarium'), 'chip' => v2_t('Ticketing & booking for zoos and aquariums'), 'cta' => v2_t('Put your zoo or aquarium online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your zoo / aquarium.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Admission tickets by day and time slot, animal encounters and family packages, online and at the ticket office. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'ateliere' => ['label' => v2_t('creative workshop'), 'chip' => v2_t('Ticketing & booking for creative workshops'), 'cta' => v2_t('Put your creative workshop online'), 'h1a' => v2_t('Sell places'), 'h1b' => v2_t('at your creative workshops.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Sessions with limited places, material packages and booking by time slot, with no overselling. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'tururi' => ['label' => v2_t('sightseeing tour'), 'chip' => v2_t('Ticketing & booking for sightseeing tours'), 'cta' => v2_t('Put your tour online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your guided tours.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('City walks and tours with timed departures, capacity per departure and tickets on the phone. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'educatie' => ['label' => v2_t('educational programme'), 'chip' => v2_t('Ticketing & booking for educational programmes'), 'cta' => v2_t('Put your educational programme online'), 'h1a' => v2_t('Sell places'), 'h1b' => v2_t('on your educational programmes.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('STEM activities and interactive lessons booked by class and group, plus tax documents generated automatically. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'familie' => ['label' => v2_t('family activity'), 'chip' => v2_t('Ticketing & booking for family activities'), 'cta' => v2_t('Put your family activity online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your family activities.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Family packages, prices by age group and booking by slot, online and on site. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'corporate' => ['label' => v2_t('corporate experience'), 'chip' => v2_t('Ticketing & booking for corporate experiences'), 'cta' => v2_t('Put your corporate experience online'), 'h1a' => v2_t('Sell places'), 'h1b' => v2_t('on your team experiences.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Group packages for team building and private events, with automatic invoicing. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
        'cultura' => ['label' => v2_t('cultural institution'), 'chip' => v2_t('Ticketing & booking for cultural institutions'), 'cta' => v2_t('Put your cultural institution online'), 'h1a' => v2_t('Sell tickets'), 'h1b' => v2_t('to your cultural events.'), 'h1c' => v2_t('Your price stays yours.'), 'sub' => v2_t('Seated or general admission tickets, by date and time slot, with tax documents issued automatically. The commission of {accent} doesn\'t touch your price: you keep the price you set.', $vars)],
    ];
}

const V2_PARTNER_ALIASES = [
    'escape' => 'escape', 'escape-room' => 'escape', 'escape-rooms' => 'escape',
    'muzeu' => 'muzeu', 'muzee' => 'muzeu', 'muzee-expozitii' => 'muzeu', 'museum' => 'muzeu',
    'parc-distractii' => 'parc-distractii', 'parc-distracții' => 'parc-distractii', 'distractii' => 'parc-distractii', 'parcuri-de-distractii' => 'parc-distractii',
    'parc-aventura' => 'parc-aventura', 'aventura' => 'parc-aventura', 'parcuri-de-aventura' => 'parc-aventura',
    'natura' => 'natura', 'natura-outdoor' => 'natura', 'outdoor' => 'natura',
    'acvarii-zoo' => 'acvarii-zoo', 'zoo' => 'acvarii-zoo', 'acvariu' => 'acvarii-zoo',
    'ateliere' => 'ateliere', 'atelier' => 'ateliere', 'ateliere-experiente-creative' => 'ateliere',
    'tururi' => 'tururi', 'tur' => 'tururi', 'tururi-experiente-turistice' => 'tururi',
    'educatie' => 'educatie', 'educatie-invatare-experientiala' => 'educatie', 'stem' => 'educatie',
    'familie' => 'familie', 'familie-copii' => 'familie', 'copii' => 'familie',
    'corporate' => 'corporate', 'corporate-grupuri' => 'corporate',
    'cultura' => 'cultura', 'cultura-arta' => 'cultura', 'arta' => 'cultura',
];
