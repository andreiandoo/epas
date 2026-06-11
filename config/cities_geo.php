<?php

/**
 * Cities geocoding dictionary — top RO + CEE cities cu lat/lng hardcoded.
 *
 * Folosit în Fan CRM pentru heatmap geografic. marketplace_customers stochează
 * doar `city` ca text, fără coordonate. Ne uităm aici la prima rulare să
 * geo-localizăm fanii fără dependențe externe (Nominatim, Mapbox).
 *
 * Match strategy: case-insensitive + diacritics-stripped. "BUCURESTI" sau
 * "bucurești" → match cu "București".
 *
 * Pentru orașe necunoscute → NULL (excluse din hartă, contate în alte tab-uri).
 */

return [
    // ============== ROMANIA ==============
    'București' => ['lat' => 44.4268, 'lng' => 26.1025, 'country' => 'RO'],
    'Cluj-Napoca' => ['lat' => 46.7712, 'lng' => 23.6236, 'country' => 'RO'],
    'Timișoara' => ['lat' => 45.7489, 'lng' => 21.2087, 'country' => 'RO'],
    'Iași' => ['lat' => 47.1585, 'lng' => 27.6014, 'country' => 'RO'],
    'Constanța' => ['lat' => 44.1598, 'lng' => 28.6348, 'country' => 'RO'],
    'Brașov' => ['lat' => 45.6427, 'lng' => 25.5887, 'country' => 'RO'],
    'Craiova' => ['lat' => 44.3302, 'lng' => 23.7949, 'country' => 'RO'],
    'Galați' => ['lat' => 45.4353, 'lng' => 28.0080, 'country' => 'RO'],
    'Ploiești' => ['lat' => 44.9469, 'lng' => 26.0128, 'country' => 'RO'],
    'Oradea' => ['lat' => 47.0722, 'lng' => 21.9217, 'country' => 'RO'],
    'Brăila' => ['lat' => 45.2692, 'lng' => 27.9574, 'country' => 'RO'],
    'Arad' => ['lat' => 46.1866, 'lng' => 21.3123, 'country' => 'RO'],
    'Pitești' => ['lat' => 44.8566, 'lng' => 24.8692, 'country' => 'RO'],
    'Sibiu' => ['lat' => 45.7983, 'lng' => 24.1256, 'country' => 'RO'],
    'Bacău' => ['lat' => 46.5670, 'lng' => 26.9146, 'country' => 'RO'],
    'Târgu Mureș' => ['lat' => 46.5419, 'lng' => 24.5586, 'country' => 'RO'],
    'Baia Mare' => ['lat' => 47.6592, 'lng' => 23.5680, 'country' => 'RO'],
    'Buzău' => ['lat' => 45.1500, 'lng' => 26.8333, 'country' => 'RO'],
    'Botoșani' => ['lat' => 47.7458, 'lng' => 26.6694, 'country' => 'RO'],
    'Satu Mare' => ['lat' => 47.7917, 'lng' => 22.8852, 'country' => 'RO'],
    'Râmnicu Vâlcea' => ['lat' => 45.0997, 'lng' => 24.3692, 'country' => 'RO'],
    'Suceava' => ['lat' => 47.6635, 'lng' => 26.2732, 'country' => 'RO'],
    'Piatra Neamț' => ['lat' => 46.9275, 'lng' => 26.3708, 'country' => 'RO'],
    'Drobeta-Turnu Severin' => ['lat' => 44.6269, 'lng' => 22.6566, 'country' => 'RO'],
    'Târgoviște' => ['lat' => 44.9347, 'lng' => 25.4569, 'country' => 'RO'],
    'Focșani' => ['lat' => 45.6967, 'lng' => 27.1864, 'country' => 'RO'],
    'Bistrița' => ['lat' => 47.1333, 'lng' => 24.5000, 'country' => 'RO'],
    'Tulcea' => ['lat' => 45.1716, 'lng' => 28.7900, 'country' => 'RO'],
    'Reșița' => ['lat' => 45.3008, 'lng' => 21.8889, 'country' => 'RO'],
    'Slatina' => ['lat' => 44.4317, 'lng' => 24.3722, 'country' => 'RO'],
    'Călărași' => ['lat' => 44.2058, 'lng' => 27.3306, 'country' => 'RO'],
    'Alba Iulia' => ['lat' => 46.0667, 'lng' => 23.5833, 'country' => 'RO'],
    'Giurgiu' => ['lat' => 43.9037, 'lng' => 25.9699, 'country' => 'RO'],
    'Deva' => ['lat' => 45.8833, 'lng' => 22.9000, 'country' => 'RO'],
    'Hunedoara' => ['lat' => 45.7500, 'lng' => 22.9000, 'country' => 'RO'],
    'Zalău' => ['lat' => 47.1903, 'lng' => 23.0578, 'country' => 'RO'],
    'Sfântu Gheorghe' => ['lat' => 45.8633, 'lng' => 25.7889, 'country' => 'RO'],
    'Bârlad' => ['lat' => 46.2278, 'lng' => 27.6675, 'country' => 'RO'],
    'Vaslui' => ['lat' => 46.6406, 'lng' => 27.7281, 'country' => 'RO'],
    'Roman' => ['lat' => 46.9211, 'lng' => 26.9333, 'country' => 'RO'],
    'Turda' => ['lat' => 46.5710, 'lng' => 23.7857, 'country' => 'RO'],
    'Mediaș' => ['lat' => 46.1664, 'lng' => 24.3530, 'country' => 'RO'],
    'Slobozia' => ['lat' => 44.5644, 'lng' => 27.3617, 'country' => 'RO'],
    'Alexandria' => ['lat' => 43.9667, 'lng' => 25.3333, 'country' => 'RO'],
    'Voluntari' => ['lat' => 44.4880, 'lng' => 26.1764, 'country' => 'RO'],
    'Lugoj' => ['lat' => 45.6889, 'lng' => 21.9036, 'country' => 'RO'],
    'Mioveni' => ['lat' => 44.9556, 'lng' => 24.9472, 'country' => 'RO'],
    'Mangalia' => ['lat' => 43.8167, 'lng' => 28.5833, 'country' => 'RO'],
    'Onești' => ['lat' => 46.2486, 'lng' => 26.7669, 'country' => 'RO'],
    'Năvodari' => ['lat' => 44.3158, 'lng' => 28.6131, 'country' => 'RO'],

    // ============== CEE — orașe relevante pentru turnee ==============
    'Sofia' => ['lat' => 42.6977, 'lng' => 23.3219, 'country' => 'BG'],
    'Plovdiv' => ['lat' => 42.1354, 'lng' => 24.7453, 'country' => 'BG'],
    'Varna' => ['lat' => 43.2141, 'lng' => 27.9147, 'country' => 'BG'],
    'Burgas' => ['lat' => 42.5048, 'lng' => 27.4626, 'country' => 'BG'],
    'Budapest' => ['lat' => 47.4979, 'lng' => 19.0402, 'country' => 'HU'],
    'Debrecen' => ['lat' => 47.5316, 'lng' => 21.6273, 'country' => 'HU'],
    'Belgrade' => ['lat' => 44.7866, 'lng' => 20.4489, 'country' => 'RS'],
    'Novi Sad' => ['lat' => 45.2671, 'lng' => 19.8335, 'country' => 'RS'],
    'Chișinău' => ['lat' => 47.0105, 'lng' => 28.8638, 'country' => 'MD'],
    'Bălți' => ['lat' => 47.7615, 'lng' => 27.9295, 'country' => 'MD'],
    'Warsaw' => ['lat' => 52.2297, 'lng' => 21.0122, 'country' => 'PL'],
    'Kraków' => ['lat' => 50.0647, 'lng' => 19.9450, 'country' => 'PL'],
    'Praha' => ['lat' => 50.0755, 'lng' => 14.4378, 'country' => 'CZ'],
    'Brno' => ['lat' => 49.1951, 'lng' => 16.6068, 'country' => 'CZ'],
    'Bratislava' => ['lat' => 48.1486, 'lng' => 17.1077, 'country' => 'SK'],
    'Wien' => ['lat' => 48.2082, 'lng' => 16.3738, 'country' => 'AT'],
    'Vienna' => ['lat' => 48.2082, 'lng' => 16.3738, 'country' => 'AT'],
    'Athens' => ['lat' => 37.9838, 'lng' => 23.7275, 'country' => 'GR'],
    'Thessaloniki' => ['lat' => 40.6401, 'lng' => 22.9444, 'country' => 'GR'],
    'Istanbul' => ['lat' => 41.0082, 'lng' => 28.9784, 'country' => 'TR'],
    'Ljubljana' => ['lat' => 46.0569, 'lng' => 14.5058, 'country' => 'SI'],
    'Zagreb' => ['lat' => 45.8150, 'lng' => 15.9819, 'country' => 'HR'],
    'Sarajevo' => ['lat' => 43.8563, 'lng' => 18.4131, 'country' => 'BA'],

    // ============== Diaspora hub-uri (RO emigrants) ==============
    'London' => ['lat' => 51.5074, 'lng' => -0.1278, 'country' => 'GB'],
    'Madrid' => ['lat' => 40.4168, 'lng' => -3.7038, 'country' => 'ES'],
    'Barcelona' => ['lat' => 41.3851, 'lng' => 2.1734, 'country' => 'ES'],
    'Roma' => ['lat' => 41.9028, 'lng' => 12.4964, 'country' => 'IT'],
    'Milano' => ['lat' => 45.4642, 'lng' => 9.1900, 'country' => 'IT'],
    'Torino' => ['lat' => 45.0703, 'lng' => 7.6869, 'country' => 'IT'],
    'München' => ['lat' => 48.1351, 'lng' => 11.5820, 'country' => 'DE'],
    'Berlin' => ['lat' => 52.5200, 'lng' => 13.4050, 'country' => 'DE'],
    'Paris' => ['lat' => 48.8566, 'lng' => 2.3522, 'country' => 'FR'],
    'Bruxelles' => ['lat' => 50.8503, 'lng' => 4.3517, 'country' => 'BE'],
    'Brussels' => ['lat' => 50.8503, 'lng' => 4.3517, 'country' => 'BE'],
];
