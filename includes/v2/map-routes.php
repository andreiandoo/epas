<?php
/**
 * bilete.online v2: the editorial routes behind /trasee and /trasee/{slug}.
 *
 * A route is a title, a few paragraphs and an ordered list of attraction slugs. Everything else —
 * the names, the cities, the coordinates, the photos, the distance between stops — is resolved by
 * bin/build-map-data.php into assets/v2/data/atractii.summary.json (`routes`), so a route never
 * repeats what the catalogue already knows and never goes stale when a place is renamed.
 *
 * Every slug below was checked against the dataset; the builder refuses to write a route whose
 * stops it cannot resolve, so a typo here fails the build instead of shipping a broken page.
 *
 *   title     the route's name
 *   lead      one sentence, used under the heading and as the card's text
 *   intro     the opening paragraph of the route page
 *   pace      what the stops realistically add up to, in the author's words
 *   emoji     the marker on the card
 *   stops     attraction slugs, in the order they are meant to be visited
 */

const MAP_ROUTES = [
    'drumul-castelelor-din-transilvania' => [
        'title' => 'Drumul castelelor din Transilvania',
        'emoji' => '🏰',
        'pace'  => '2 zile',
        'lead'  => 'De la curtea Bánffy de lângă Cluj până la cetatea de pe stânca Devei, prin reședințele nobiliare de pe Mureș.',
        'intro' => 'Traseul leagă castelele și curțile nobiliare din inima Transilvaniei, majoritatea ridicate de familii maghiare între secolele XVI și XIX. Se coboară dinspre Cluj spre valea Mureșului și se termină la Deva, unde cetatea medievală stă pe un con vulcanic deasupra orașului. Unele reședințe sunt restaurate și primesc vizitatori, altele se văd deocamdată doar din curte.',
        'stops' => [
            'castelul-banffy-de-la-bontida-bontida',
            'castelul-kemeny-din-brancovenesti-brancovenesti',
            'castelul-teleki-din-gornesti-gornesti',
            'castelul-bethlen-din-cris-danes',
            'ansamblul-castelului-bethlen-din-sanmiclaus-sona',
            'cetatea-deva-deva',
        ],
    ],
    'manastirile-din-bucovina' => [
        'title' => 'Mănăstirile din Bucovina',
        'emoji' => '⛪',
        'pace'  => '2 zile',
        'lead'  => 'Voroneț, Humor, Moldovița, Sucevița și încă trei mănăstiri, în ordinea în care se leagă pe drum.',
        'intro' => 'Cel mai cunoscut circuit religios din România: mănăstirile de secol XV-XVI din nordul Moldovei, câteva dintre ele pictate pe exterior. Traseul pornește din apropierea Sucevei, urcă spre Rădăuți și Putna, trece munții spre Moldovița și coboară prin Humor la Voroneț. Distanțele sunt mici, dar drumurile de munte între Sucevița și Moldovița cer timp.',
        'stops' => [
            'manastirea-dragomirna-mitocu-dragomirnei',
            'manastirea-bogdana-radauti',
            'colectia-muzeala-a-manastirii-putna-putna',
            'manastirea-sucevita-sucevita',
            'manastirea-moldovita-vatra-moldovitei',
            'manastirea-humor-manastirea-humorului',
            'manastirea-voronet-voronet',
        ],
    ],
    'cetatile-si-bisericile-fortificate-sasesti' => [
        'title' => 'Cetățile și bisericile fortificate săsești',
        'emoji' => '🗿',
        'pace'  => '2 zile',
        'lead'  => 'Prejmer, Viscri, Biertan și satele fortificate dintre Brașov și Alba.',
        'intro' => 'Satele săsești și-au fortificat bisericile ca să aibă unde se retrage în fața invaziilor: ziduri groase, turnuri de apărare și cămări de provizii în incintă. Traseul traversează sudul Transilvaniei de la est la vest, prin unele dintre cele mai bine păstrate astfel de ansambluri. Multe sunt în sate mici, cu drumuri secundare între ele.',
        'stops' => [
            'cetatea-taraneasca-prejmer-prejmer',
            'ansamblul-bisericii-evanghelice-fortificate-ungra',
            'biserica-fortificata-din-viscri-viscri',
            'biserica-fortificata-din-biertan-biertan',
            'biserica-fortificata-din-metis-metis',
            'biserica-evanghelica-fortificata-bazna',
            'biserica-evanghelica-calnic-calnic',
        ],
    ],
    'valea-prahovei-intr-o-zi' => [
        'title' => 'Valea Prahovei într-o zi',
        'emoji' => '🏔️',
        'pace'  => 'o zi',
        'lead'  => 'Mănăstirea Sinaia, castelele regale și o cascadă, toate la câțiva kilometri unul de altul.',
        'intro' => 'Cel mai scurt traseu de pe listă și singurul care se face comod într-o zi, fără să schimbi baza. Totul e înșirat pe câțiva kilometri de-a lungul văii, între Sinaia și Bușteni, iar de la mănăstire până la Peleș se merge pe jos prin parc.',
        'stops' => [
            'manastirea-sinaia-sinaia',
            'parcul-dimitrie-ghica-din-sinaia-sinaia',
            'castelul-peles-sinaia',
            'castelul-foisor-sinaia',
            'cascada-urlatoarea-busteni',
            'casa-memoriala-cezar-petrescu-busteni',
        ],
    ],
    'salinele-romaniei' => [
        'title' => 'Salinele României',
        'emoji' => '⛏️',
        'pace'  => 'de bifat pe rând',
        'lead'  => 'Patru mine de sare deschise vizitatorilor, din Bucovina până în Oltenia.',
        'intro' => 'Spre deosebire de celelalte trasee, acesta nu se parcurge dintr-o bucată: salinele sunt la sute de kilometri una de alta, în patru colțuri de țară. E o listă de bifat pe rând, când ajungi prin zonă — utilă mai ales vara și în zilele ploioase, pentru că în subteran temperatura e aceeași tot anul.',
        'stops' => [
            'salina-cacica-cacica',
            'salina-targu-ocna-targu-ocna',
            'salina-unirea-slanic',
            'muzeul-sarii-din-slanic-prahova-slanic',
            'salina-ocnele-mari-ocnele-mari',
        ],
    ],
    'dobrogea-antica' => [
        'title' => 'Dobrogea antică',
        'emoji' => '🏛️',
        'pace'  => '2 zile',
        'lead'  => 'Cetăți grecești și romane, de la Dunăre până la mare, cele mai vechi urme construite din țară.',
        'intro' => 'Dobrogea a fost graniță a Imperiului Roman, iar de-a lungul Dunării și pe malul mării au rămas cetăți, castre și bazilici. Traseul coboară dinspre nord, din zona Măcin–Isaccea, trece prin Deltă la Halmyris, ajunge la Histria pe malul lagunei și se închide în interior, la complexul rupestru de la Murfatlar.',
        'stops' => [
            'dinogetia-jijila',
            'castrul-roman-noviodunum-isaccea',
            'halmyris-murighiol',
            'histria-istria',
            'capidava-capidava',
            'complexul-rupestru-basarabi-murfatlar-murfatlar',
        ],
    ],
    'bisericile-de-lemn-din-tara-chioarului' => [
        'title' => 'Bisericile de lemn din Țara Chioarului',
        'emoji' => '🪵',
        'pace'  => 'o zi',
        'lead'  => 'Șase biserici de lemn din satele de deal ale Maramureșului, toate la câțiva kilometri una de alta.',
        'intro' => 'Țara Chioarului, în sudul Maramureșului, a păstrat un grup compact de biserici de lemn din secolele XVII-XVIII, cu turle înalte și pridvor sculptat. Sunt în sate mici, la distanțe scurte, ceea ce face zona mai ușor de parcurs într-o zi decât Maramureșul istoric din nord.',
        'stops' => [
            'biserica-de-lemn-din-buzesti-buzesti',
            'biserica-de-lemn-din-posta-remetea-chioarului',
            'biserica-de-lemn-din-berchez-remetea-chioarului',
            'biserica-de-lemn-din-remecioara-remetea-chioarului',
            'biserica-de-lemn-sfintii-arhangheli-din-varai-valea-chioarului',
            'biserica-de-lemn-din-arduzel-ulmeni',
        ],
    ],
    'bucurestiul-in-palate' => [
        'title' => 'Bucureștiul în palate',
        'emoji' => '🏛️',
        'pace'  => 'o după-amiază, pe jos',
        'lead'  => 'De la Ateneu până la Bursă, o plimbare prin palatele Căii Victoriei și ale centrului vechi.',
        'intro' => 'Aproape tot traseul se face pe jos, pe un singur ax: Calea Victoriei și străzile din jurul ei, unde s-au construit, între 1880 și 1940, cele mai multe dintre palatele orașului — bănci, cluburi, instituții și reședințe boierești. Cele mai multe se văd din stradă; câteva au program de vizitare.',
        'stops' => [
            'ateneul-roman-bucuresti',
            'palatul-cantacuzino-bucuresti',
            'palatul-cretulescu-bucuresti',
            'palatul-cercului-militar-national-bucuresti',
            'palatul-bancii-marmorosch-blank-bucuresti',
            'palatul-bnr-bucuresti',
            'palatul-bursei-bucuresti',
        ],
    ],
    'oradea-in-stil-secession' => [
        'title' => 'Oradea în stil secession',
        'emoji' => '🎨',
        'pace'  => 'o zi, pe jos',
        'lead'  => 'Casele și palatele Art Nouveau din centrul Oradiei, plus ansamblul baroc al episcopiei.',
        'intro' => 'La începutul secolului XX, Oradea s-a reconstruit în stilul secession vienez și maghiar, iar clădirile au rămas grupate pe câteva străzi din centru. Traseul le ia pe rând și se termină la ansamblul baroc al episcopiei romano-catolice, din cealaltă parte a orașului.',
        'stops' => [
            'casa-darvas-oradea',
            'casa-fuchsl-oradea',
            'casa-deutsch-oradea',
            'casa-kovats-oradea',
            'moskovits-adolf-and-sons-palace-oradea',
            'catedrala-sfantul-nicolae-din-oradea-oradea',
            'palatul-baroc-din-oradea-oradea',
            'bazilica-romano-catolica-din-oradea-oradea',
        ],
    ],
    'timisoara-imperiala' => [
        'title' => 'Timișoara imperială',
        'emoji' => '🏙️',
        'pace'  => 'o zi, pe jos',
        'lead'  => 'Cetatea, piețele baroce și cartierul Fabric, de la bastion până la biserica din Fabric.',
        'intro' => 'Timișoara a fost reconstruită de austrieci ca oraș-cetate în secolul XVIII, iar planul acela se vede și azi: bastionul, piețele baroce din interiorul fostelor ziduri și cartierele de manufacturi din jur. Traseul începe la Bastionul Theresia, traversează centrul și se încheie în Fabric, cartierul industrial care și-a păstrat arhitectura.',
        'stops' => [
            'bastionul-theresia-timisoara',
            'biserica-piaristilor-inaltarea-sfintei-cruci-din-timisoara-timisoara',
            'palatul-culturii-din-timisoara-timisoara',
            'palatul-baroc-din-timisoara-timisoara',
            'casa-bruck-timisoara',
            'casa-arhiducelui-din-fabric-timisoara',
            'biserica-ortodoxa-sfantul-ilie-din-fabric-timisoara',
        ],
    ],
    'manastirile-valcii' => [
        'title' => 'Mănăstirile Vâlcii',
        'emoji' => '⛪',
        'pace'  => '2 zile',
        'lead'  => 'De la Cozia, pe Olt, până la Horezu și Mănăstirea Dintr-un Lemn, prin dealurile subcarpatice.',
        'intro' => 'Vâlcea are cea mai densă rețea de mănăstiri din Oltenia, multe ctitorite de domnitori sau de boieri în secolele XIV-XVIII. Traseul coboară pe valea Oltului de la Cornet și Cozia, urcă spre schiturile de munte și se încheie în dealuri, la Horezu și la mănăstirea ridicată, spune tradiția, dintr-un singur stejar.',
        'stops' => [
            'manastirea-cornet-racovita',
            'manastirea-cozia-caciulata',
            'manastirea-stanisoara-salatrucel',
            'manastirea-frasinei-muereasca',
            'manastirea-saracinesti-valea-cheii',
            'manastirea-horezu-romanii-de-jos',
            'manastirea-dintr-un-lemn-dezrobiti',
        ],
    ],
    'tara-hategului' => [
        'title' => 'Țara Hațegului',
        'emoji' => '🪨',
        'pace'  => 'o zi',
        'lead'  => 'Bisericile medievale de piatră din depresiunea Hațegului, cele mai vechi din Transilvania.',
        'intro' => 'Depresiunea Hațegului păstrează un grup de biserici de piatră din secolele XIII-XIV, ridicate de cnezii români locali — printre cele mai vechi lăcașuri de zid rămase în picioare din Transilvania. Sunt în sate aflate la câțiva kilometri unul de altul, sub munții Retezat.',
        'stops' => [
            'biserica-cnezilor-candea-din-santamaria-orlea-santamaria-orlea',
            'biserica-de-zid-sfantul-gheorghe-din-sanpetru-sanpetru',
            'biserica-parohiala-reformata-din-rau-alb-rau-alb',
            'biserica-duminica-tuturor-sfintilor-din-rau-de-mori-rau-de-mori',
        ],
    ],
];

/** One route by slug, or null. */
function v2_map_route(string $slug): ?array
{
    return MAP_ROUTES[$slug] ?? null;
}
