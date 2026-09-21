<?php
/**
 * bilete.online v2: the /harta/{slug} landings — eight attraction types and eight historical
 * regions, all rendered by includes/v2/map-page.php with one filter applied.
 *
 * Each entry is only the copy; the numbers, the cities, the counties and the picks come from the
 * matching entry in assets/v2/data/atractii.summary.json (`landings`), so nothing here goes stale
 * when the catalogue changes. `kind` + `key` must match the definitions in bin/build-map-data.php.
 *
 *   h1     heading, without the place
 *   em     the second, coloured half of the heading
 *   lead   one sentence under the heading
 *   intro  the opening paragraph of the editorial block
 *   faq    two questions specific to this landing, added to the shared ones
 *
 * Attractions are named only where they were checked against the dataset.
 */

const MAP_LANDINGS = [
    // ------------------------------------------------------------------ types
    'castele' => [
        'kind' => 'type', 'key' => 'castel-palat',
        'h1'   => 'Harta castelelor și palatelor',
        'em'   => 'din România',
        'lead' => 'Castele, palate și conace, de la reședințele regale din Sinaia la palatele boierești și conacele de țară.',
        'intro' => 'Categoria adună reședințele construite ca să fie locuite, nu ca să fie apărate: castele de munte ca Peleș și Pelișor, palate urbane ridicate în secolul al XIX-lea, conace boierești și curți nobiliare ca ansamblul Bánffy de la Bonțida. Cetățile și fortificațiile sunt trecute la monumente, așa că aici nu le vei găsi.',
        'faq' => [
            ['Ce intră la „castel & palat”?', 'Reședințele: castele, palate, conace și curți nobiliare. Cetățile, turnurile și fortificațiile sunt clasificate ca monumente, iar bisericile fortificate ca biserici și mănăstiri.'],
            ['Pot cumpăra bilet de intrare de aici?', 'Harta arată unde se află fiecare loc și duce la pagina lui. Biletele apar acolo unde administratorul locului le vinde prin bilete.online; restul sunt obiective de vizitat, fără bilet online.'],
        ],
    ],
    'muzee' => [
        'kind' => 'type', 'key' => 'muzeu',
        'h1'   => 'Harta muzeelor',
        'em'   => 'din România',
        'lead' => 'Muzee naționale, muzee de oraș, case memoriale și colecții sătești, toate pe aceeași hartă.',
        'intro' => 'De la muzeele naționale din marile orașe până la colecțiile muzeale ținute într-o singură cameră, într-un sat. Sunt incluse casele memoriale, muzeele de artă, de istorie și de etnografie, muzeele în aer liber și colecțiile găzduite de biserici sau de școli.',
        'faq' => [
            ['Sunt și muzeele mici, din sate?', 'Da. Colecțiile muzeale sătești și casele memoriale sunt în aceeași categorie ca muzeele naționale — de-asta lista e atât de lungă în afara orașelor mari.'],
            ['Cum găsesc muzeele dintr-un anumit oraș?', 'Scrie numele orașului în căutarea hărții, sau intră direct pe pagina orașului din lista de mai jos.'],
        ],
    ],
    'monumente' => [
        'kind' => 'type', 'key' => 'monument',
        'h1'   => 'Harta monumentelor',
        'em'   => 'din România',
        'lead' => 'Cetăți, situri arheologice, statui, cruci de piatră și monumente comemorative.',
        'intro' => 'Cea mai largă categorie: tot ce e clasat ca monument fără să fie clădire locuibilă, biserică sau muzeu. Intră cetățile și fortificațiile, siturile arheologice, statuile și busturile, monumentele de for public și cele comemorative, de la cetăți țărănești ca Prejmer până la cetatea Enisala din Dobrogea.',
        'faq' => [
            ['De ce sunt atât de multe monumente?', 'Categoria acoperă și obiectivele mici — o statuie, o cruce de piatră, un monument comemorativ — nu doar cetățile și siturile mari. De aceea e a doua ca mărime de pe hartă.'],
            ['Cetățile unde sunt?', 'Tot aici. Cetățile și fortificațiile sunt monumente; castelele și palatele au categoria lor.'],
        ],
    ],
    'biserici-si-manastiri' => [
        'kind' => 'type', 'key' => 'biserica-manastire',
        'h1'   => 'Harta bisericilor și mănăstirilor',
        'em'   => 'din România',
        'lead' => 'Mănăstiri, biserici de lemn, biserici fortificate și catedrale — cea mai numeroasă categorie de pe hartă.',
        'intro' => 'Aici sunt bisericile de lemn din nord, mănăstirile pictate, bisericile fortificate săsești ca cele din Biertan și Viscri, catedralele urbane și miile de biserici de sat clasate ca monument istoric. Pe harta generală această categorie e stinsă din start, tocmai pentru că e mai numeroasă decât toate celelalte la un loc.',
        'faq' => [
            ['De ce nu apar bisericile pe harta principală?', 'Sunt peste jumătate din toate atracțiile și ar acoperi restul punctelor. Pe harta generală se aprind dintr-un click pe filtrul de tip; aici sunt singurele afișate.'],
            ['Sunt incluse și bisericile de lemn?', 'Da, ca și bisericile fortificate, mănăstirile, schiturile și catedralele. Tot ce e lăcaș de cult clasat intră în această categorie.'],
        ],
    ],
    'parcuri-si-gradini' => [
        'kind' => 'type', 'key' => 'parc-gradina',
        'h1'   => 'Harta parcurilor și grădinilor',
        'em'   => 'din România',
        'lead' => 'Parcuri istorice, grădini botanice și parcuri dendrologice, cu tot cu cele din jurul conacelor.',
        'intro' => 'Parcurile publice cu statut de monument, grădinile botanice, parcurile dendrologice și grădinile amenajate în jurul castelelor și conacelor. Sunt spații de plimbare, de obicei cu acces liber, bune de pus între două vizite într-o zi de oraș.',
        'faq' => [
            ['Se plătește intrarea în parcuri?', 'Majoritatea parcurilor istorice au acces liber. Grădinile botanice și unele parcuri dendrologice au taxă de intrare, stabilită de administrator.'],
            ['De ce unele nu au fotografie?', 'Fotografiile sunt încărcate de administratorii locurilor. Punctele fără poză sunt la fel de reale — apar cu un desen din identitatea vizuală în locul imaginii.'],
        ],
    ],
    'cladiri-istorice' => [
        'kind' => 'type', 'key' => 'cladire-istorica',
        'h1'   => 'Harta clădirilor istorice',
        'em'   => 'din România',
        'lead' => 'Case, hanuri, gări, băi publice și clădiri civile clasate ca monument istoric.',
        'intro' => 'Clădirile care nu sunt nici reședințe nobiliare, nici lăcașuri de cult, nici muzee: case urbane, hanuri, gări, hoteluri vechi, băi publice, sedii de bănci și școli, cazinouri ca cel militar din Timișoara. Sunt obiective pe care le vezi de obicei din stradă, în plimbările prin centrele vechi.',
        'faq' => [
            ['Se pot vizita pe dinăuntru?', 'Multe sunt clădiri în funcțiune — școli, sedii, locuințe — și se văd doar din exterior. Cele care primesc vizitatori o spun pe pagina lor.'],
            ['Cum le găsesc pe cele dintr-un centru vechi?', 'Deschide harta, caută orașul și apropie pe centrul lui: clădirile istorice apar grupate exact pe străzile vechi.'],
        ],
    ],
    'teatre-si-opere' => [
        'kind' => 'type', 'key' => 'teatru-opera',
        'h1'   => 'Harta teatrelor și operelor',
        'em'   => 'din România',
        'lead' => 'Teatre naționale, opere, filarmonici și ateneuri, inclusiv clădirile lor istorice.',
        'intro' => 'Teatrele naționale și de stat, operele, filarmonicile, ateneurile și casele de cultură cu sală de spectacol — de la Ateneul Român până la palatele culturii din orașele mari, care găzduiesc adesea și teatru, și filarmonică, și muzeu.',
        'faq' => [
            ['Găsesc aici și spectacolele?', 'Harta arată clădirile, nu programul. Biletele la spectacole apar pe bilete.online acolo unde instituția vinde prin platformă.'],
            ['Ateneurile și casele de cultură intră aici?', 'Da, dacă au sală de spectacol. Palatele culturii apar de obicei și în această categorie, și ca muzeu.'],
        ],
    ],
    'lacuri-si-natura' => [
        'kind' => 'type', 'key' => 'lac-natura',
        'h1'   => 'Harta lacurilor și locurilor din natură',
        'em'   => 'din România',
        'lead' => 'Lacuri, chei, peșteri și rezervații — cea mai mică, dar cea mai răsfirată categorie.',
        'intro' => 'Obiectivele naturale intrate în catalog: lacuri, chei, peșteri, rezervații și arii protejate. Sunt puține la număr și împrăștiate pe toată țara, așa că harta e cel mai bun mod de a le găsi pe cele dintr-o zonă în care ajungi oricum.',
        'faq' => [
            ['De ce sunt așa puține?', 'Catalogul pornește de la obiectivele clasate ca patrimoniu, unde naturalul e slab reprezentat. Categoria crește pe măsură ce administratorii de arii protejate își adaugă locurile.'],
            ['Pot vedea ce e în apropiere?', 'Da — apasă „Lângă mine” pe hartă, sau deschide pagina unui punct: fiecare atracție arată ce se află în jurul ei.'],
        ],
    ],

    // ------------------------------------------------------------------ regions
    'transilvania' => [
        'kind' => 'region', 'key' => 'Transilvania',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Transilvania',
        'lead' => 'Biserici fortificate, cetăți, castele și orașe medievale, din Brașov până în Bihor.',
        'intro' => 'Regiunea cu cea mai densă rețea de patrimoniu construit din țară: bisericile fortificate săsești, cetățile țărănești, orașele medievale cu centru vechi întreg și castelele nobiliare maghiare. Tot aici sunt cele mai multe muzee din afara Bucureștiului.',
        'faq' => [
            ['Ce nu trebuie ratat în Transilvania?', 'Bisericile fortificate din zona Sibiu–Brașov, centrele vechi ale orașelor săsești și castelele nobiliare din nordul regiunii. Deschide harta și filtrează după tipul care te interesează.'],
            ['Cum planific un traseu prin regiune?', 'Filtrează după tip, apropie pe zona în care ajungi și folosește lista din stânga: e ordonată după distanța față de centrul hărții.'],
        ],
    ],
    'muntenia' => [
        'kind' => 'region', 'key' => 'Muntenia',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Muntenia',
        'lead' => 'Bucureștiul, Valea Prahovei și mănăstirile de câmpie, în regiunea cu cele mai multe puncte de pe hartă.',
        'intro' => 'Regiunea cu cel mai mare număr de atracții, în bună parte din cauza Bucureștiului, unde se adună muzeele naționale, palatele și clădirile istorice. În afara capitalei, Valea Prahovei concentrează castelele regale, iar câmpia — mănăstirile și conacele brâncovenești.',
        'faq' => [
            ['Câte dintre ele sunt în București?', 'O bună parte. Numărul exact pentru oraș e în lista de orașe de mai jos, iar harta îl arată în timp real când cauți „București”.'],
            ['Ce e de văzut pe Valea Prahovei?', 'Castelele de la Sinaia și obiectivele din jurul lor. Filtrează harta după „Castel & palat” și apropie pe zona Sinaia–Bușteni.'],
        ],
    ],
    'moldova' => [
        'kind' => 'region', 'key' => 'Moldova',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Moldova',
        'lead' => 'Mănăstirile din nord, cetățile de scaun și orașele cu palate ale culturii.',
        'intro' => 'Moldova istorică, de la mănăstirile pictate din Bucovina — Voroneț printre ele — la cetățile de scaun și la orașele mari, cu palate ale culturii și muzee. Zona de nord e cea mai densă în obiective religioase din toată țara.',
        'faq' => [
            ['Mănăstirile pictate sunt toate aici?', 'Da, intră la „Biserică & mănăstire”. Filtrează după acest tip și apropie pe nordul regiunii ca să le vezi grupate.'],
            ['Care orașe au cele mai multe atracții?', 'Sunt în lista de orașe de mai jos, ordonată după numărul de obiective.'],
        ],
    ],
    'banat' => [
        'kind' => 'region', 'key' => 'Banat',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Banat',
        'lead' => 'Timișoara și arhitectura ei, plus satele și orașele mici din jur.',
        'intro' => 'Regiunea e dominată de Timișoara, cu palate, clădiri istorice, teatre și muzee adunate în cartierele centrale. În afara orașului, patrimoniul e răsfirat: biserici, monumente și clădiri civile din localitățile mici de câmpie și de munte.',
        'faq' => [
            ['Ce se vede într-o zi în Timișoara?', 'Centrul istoric adună cele mai multe obiective din regiune. Apropie harta pe oraș: clădirile istorice apar grupate pe piețele mari.'],
            ['Există obiective și în afara Timișoarei?', 'Da, dar mult mai rare. Harta e cel mai simplu mod de a le găsi pe cele aflate pe traseul tău.'],
        ],
    ],
    'oltenia' => [
        'kind' => 'region', 'key' => 'Oltenia',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Oltenia',
        'lead' => 'Mănăstiri de deal, conace brâncovenești și orașele de pe Jiu și Olt.',
        'intro' => 'Oltenia are un patrimoniu religios bogat, cu mănăstiri așezate pe dealurile subcarpatice, plus conace și culele specifice zonei. Craiova concentrează muzeele și clădirile publice, iar nordul regiunii — obiectivele de munte.',
        'faq' => [
            ['Ce sunt culele?', 'Case-turn fortificate, specifice Olteniei și nordului Munteniei. În catalog apar de obicei ca monumente sau clădiri istorice.'],
            ['Care e cel mai bun mod de a le vizita?', 'Sunt răspândite, deci merită grupate pe zone. Folosește harta și lista din stânga, care se ordonează după distanță.'],
        ],
    ],
    'dobrogea' => [
        'kind' => 'region', 'key' => 'Dobrogea',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Dobrogea',
        'lead' => 'Cetăți antice, situri arheologice și litoralul, într-o regiune cu cea mai veche urmă construită din țară.',
        'intro' => 'Dobrogea are cel mai vechi patrimoniu construit din România: cetăți grecești și romane ca Histria, cetăți medievale ca Enisala, situri arheologice și moștenirea otomană din orașele de coastă. Vara, multe dintre ele sunt la mai puțin de o oră de plajă.',
        'faq' => [
            ['Ce se poate vizita pe lângă plajă?', 'Cetățile antice și siturile arheologice din interiorul regiunii. Apropie harta pe zona în care stai și lista îți arată ce e cel mai aproape.'],
            ['Sunt incluse și obiectivele din Deltă?', 'Cele clasate, da. Categoria „Lac & natură” e însă slab reprezentată în tot catalogul.'],
        ],
    ],
    'crisana' => [
        'kind' => 'region', 'key' => 'Crișana',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Crișana',
        'lead' => 'Oradea și arhitectura ei secession, plus bisericile de lemn din dealuri.',
        'intro' => 'Regiunea din vestul țării, cu Oradea în centru: palate în stil secession, clădiri publice din perioada austro-ungară și băi termale cu istorie. Spre dealuri, patrimoniul devine rural — biserici de lemn și monumente de sat.',
        'faq' => [
            ['Ce e de văzut în Oradea?', 'Palatele și clădirile din centrul istoric formează cea mai densă grupare din regiune. Apropie harta pe oraș ca să le vezi una lângă alta.'],
            ['Există obiective în afara orașelor?', 'Da, mai ales biserici de lemn și monumente în satele din dealuri.'],
        ],
    ],
    'maramures' => [
        'kind' => 'region', 'key' => 'Maramureș',
        'h1'   => 'Harta atracțiilor',
        'em'   => 'din Maramureș',
        'lead' => 'Biserici de lemn, sate cu porți sculptate și muzee ale satului.',
        'intro' => 'Regiunea cu cea mai bine păstrată arhitectură de lemn din țară: biserici de lemn cu turle înalte, porți sculptate, muzee ale satului și colecții etnografice. Obiectivele sunt răspândite prin sate, deci harta ajută mai mult decât o listă.',
        'faq' => [
            ['Bisericile de lemn se pot vizita?', 'Multe sunt biserici active; accesul îl stabilește parohia. Pagina fiecărui obiectiv spune ce se știe despre el.'],
            ['Cum le găsesc pe cele apropiate între ele?', 'Apropie harta pe valea pe care mergi: bisericile apar înșirate de-a lungul drumurilor principale.'],
        ],
    ],
];

/** The landing for a URL slug, or null. */
function v2_map_landing(string $slug): ?array
{
    return MAP_LANDINGS[$slug] ?? null;
}

/** The URL slug of the landing for a type slug ('castel-palat' => 'castele'), or ''. */
function v2_map_landing_for_type(string $typeSlug): string
{
    foreach (MAP_LANDINGS as $slug => $def) {
        if ($def['kind'] === 'type' && $def['key'] === $typeSlug) {
            return $slug;
        }
    }

    return '';
}

/** The URL slug of the landing for a region name ('Transilvania' => 'transilvania'), or ''. */
function v2_map_landing_for_region(string $region): string
{
    foreach (MAP_LANDINGS as $slug => $def) {
        if ($def['kind'] === 'region' && $def['key'] === $region) {
            return $slug;
        }
    }

    return '';
}
