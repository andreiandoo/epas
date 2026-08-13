/* =========================================================
   CLIENT — DATE SI HELPERE VIZUALE, PORTATE VERBATIM din
   tics-app/client-app.html.

   FISIER GENERAT. Nu edita la mana:
       node scripts/extract-client-data.cjs

   Contine, identic cu prototipul:
     - datasets: VEN, ART, ARTX, EV, TICS, FEST, STAY, ADDONS, EXPDAYS,
       ATTENDED, CATPOOLS, PREFGROUPS, REWARDS, AFF, CAL_*, EVREVIEWS, ...
     - iconografia `I` (SVG inline)
     - SCENES: cele 9 "fotografii" SVG procedurale + sceneURI / bgv / galFor /
       poster / scByCat  <- generatoarele de imagine ale cardurilor.
       Portul precedent le-a inlocuit cu gradiente + emoji; de aici venea
       diferenta mare de aspect.

   Nu s-au portat helperele cuplate la DOM (lightbox, toast, timere) — acelea
   devin componente React.
   ========================================================= */
// @ts-nocheck — dump verbatim din prototip; tipurile se adauga la consum, nu aici.
/* eslint-disable */

export const VEN={
    arena:{id:'arena',name:'Cluj Arena',city:'Cluj-Napoca',addr:'Aleea Stadionului 2',cap:'30.000',tone:'linear-gradient(150deg,#241a44,#6d28d9)'},
    sala:{id:'sala',name:'Sala Mare · TN Cluj',city:'Cluj-Napoca',addr:'P-ța Ștefan cel Mare 24',cap:'820',tone:'linear-gradient(150deg,#2a2150,#7c3aed)'},
    zeppelin:{id:'zeppelin',name:'Zeppelin Feld',city:'Nuremberg',addr:'Bayern, DE',cap:'40.000',tone:'linear-gradient(150deg,#3a2c66,#8b5cf6)'},
    turda:{id:'turda',name:'Salina Turda',city:'Turda',addr:'Aleea Durgăului 7',cap:'—',tone:'linear-gradient(150deg,#0f4c4a,#12b3a6)'},
  };
  export const ART={
    coldplay:{id:'coldplay',name:'Coldplay',role:'Headliner',g:'🎤',tone:'linear-gradient(135deg,#7c3aed,#2a1065)',fol:'82M',bio:'Formație britanică de rock alternativ, una dintre cele mai apreciate live din lume.'},
    guetta:{id:'guetta',name:'David Guetta',role:'DJ / Producer',g:'🎧',tone:'linear-gradient(135deg,#8b5cf6,#241a44)',fol:'55M',bio:'DJ francez, pionier al EDM-ului comercial.'},
    smiley:{id:'smiley',name:'Smiley',role:'Pop / Live',g:'🎙',tone:'linear-gradient(135deg,#4c1d95,#a78bfa)',fol:'2.4M',bio:'Unul dintre cei mai iubiți artiști pop din România, show-uri live cu band complet.'},
    delia:{id:'delia',name:'Delia',role:'Pop',g:'🎤',tone:'linear-gradient(135deg,#6d28d9,#2a1065)',fol:'3.1M',bio:'Artistă pop cu producții vizuale spectaculoase și energie de scenă rară.'},
    garrix:{id:'garrix',name:'Martin Garrix',role:'DJ / Producer',g:'🎛',tone:'linear-gradient(135deg,#312e81,#6366f1)',fol:'48M',bio:'DJ olandez, nr. 1 mondial ani la rând, festival main-stage energy.'},
  };
  export const EV={
    coldplay:{id:'coldplay',t:'Coldplay — Music of the Spheres',s:'Coldplay',type:'event',cat:'Concerte',city:'Cluj-Napoca',ven:'arena',d:'19 Apr',mon:'Apr',day:'19',time:'20:00',from:175,tone:'linear-gradient(150deg,#4c1d95,#8b5cf6)',g:'🎤',rat:'4.9',by:'Live Nation',
      artists:['coldplay'],gallery:['linear-gradient(135deg,#7c3aed,#2a1065)','linear-gradient(135deg,#8b5cf6,#241a44)','linear-gradient(135deg,#a78bfa,#6d28d9)'],video:true,friends:['MI','AP','DL'],seatmap:true,
      tt:[{n:'Fan Pit',desc:'Cea mai apropiată zonă de scenă, în picioare.',p:350,pts:350,seat:false},{n:'Categoria I',desc:'Loc pe scaun, tribună centrală.',p:250,old:290,pts:250,seat:true},{n:'Categoria II',desc:'Loc pe scaun, tribună laterală.',p:175,pts:175,seat:true},{n:'Elev/Student',desc:'Necesită legitimație validă la intrare.',p:120,pts:120,seat:false}],
      bundles:[{n:'Pachet 2 persoane · Cat. I',desc:'2× Categoria I + program tipărit',p:460,old:500,pts:460},{n:'VIP Experience',desc:'Fast-track, lounge, welcome drink',p:690,pts:690}]},
    celestial:{id:'celestial',t:'Celestial Echo: The Horizon',s:'Celestial Echo',type:'event',cat:'Concerte',city:'Nuremberg',ven:'zeppelin',d:'21 Oct',mon:'Oct',day:'21',time:'19:00',from:56,tone:'linear-gradient(150deg,#3a2c66,#a78bfa)',g:'✨',rat:'4.7',by:'Zeppelin Live',
      artists:['guetta'],gallery:['linear-gradient(135deg,#8b5cf6,#2a1065)','linear-gradient(135deg,#a78bfa,#6d28d9)'],video:false,friends:['RS'],seatmap:false,
      tt:[{n:'General Access',desc:'Acces general, în picioare.',p:56,pts:56,seat:false},{n:'Golden Circle',desc:'Zonă premium lângă scenă.',p:110,old:130,pts:110,seat:false}],bundles:[]},
    swan:{id:'swan',t:'Lacul Lebedelor',s:'Lacul Lebedelor',type:'event',cat:'Teatru',city:'Cluj',ven:'sala',d:'18 Apr',mon:'Apr',day:'18',time:'19:00',from:80,tone:'linear-gradient(150deg,#2a2150,#7c3aed)',g:'🩰',rat:'4.9',by:'Teatrul Național',
      artists:[],gallery:['linear-gradient(135deg,#7c3aed,#2a2150)'],video:false,friends:[],seatmap:true,
      tt:[{n:'Parter',desc:'Rândurile 1–10, vizibilitate optimă.',p:120,pts:120,seat:true},{n:'Balcon',desc:'Etaj, vedere de ansamblu.',p:80,pts:80,seat:true}],bundles:[]},
    salina:{id:'salina',t:'Salina Turda — Tur & Agrement',s:'Salina Turda',type:'experience',cat:'Experiențe',city:'Turda',ven:'turda',d:'Zilnic',mon:'',day:'',time:'09–17',from:35,tone:'linear-gradient(150deg,#0f4c4a,#12b3a6)',g:'⛰',rat:'4.8',by:'Experiență tics',
      artists:[],gallery:['linear-gradient(135deg,#12b3a6,#0f4c4a)','linear-gradient(135deg,#0f766e,#134e4a)'],video:true,friends:['AP'],seatmap:false,
      tt:[{n:'Bilet Adult',desc:'Acces salină + roată panoramică + barcă pe lac subteran.',p:50,pts:50,seat:false},{n:'Bilet Copil',desc:'5–14 ani. Sub 5 ani gratuit.',p:35,old:40,pts:35,seat:false},{n:'Family Pass',desc:'2 adulți + 2 copii.',p:140,old:170,pts:140,seat:false}],bundles:[]},
    atv:{id:'atv',t:'ATV Adventure în Apuseni',s:'ATV Adventure',type:'experience',cat:'Experiențe',city:'Apuseni',ven:'turda',d:'Weekend',mon:'',day:'',time:'2h',from:180,tone:'linear-gradient(150deg,#7c3a12,#d97706)',g:'🏍',rat:'4.9',by:'Experiență tics',
      artists:[],gallery:['linear-gradient(135deg,#d97706,#7c3a12)'],video:false,friends:[],seatmap:false,
      tt:[{n:'ATV Single',desc:'Un ATV, o persoană, ghid inclus.',p:220,pts:220,seat:false},{n:'ATV Duo',desc:'Un ATV, două persoane.',p:180,pts:180,seat:false}],bundles:[]},
    wine:{id:'wine',t:'Wine Tasting la Jidvei',s:'Wine Tasting',type:'experience',cat:'Experiențe',city:'Alba',ven:'turda',d:'Vineri',mon:'',day:'',time:'3h',from:90,tone:'linear-gradient(150deg,#5b1f3a,#a83e6a)',g:'🍷',rat:'4.7',by:'Experiență tics',
      artists:[],gallery:['linear-gradient(135deg,#a83e6a,#5b1f3a)'],video:false,friends:['DL'],seatmap:false,
      tt:[{n:'Degustare 5 vinuri',desc:'Cu platou de brânzeturi.',p:120,pts:120,seat:false},{n:'Degustare 3 vinuri',desc:'Introducere ghidată.',p:90,pts:90,seat:false}],bundles:[]},
  };
  // Radar — tics urmărește piața și te trimite la cea mai bună ofertă (linkuri, nu vinde direct)
  export const TICS={
    smiley:{id:'smiley',s:'Smiley Live',cat:'Concerte',city:'Arad',venName:'Arena Arad',addr:'Str. Iuliu Maniu 1',day:'02',mon:'Mai',time:'20:00',tone:'linear-gradient(150deg,#4c1d95,#a78bfa)',g:'🎙',stock:'40+',rat:'4.8',
      desc:'Smiley revine live cu band complet, un show de peste 2 ore cu toate hiturile și surprize de scenă. O seară de pop românesc la cel mai înalt nivel.',
      artists:['smiley'],gallery:['linear-gradient(135deg,#a78bfa,#4c1d95)','linear-gradient(135deg,#7c3aed,#2a1065)'],
      offers:[['bilete.ro',95,'12 bilete'],['iaBilet',99,'stoc bun'],['Entertix',109,'ultimele 6']]},
    untold:{id:'untold',s:'Untold 2026',cat:'Festival',city:'Cluj',venName:'Cluj Arena',addr:'Aleea Stadionului 2',day:'06',mon:'Aug',time:'4 zile',tone:'linear-gradient(150deg,#1e1b4b,#7c3aed)',g:'🎪',stock:'Puține',rat:'4.9',
      desc:'Cel mai mare festival din România: 4 zile, zeci de scene și headlineri de talie mondială. Experiență completă cu zonă de camping și cashless.',
      artists:['garrix','guetta'],gallery:['linear-gradient(135deg,#7c3aed,#1e1b4b)','linear-gradient(135deg,#6366f1,#312e81)','linear-gradient(135deg,#a78bfa,#4c1d95)'],
      offers:[['Untold.com',499,'Faza 3'],['iaBilet',520,'stoc bun'],['MyTicket',549,'ultimele']]},
    delia:{id:'delia',s:'Delia — Deliria',cat:'Concerte',city:'București',venName:'Arenele Romane',addr:'Str. Cutitul de Argint 5',day:'14',mon:'Iun',time:'21:00',tone:'linear-gradient(150deg,#6d28d9,#2a1065)',g:'🎤',stock:'Disponibil',rat:'4.7',
      desc:'Delia aduce turneul Deliria, un spectacol vizual amplu cu coregrafii, lumini și toate piesele care au făcut istorie. Un show de neratat.',
      artists:['delia'],gallery:['linear-gradient(135deg,#6d28d9,#2a1065)','linear-gradient(135deg,#a78bfa,#6d28d9)'],
      offers:[['Entertix',129,'stoc bun'],['bilete.ro',135,'stoc bun'],['iaBilet',139,'stoc bun']]},
  };
  export const FEST={id:'nordvale',t:'Nordvale Festival 2026',s:'Nordvale',city:'Cluj',d:'10–13 Iul',from:180,tone:'linear-gradient(150deg,#0e0b1a,#7c3aed 130%)',g:'🎪',days:['Joi 10','Vin 11','Sâm 12','Dum 13'],lineup:[['David Guetta','Headliner'],['Martin Garrix','Co-headliner'],['Post Malone','Headliner'],['Antonia','Local']],stages:['Main Stage','Arcadia','Sunset','Cashless Bar'],
    artists:['garrix','guetta'],video:true,rat:'4.9',
    desc:'Patru zile, patru scene și peste 40 de artiști internaționali și locali. Nordvale înseamnă muzică non-stop, o zonă de camping proprie, food-court și un sistem cashless integrat cu brățara ta. Îți iei abonamentul, îți încarci portofelul și te bucuri — restul îl gestionăm noi.',
    gallery:['linear-gradient(135deg,#7c3aed,#1e1b4b)','linear-gradient(135deg,#a78bfa,#4c1d95)','linear-gradient(135deg,#6366f1,#312e81)','linear-gradient(135deg,#12b3a6,#0f4c4a)'],
    tt:[{n:'Abonament 4 zile · General',desc:'Acces general toate cele 4 zile.',p:420,pts:420,seat:false},
        {n:'Abonament 4 zile · VIP',desc:'Zonă VIP lângă scenă, bar dedicat, toalete premium, fast-lane.',p:890,old:990,pts:890,seat:false},
        {n:'Bilet 1 zi',desc:'Acces general pentru o singură zi, la alegere.',p:180,pts:180,seat:false},
        {n:'Camping Pass 4 nopți',desc:'Loc de cort în zona de camping + dușuri. Se ia pe lângă abonament.',p:150,pts:150,seat:false}],
    rentals:[{n:'Loc campare + cort echipat',d:'4 nopți, cort 2 pers montat',p:150,ic:'⛺',period:true},{n:'Cazare glamping premium',d:'Cort cu pat, curent, lounge',p:900,ic:'🏕️',period:true},{n:'Parcare festival 4 zile',d:'Lângă intrarea principală',p:120,ic:'🅿️'},{n:'Locker + stație încărcare',d:'Bagaje & telefon, acces nelimitat',p:60,ic:'🔒'}]};
  export const STAY=[{n:'Hotel Platinia',type:'Hotel',r:'4.7',dkm:0.4,p:420,x:44,y:38,tone:'linear-gradient(135deg,#7c3aed,#2a1065)',am:['wifi','parcare','mic dejun']},{n:'Grand Hotel Italia',type:'Hotel',r:'4.6',dkm:1.2,p:380,x:66,y:52,tone:'linear-gradient(135deg,#4c1d95,#8b5cf6)',am:['wifi','piscină','parcare']},{n:'Apartament Central',type:'Apartament',r:'4.8',dkm:0.6,p:260,x:52,y:46,tone:'linear-gradient(135deg,#0e7490,#22d3ee)',am:['wifi','bucătărie']},{n:'Hampton by Hilton',type:'Hotel',r:'4.5',dkm:0.8,p:340,x:34,y:60,tone:'linear-gradient(135deg,#6d28d9,#a78bfa)',am:['wifi','mic dejun']},{n:'Hostel The Spot',type:'Hostel',r:'4.3',dkm:0.9,p:120,x:38,y:30,tone:'linear-gradient(135deg,#b45309,#f59e0b)',am:['wifi']},{n:'DoubleTree Cluj',type:'Hotel',r:'4.4',dkm:1.5,p:300,x:62,y:32,tone:'linear-gradient(135deg,#312e81,#6366f1)',am:['wifi','parcare','piscină']}];
  export const STAYTYPES=['Toate','Hotel','Apartament','Hostel'];
  export const STAYSORTS=[['dist','Distanță'],['price','Preț'],['rating','Rating']];
  export function stayFilter(){let l=STAY.filter(s=>ST.stayF.type==='Toate'||s.type===ST.stayF.type).filter(s=>s.p<=ST.stayF.maxPrice);const so=ST.stayF.sort;l=l.slice().sort((a,b)=>so==='price'?a.p-b.p:so==='rating'?parseFloat(b.r)-parseFloat(a.r):a.dkm-b.dkm);return l;}
  export const CATS=[['Toate','✦'],['Concerte','🎵'],['Experiențe','⛰'],['Festival','🎪'],['Teatru','🩰'],['Sport','🏟'],['Stand-up','🎙']];
  export const PREFS=['Rock','Pop','Electronic','Hip-Hop','Teatru','Stand-up','Festivaluri','Experiențe','Sport','Familie','Clasică','Jazz'];
  export const PREFGROUPS=[
    {t:'Tipuri de evenimente',ic:'🎭',h:'Ce te scoate din casă?',p:'Ca să nu-ți arătăm balet clasic când tu vrei mosh-pit 🤘. Bifează ce-ți place — restul le ascundem.',o:['Concerte','Festivaluri','Teatru','Stand-up','Sport','Experiențe','Expoziții','Petreceri','Operă','Film','Conferințe','Familie & copii']},
    {t:'Genuri muzicale',ic:'🎧',h:'Pe ce dai play?',p:'Îți umplem feed-ul cu artiștii pe care chiar îi asculți — promitem, zero manele dacă nu-i cazul.',search:'Caută un gen…',o:['Rock','Pop','Electronic','House','Techno','Hip-Hop','Trap','Jazz','Blues','Clasică','Folk','Latino','Reggaeton','R&B','Manele','Indie','Metal','Punk','Disco','Funk','Drum & Bass','Dubstep','Country','Soul']},
    {t:'Orașe de interes',ic:'📍',h:'Unde ești gata să mergi?',p:'Îți arătăm întâi ce e aproape (sau orașul în care-ți fugi în weekend 👀).',search:'Caută un oraș…',o:['București','Cluj-Napoca','Timișoara','Iași','Constanța','Brașov','Sibiu','Oradea','Craiova','Arad','Târgu Mureș','Bacău','Ploiești','Galați','Suceava','Pitești','Baia Mare','Alba Iulia','Vama Veche','Mamaia']},
    {t:'Zile preferate',ic:'📆',h:'Când ai chef de ieșit?',p:'Ca să-ți dăm notificări la momentul bun, nu marțea la 8 dimineața 😴.',o:['Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă','Duminică']},
    {t:'Cum ieși de obicei',ic:'🫶',h:'Cu cine dai iama?',p:'Îți recomandăm experiențe pe gașca ta — solo, cu prietenii sau tot tribul de acasă.',o:['Singur','Cu prietenii','Cu familia','La întâlnire','Cu colegii']},
  ];
  export const ADDONS={
    salina:[{n:'Barcă pe lacul subteran',d:'30 min plimbare',p:40,ic:'🚣'},{n:'Roată panoramică',d:'Acces separat',p:15,ic:'🎡'},{n:'Parcare',d:'Toată ziua',p:20,ic:'🅿️'},{n:'Cabană 2 nopți',d:'Închiriere pe perioadă',p:600,ic:'🏡',period:true}],
    atv:[{n:'Ghid privat',d:'Traseu extins',p:120,ic:'🧭'},{n:'GoPro rental',d:'Filmează tura',p:60,ic:'📷'},{n:'Închiriere sanie',d:'Sezon iarnă',p:80,ic:'🛷'}],
    wine:[{n:'Platou brânzeturi',d:'Selecție locală',p:45,ic:'🧀'},{n:'Închiriere bicicletă',d:'Prin podgorie',p:50,ic:'🚲'},{n:'Transfer hotel',d:'Dus-întors',p:80,ic:'🚐'}],
  };
  // zile pentru experiențe + grad de ocupare
  export const EXPDAYS=[{wd:'Azi',d:'8',mon:'Aug',occ:82},{wd:'Vin',d:'9',mon:'Aug',occ:64},{wd:'Sâm',d:'10',mon:'Aug',occ:95},{wd:'Dum',d:'11',mon:'Aug',occ:100},{wd:'Lun',d:'12',mon:'Aug',occ:22},{wd:'Mar',d:'13',mon:'Aug',occ:41},{wd:'Mie',d:'14',mon:'Aug',occ:58},{wd:'Joi',d:'15',mon:'Aug',occ:74},{wd:'Vin',d:'16',mon:'Aug',occ:88},{wd:'Sâm',d:'17',mon:'Aug',occ:47}];
  export const occInfo=o=>o>=100?{l:'Sold-out',c:'#8a90a5',full:true}:o>=85?{l:'Aproape plin',c:'var(--red)'}:o>=60?{l:'Se umple',c:'var(--amber)'}:{l:'Locuri multe',c:'var(--green-2)'};
  // festival stages with per-day lineup + presentation
  export const FSTAGES=[
    {n:'Main Stage',c:'#7c3aed',desc:'Scena principală — headlinerii serii, sunet și lumini de arenă.',day:{'Joi 10':[['David Guetta','23:00'],['Antonia','21:00']],'Vin 11':[['Martin Garrix','23:00']]}},
    {n:'Arcadia',c:'#12b3a6',desc:'Structură industrială cu foc și acrobați — techno & house.',day:{'Joi 10':[['Charlotte de Witte','01:00'],['Boris Brejcha','22:30']]}},
    {n:'Sunset',c:'#d97706',desc:'Chill pe plajă la apus — deep & melodic.',day:{'Joi 10':[['Lane 8','19:00'],['Ben Böhmer','20:30']]}},
    {n:'Cashless Bar',c:'#6d28d9',desc:'Zona de food & drink, plătești cu brățara.',day:{'Joi 10':[['—','non-stop']]}},
  ];
  // in-app calendar (aggregator style)
  export const CAL_COUNTS={3:4,4:6,5:9,6:12,7:15,8:22,9:8,10:6,11:9,12:14,13:18,14:24,15:31,16:12,17:8,18:11,19:16,20:19,21:28,22:34,23:15,24:6,25:9,26:12,27:17,28:22,29:26,30:14,31:5};
  export const CAL_DOTS={5:['#7c3aed','#22c55e','#3b82f6','#d97706'],8:['#7c3aed','#22c55e','#3b82f6','#8b5cf6','#d97706'],15:['#7c3aed','#22c55e','#3b82f6','#8b5cf6','#d97706']};
  export const CAL_DAY=[['Concerte','Nibiru: Tzancă Uraganu','01:00 · Costinești','69','iaBilet','#7c3aed'],['Experiențe','Suceava: Diamond Drops','10:00 · Horodnic','37','iaBilet','#d97706'],['Concerte','SUNWAVES IBIZA','16:00 · Vama Veche','120','Entertix','#7c3aed'],['Teatru','Cinematerapia — valențe','14:00 · Iași','—','—','#3b82f6']];
  // Festivalul participă în fluxul de bilete ca un EV dedicat (abonamente + rentals)
  EV.nordvale={id:'nordvale',t:FEST.t,s:FEST.s,type:'event',cat:'Festival',city:FEST.city,ven:'arena',d:FEST.d,mon:'Iul',day:'10',time:'4 zile',from:FEST.from,tone:FEST.tone,g:'🎪',rat:FEST.rat,by:'Nordvale',artists:FEST.artists,gallery:FEST.gallery,video:FEST.video,friends:['MI','DL'],seatmap:false,tt:FEST.tt,bundles:[]};
  ADDONS.nordvale=FEST.rentals;
  // program de afiliere / rețea de prieteni
  export const AFF={code:'ANDREI2X',url:'tics.ro/r/ANDREI2X',invited:12,earned:1240,
    friends:[['Maria Ionescu','MI','Din checkout · Coldplay'],['Dan Lungu','DL','A folosit codul tău'],['Alex Pop','AP','A folosit codul tău'],['Raluca Stan','RS','Din checkout · Salina']],
    fof:[['Ioana Marcu','IM','prin Maria'],['George Vlad','GV','prin Dan'],['Sorin Tudor','ST','prin Alex']]};
  // recompense & recenzii
  export const REWARDS=[['🎟','10 lei reducere la orice bilet','500 p-','ok'],['🍺','Bere gratis la Cashless Bar','800 p-','ok'],['⚡','Fast-lane la intrare (1 eveniment)','1.200 p-','ok'],['🎁','Bilet dublu la un concert la alegere','3.000 p-','locked']];
  export const ATTENDED=[{ev:'coldplay',when:'19 Apr 2026',reviewed:false},{ev:'salina',when:'2 Feb 2026',reviewed:true}];
  export const MYREVIEWS=[{ev:'salina',target:'Eveniment',rating:5,txt:'Experiență superbă, ghidul a fost excelent și barca pe lacul subteran e memorabilă. Recomand cu drag!',when:'acum 2 zile'},{ev:'swan',target:'Locație · Sala Mare',rating:4,txt:'Acustică foarte bună, scaune confortabile. Parcarea e puțin aglomerată la final.',when:'săpt. trecută'}];
  export const EVREVIEWS=[['Andrei P.','AP',5,'Cel mai bun concert la care am fost. Producție impecabilă!','acum 3 zile'],['Maria I.','MI',5,'Atmosferă incredibilă, merită fiecare leu.','săpt. trecută'],['Dan L.','DL',4,'Super show, doar accesul a durat puțin.','acum 2 săpt.']];
  // artist — social icons + extended profile data
  export const SOCIC={spotify:'<svg width="18" height="18" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.6 14.4c-.2.3-.5.4-.8.2-2.2-1.3-5-1.6-8.3-.9-.3.1-.6-.1-.7-.4-.1-.3.1-.6.4-.7 3.6-.8 6.7-.4 9.2 1.1.3.2.4.5.2.7zm1.2-2.7c-.2.4-.6.5-.9.3-2.5-1.5-6.3-2-9.3-1.1-.4.1-.8-.1-.9-.5-.1-.4.1-.8.5-.9 3.4-1 7.6-.5 10.5 1.3.3.2.4.6.1.9zm.1-2.8C15 9.2 9.9 9 6.9 9.9c-.5.1-1-.1-1.1-.6-.1-.5.1-1 .6-1.1 3.5-1 9.1-.8 12.6 1.3.4.3.6.8.3 1.3-.3.4-.8.5-1.4.2z"/></svg>',instagram:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E4405F" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#E4405F" stroke="none"/></svg>',tiktok:'<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 3c.3 2 1.6 3.5 3.5 3.8V9c-1.3 0-2.5-.4-3.5-1v6.5A5.5 5.5 0 1 1 10.5 9v2.3A3.2 3.2 0 1 0 13 14.5V3h3z"/></svg>',fb:'<svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.6 4.5-4.6 1.3 0 2.6.2 2.6.2v2.9h-1.5c-1.4 0-1.9.9-1.9 1.8V12h3.3l-.5 3.5h-2.8v8.4A12 12 0 0 0 24 12z"/></svg>',lastfm:'<svg width="18" height="18" viewBox="0 0 24 24" fill="#D51007"><path d="M10.4 15.5l-.7-1.9c-.5 1.1-1.4 1.6-2.6 1.6-1.9 0-2.9-1.5-2.9-3.9 0-3 1.5-4.1 3-4.1 2.1 0 2.8 1.4 3.4 3.2l.8 2.4c.6 1.9 1.7 3.4 5.1 3.4 2.5 0 4.1-.8 4.1-2.8 0-1.7-.9-2.6-2.7-3l-1.3-.3c-.9-.2-1.2-.6-1.2-1.2 0-.7.6-1.2 1.5-1.2 1 0 1.6.4 1.7 1.3l2.1-.3c-.2-1.9-1.5-2.7-3.7-2.7-1.9 0-3.8.8-3.8 3 0 1.4.7 2.3 2.4 2.7l1.4.3c1 .2 1.3.6 1.3 1.1 0 .7-.7 1.1-2 1.1-1.9 0-2.7-1-3.2-2.5l-.8-2.4c-.7-2-1.7-3.6-4.6-3.6-2.9 0-4.5 1.8-4.5 4.9 0 3 1.5 4.6 4.2 4.6 2.2 0 3.3-1 3.5-1.6z"/></svg>'};
  export const SOCLIST=[['spotify','Spotify'],['instagram','Instagram'],['tiktok','TikTok'],['fb','Facebook'],['lastfm','Last.fm']];
  export const ARTX={
    coldplay:{sub:'Rock alternativ · UK',bio:'Coldplay este una dintre cele mai iubite și influente trupe live din lume. Cu producții vizuale amețitoare, brățări LED sincronizate și imnuri cântate de stadioane întregi, fiecare concert e o experiență colectivă de neuitat.',
      soc:{spotify:'62M',instagram:'24.8M',tiktok:'3.2M',fb:'40M',lastfm:'5.1M'},
      songs:[['Yellow','1.4B'],['Something Just Like This','2.1B'],['Viva La Vida','1.1B'],['A Sky Full of Stars','1.3B'],['The Scientist','980M'],['Paradise','1.0B'],['Fix You','1.2B'],['Hymn for the Weekend','1.6B'],['Adventure of a Lifetime','740M'],['Clocks','690M']],
      videos:[['Music of the Spheres · Live','linear-gradient(135deg,#7c3aed,#2a1065)','🎆'],['Yellow (Official)','linear-gradient(135deg,#f59e0b,#7c3aed)','💛'],['A Sky Full of Stars','linear-gradient(135deg,#3b82f6,#1e1b4b)','✨']]},
    guetta:{sub:'DJ / Producer · FR',bio:'David Guetta a definit sunetul mainstream-ului EDM. Colaborări globale, main-stage la cele mai mari festivaluri și un set de energie pură care nu se oprește niciodată.',
      soc:{spotify:'48M',instagram:'27M',tiktok:'9.5M',fb:'55M',lastfm:'3.2M'},
      songs:[['Titanium','2.2B'],['This One’s for You','1.1B'],['Without You','950M'],['Hey Mama','1.9B'],['Memories','780M'],['Bad (feat. Showtek)','640M'],['Say My Name','1.2B'],['I’m Good (Blue)','1.7B'],['Turn Me On','710M'],['Dangerous','830M']],
      videos:[['Tomorrowland Mainstage','linear-gradient(135deg,#312e81,#6366f1)','🎧'],['Titanium (feat. Sia)','linear-gradient(135deg,#0ea5e9,#1e1b4b)','⚡'],['I’m Good (Blue)','linear-gradient(135deg,#2563eb,#7c3aed)','💙']]},
    smiley:{sub:'Pop / Live · RO',bio:'Unul dintre cei mai iubiți artiști pop din România. Show-uri live cu band complet, energie contagioasă și hituri pe care le știe toată țara.',
      soc:{spotify:'2.4M',instagram:'2.1M',tiktok:'1.3M',fb:'3.4M',lastfm:'420k'},
      songs:[['Dead Man Walking','48M'],['Îngerii','62M'],['Cea mai frumoasă','55M'],['Acasă','40M'],['Vals','38M'],['Iubirea învinge','31M'],['Preludiu','28M'],['Confidential','25M'],['Toți cred','22M'],['Nemuritor','19M']],
      videos:[['Smiley Live · Arena','linear-gradient(135deg,#4c1d95,#a78bfa)','🎙'],['Îngerii (Official)','linear-gradient(135deg,#7c3aed,#2a1065)','🎬'],['Acasă · Live Session','linear-gradient(135deg,#6d28d9,#312e81)','🎵']]},
    delia:{sub:'Pop · RO',bio:'Artistă pop cu producții vizuale spectaculoase, coregrafii ample și o prezență de scenă rară. Turneele ei sunt printre cele mai vândute din țară.',
      soc:{spotify:'1.9M',instagram:'3.1M',tiktok:'2.2M',fb:'2.8M',lastfm:'310k'},
      songs:[['Da, mamă','58M'],['Gura ta','44M'],['Ipotecat','39M'],['Pariez','33M'],['Cine M-a Facut Om Mare','30M'],['Deliria','26M'],['1234','24M'],['Pe aripi de vânt','21M'],['Ce am eu cu ea','18M'],['Verde-mpărat','16M']],
      videos:[['Deliria · Tour','linear-gradient(135deg,#6d28d9,#2a1065)','🎤'],['Da, mamă (Official)','linear-gradient(135deg,#a78bfa,#6d28d9)','💜'],['1234 · Live','linear-gradient(135deg,#7c3aed,#db2777)','🔥']]},
    garrix:{sub:'DJ / Producer · NL',bio:'Nr. 1 mondial ani la rând, Martin Garrix aduce energie de main-stage și drops care fac istorie la fiecare festival major din lume.',
      soc:{spotify:'44M',instagram:'16M',tiktok:'6.8M',fb:'22M',lastfm:'1.8M'},
      songs:[['Animals','1.5B'],['In the Name of Love','1.6B'],['Scared to Be Lonely','1.4B'],['There for You','780M'],['Ocean','690M'],['Summer Days','620M'],['High on Life','540M'],['Used to Love','470M'],['Wizard','410M'],['Forbidden Voices','360M']],
      videos:[['Ultra Mainstage','linear-gradient(135deg,#312e81,#6156e6)','🎛'],['Animals (Official)','linear-gradient(135deg,#1e1b4b,#6366f1)','🐾'],['In the Name of Love','linear-gradient(135deg,#2563eb,#7c3aed)','💫']]},
  };
  // categorii cu imagini care se rotesc la 5s (evenimente reale din acea categorie)
  export const CATPOOLS=[
    {name:'Concerte',count:499,c:'#be185d',route:'go:category:Concerte',pool:[EV.coldplay,EV.celestial,TICS.smiley,TICS.delia]},
    {name:'Experiențe',count:214,c:'#0f766e',route:'go:category:Experiențe',pool:[EV.salina,EV.atv,EV.wine]},
    {name:'Teatru',count:476,c:'#0e7490',route:'go:category:Teatru',pool:[EV.swan,{tone:'linear-gradient(135deg,#374151,#111827)',g:'🎭',s:'Fata din curcubeu'},{tone:'linear-gradient(135deg,#4c1d95,#1e1b4b)',g:'🎭',s:'Livada de vișini'}]},
    {name:'Festival',count:87,c:'#b45309',route:'go:festival',pool:[EV.nordvale,TICS.untold,{tone:'linear-gradient(135deg,#7c3aed,#db2777)',g:'🎉',s:'Diamond Drops'}]},
    {name:'Stand-up',count:139,c:'#6d28d9',route:'go:ticslist',pool:[{tone:'linear-gradient(135deg,#1e293b,#0f172a)',g:'🎙',s:'Toma & Cristi Popesco'},{tone:'linear-gradient(135deg,#312e81,#6d28d9)',g:'🎙',s:'Best of Stand-up'},{tone:'linear-gradient(135deg,#4c1d95,#a78bfa)',g:'🎙',s:'Una Scurtă live'}]},
    {name:'Petrecere',count:1642,c:'#dc2626',route:'go:ticslist',pool:[EV.celestial,{tone:'linear-gradient(135deg,#7c3aed,#db2777)',g:'🍸',s:'Euphoria Cabaret'},{tone:'linear-gradient(135deg,#be185d,#7c3aed)',g:'🍺',s:'Beer Garden'}]},
  ];

  export const ST={prefs:['Rock','Electronic','Experiențe'],prefsSel:['Concerte','Experiențe','Rock','Electronic','Cluj-Napoca','Vineri','Cu prietenii'],ev:'coldplay',seats:['D4','D5'],obStep:0,cart:{protect:false,cultural:false,discount:0,nameOnTicket:false},balance:162.00,points:1240,_ttCounts:null,_ttEv:null,stayPin:0,expDate:0,expDay:4,addons:{},calDay:8,fStage:0,fDay:'Joi 10',saved:['celestial','atv','swan','wine'],revRating:0,revTab:0,rateStars:0,
    cards:[{brand:'Visa',last:'4218',exp:'07/27',grad:'linear-gradient(135deg,#1a1f71,#4b56d2)',primary:true},{brand:'Mastercard',last:'9902',exp:'11/26',grad:'linear-gradient(135deg,#c0392b,#e67e22)',primary:false}],
    stayF:{type:'Toate',sort:'dist',maxPrice:500}};
  // "tx" lettermark în Outfit — currentColor => merge alb/negru. (SVG-ul oficial tixello.com a fost blocat de politica de egress; se poate înlocui 1:1)
  export const txMark=(c,s)=>{s=s||26;return `<svg width="${s}" height="${s}" viewBox="0 0 40 40" fill="none" style="display:block"><text x="20" y="29" text-anchor="middle" font-family="Outfit,sans-serif" font-weight="700" font-size="29" letter-spacing="-1.6" fill="${c||'currentColor'}">tx</text></svg>`;};

  export const I={
    back:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>',
    bell:'<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
    save:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>',
    share:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>',
    info:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    pin:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>',
    cal:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    clock:'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    search:'<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>',
    slider:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="12" r="2"/><circle cx="15" cy="18" r="2"/></svg>',
    home:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
    compass:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/></svg>',
    ticket:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 0 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 0 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><path d="M13 5v14"/></svg>',
    user:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    wallet:'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="14" rx="3"/><path d="M3 10h18M16 15h2"/></svg>',
    qr:'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 14v.01M17 20h.01M20 17v4"/></svg>',
    nhome:'<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M11.03 2.79 3.9 8.33A2.75 2.75 0 0 0 2.83 10.5V19a2.75 2.75 0 0 0 2.75 2.75H8.6a1 1 0 0 0 1-1V16a2.4 2.4 0 0 1 4.8 0v4.75a1 1 0 0 0 1 1h3.02A2.75 2.75 0 0 0 21.17 19v-8.5a2.75 2.75 0 0 0-1.07-2.17l-7.13-5.54a1.55 1.55 0 0 0-1.94 0Z"/></svg>',
    nexplore:'<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.15" stroke="currentColor" stroke-width="1.7"/><path d="M15.6 8.4 13.55 13a1.6 1.6 0 0 1-.82.82L8.4 15.6l2.05-4.6a1.6 1.6 0 0 1 .82-.82Z" fill="currentColor"/></svg>',
    nticket:'<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M4.5 5.5A2 2 0 0 0 2.5 7.5v1.9a.9.9 0 0 0 .62.86 1.85 1.85 0 0 1 0 3.48.9.9 0 0 0-.62.86v1.9a2 2 0 0 0 2 2H10V16.5a1 1 0 0 1 2 0V18.5h7.5a2 2 0 0 0 2-2v-1.9a.9.9 0 0 0-.62-.86 1.85 1.85 0 0 1 0-3.48.9.9 0 0 0 .62-.86V7.5a2 2 0 0 0-2-2H12V7.5a1 1 0 0 1-2 0V5.5Z"/><path d="M11 7.5V16.5" stroke="#100d1c" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="1.3 2.4"/></svg>',
    nprofile:'<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="8" r="4.1"/><path d="M4.6 19.4a7.4 7.4 0 0 1 14.8 0 1.35 1.35 0 0 1-1.35 1.35H5.95A1.35 1.35 0 0 1 4.6 19.4Z"/></svg>',
    nscan:'<svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><rect x="8.5" y="8.5" width="3" height="3" rx=".6" fill="currentColor" stroke="none"/><rect x="12.5" y="8.5" width="3" height="3" rx=".6" fill="currentColor" stroke="none"/><rect x="8.5" y="12.5" width="3" height="3" rx=".6" fill="currentColor" stroke="none"/><rect x="12.5" y="12.5" width="3" height="3" rx=".6" fill="currentColor" stroke="none"/></svg>',
    x:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    lock:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    download:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 11l5 5 5-5M5 21h14"/></svg>',
    plus:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    minus:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14"/></svg>',
    arrow:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    ext:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg>',
    star:'<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>',
    play:'<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4l14 8-14 8z"/></svg>',
    bed:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4M2 17h20M6 11V9a2 2 0 0 1 2-2h3v4"/></svg>',
    car:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13v5H5z"/><circle cx="7.5" cy="18" r="1.5"/><circle cx="16.5" cy="18" r="1.5"/></svg>',
    check:'<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    tag:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12V4h8a2 2 0 0 1 1.4.6l8.2 8.2a1 1 0 0 1 0 1.4z"/><circle cx="7.5" cy="7.5" r="1"/></svg>',
    layers:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/></svg>',
    copy:'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    send:'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>',
    transfer:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3l4 4-4 4M20 7H8M8 21l-4-4 4-4M4 17h12"/></svg>',
    mail:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M3 7l9 6 9-6"/></svg>',
    heart:'<svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.9A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z"/></svg>',
    heartO:'<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.9A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z"/></svg>',
    wave:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12c2-3 4-3 6 0s4 3 6 0 4-3 6 0"/><path d="M2 17c2-3 4-3 6 0s4 3 6 0 4-3 6 0"/></svg>',
  };
  export const google='<svg width="19" height="19" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.5 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.9a5 5 0 0 1-2.2 3.3v2.7h3.6c2.1-2 3.2-4.9 3.2-7.9z"/><path fill="#34A853" d="M12 23c2.9 0 5.4-1 7.2-2.6l-3.6-2.7c-1 .7-2.3 1.1-3.6 1.1-2.8 0-5.1-1.9-6-4.4H2.3v2.8A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M6 14.3a6.6 6.6 0 0 1 0-4.2V7.3H2.3a11 11 0 0 0 0 9.9z"/><path fill="#EA4335" d="M12 5.4c1.6 0 3 .5 4.1 1.6l3.1-3.1A11 11 0 0 0 2.3 7.3L6 10.1c.9-2.6 3.2-4.7 6-4.7z"/></svg>';
  export const facebook='<svg width="19" height="19" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.6 4.5-4.6 1.3 0 2.6.2 2.6.2v2.9h-1.5c-1.4 0-1.9.9-1.9 1.8V12h3.3l-.5 3.5h-2.8v8.4A12 12 0 0 0 24 12z"/></svg>';

  export const money=n=>(Number.isInteger(n)?n:n.toFixed(2)),lei=n=>n.toFixed(2).replace('.',',');
  export const poster=(ev,cls,style,opt)=>`<div class="poster ${cls||''}" style="background:${bgv(ev)};${style||''}"><span class="glyph" style="opacity:.13">${ev.g}</span>${opt&&opt.tag?`<span class="tag ${ev.type==='experience'?'exp':''}">${ev.type==='experience'?'Experiență':ev.cat}</span>`:''}${opt&&opt.date&&ev.day?`<div class="dt"><b>${ev.day}</b><span>${ev.mon}</span></div>`:''}</div>`;
  export const sb=()=>`<div class="sb"><span>9:41</span><span class="dd"><svg width="17" height="11" viewBox="0 0 17 11" fill="currentColor"><rect y="7" width="3" height="4" rx="1"/><rect x="4.5" y="5" width="3" height="6" rx="1"/><rect x="9" y="2.5" width="3" height="8.5" rx="1"/><rect x="13.5" width="3" height="11" rx="1"/></svg><svg width="22" height="11" viewBox="0 0 24 12" fill="none"><rect x="1" y="1" width="20" height="10" rx="3" stroke="currentColor" opacity=".5"/><rect x="2.5" y="2.5" width="15" height="7" rx="1.5" fill="currentColor"/><rect x="22" y="4" width="2" height="4" rx="1" fill="currentColor" opacity=".5"/></svg></span></div>`;
  export const xIcon='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';

  // ===== procedural "stock photo" scenes (inline SVG — real stock APIs blocked by egress) =====
  export const _SV=(d,b)=>`<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 320' preserveAspectRatio='xMidYMid slice'>${d}${b}</svg>`;
  export const SCENES={
    concert:_SV(`<defs><linearGradient id='cg' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#8b46e8'/><stop offset='1' stop-color='#4f2f96'/></linearGradient><radialGradient id='cs' cx='.5' cy='.12' r='.55'><stop offset='0' stop-color='#ede9fe' stop-opacity='.95'/><stop offset='1' stop-color='#ede9fe' stop-opacity='0'/></radialGradient></defs>`,`<rect width='400' height='320' fill='url(#cg)'/><polygon points='200,6 78,320 150,320' fill='#c4b5fd' opacity='.10'/><polygon points='200,6 322,320 250,320' fill='#a78bfa' opacity='.10'/><polygon points='200,6 194,320 216,320' fill='#fff' opacity='.06'/><ellipse cx='200' cy='24' rx='80' ry='36' fill='url(#cs)'/><g fill='#fff'><circle cx='110' cy='70' r='2.4' opacity='.5'/><circle cx='300' cy='92' r='2' opacity='.4'/><circle cx='250' cy='58' r='1.6' opacity='.5'/><circle cx='150' cy='112' r='2' opacity='.35'/></g><path d='M0,320 L0,266 C34,258 60,276 92,266 C128,256 158,280 196,268 C232,258 268,282 306,268 C342,258 376,278 400,268 L400,320 Z' fill='#04030a'/><g stroke='#04030a' stroke-width='4' stroke-linecap='round'><path d='M60,300 L58,268'/><path d='M120,306 L124,270'/><path d='M300,306 L296,270'/><path d='M340,300 L344,272'/></g>`),
    party:_SV(`<defs><linearGradient id='pg' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='#5a4ad0'/><stop offset='1' stop-color='#332676'/></linearGradient></defs>`,`<rect width='400' height='320' fill='url(#pg)'/><g stroke-width='3' opacity='.75' stroke-linecap='round'><line x1='-20' y1='70' x2='420' y2='150' stroke='#22d3ee'/><line x1='-20' y1='210' x2='420' y2='120' stroke='#ec4899'/><line x1='-20' y1='160' x2='420' y2='250' stroke='#a78bfa'/><line x1='-20' y1='40' x2='420' y2='250' stroke='#22d3ee' opacity='.4'/></g><g><circle cx='80' cy='90' r='16' fill='#ec4899' opacity='.25'/><circle cx='320' cy='200' r='22' fill='#22d3ee' opacity='.2'/><circle cx='210' cy='60' r='10' fill='#a78bfa' opacity='.3'/><circle cx='150' cy='240' r='14' fill='#a78bfa' opacity='.18'/></g>`),
    theatre:_SV(`<defs><linearGradient id='tg' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#d83232'/><stop offset='1' stop-color='#7e2222'/></linearGradient><radialGradient id='tsp' cx='.5' cy='.35' r='.5'><stop offset='0' stop-color='#fde68a' stop-opacity='.5'/><stop offset='1' stop-color='#fde68a' stop-opacity='0'/></radialGradient></defs>`,`<rect width='400' height='320' fill='url(#tg)'/><ellipse cx='200' cy='150' rx='120' ry='150' fill='url(#tsp)'/><path d='M0,0 L130,0 C104,70 120,150 92,220 C74,264 40,290 0,300 Z' fill='#991b1b'/><path d='M400,0 L270,0 C296,70 280,150 308,220 C326,264 360,290 400,300 Z' fill='#991b1b'/><g stroke='#5b1010' stroke-width='2' opacity='.6' fill='none'><path d='M28,10 C20,120 34,220 20,300'/><path d='M64,10 C58,120 70,220 60,300'/><path d='M372,10 C380,120 366,220 380,300'/><path d='M336,10 C342,120 330,220 340,300'/></g><rect x='0' y='300' width='400' height='20' fill='#3a0c0c'/>`),
    festival:_SV(`<defs><linearGradient id='fg' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#312e81'/><stop offset='1' stop-color='#4f2f96'/></linearGradient></defs>`,`<rect width='400' height='320' fill='url(#fg)'/><g fill='#fff' opacity='.7'><circle cx='60' cy='50' r='1.4'/><circle cx='140' cy='36' r='1'/><circle cx='360' cy='60' r='1.4'/><circle cx='250' cy='30' r='1'/></g><g stroke='#fbcfe8' stroke-width='1.4' opacity='.7' fill='none'><circle cx='300' cy='96' r='50'/><path d='M300,46 L300,146 M250,96 L350,96 M264,60 L336,132 M336,60 L264,132'/></g><g fill='#f9a8d4' opacity='.85'><circle cx='300' cy='46' r='4'/><circle cx='350' cy='96' r='4'/><circle cx='300' cy='146' r='4'/><circle cx='250' cy='96' r='4'/></g><circle cx='300' cy='96' r='7' fill='#fbcfe8'/><g stroke='#fde68a' stroke-width='1.3' opacity='.85'><path d='M90,80 L90,50 M90,80 L70,62 M90,80 L110,62 M90,80 L74,88 M90,80 L106,88'/></g><g fill='#4c1d95'><polygon points='40,320 90,250 140,320'/><polygon points='150,320 210,240 270,320'/><polygon points='260,320 320,258 380,320'/></g><g fill='#7c3aed'><polygon points='150,320 210,240 210,320'/><polygon points='40,320 90,250 90,320'/></g>`),
    nature:_SV(`<defs><linearGradient id='sg' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#5eaad6'/><stop offset='.45' stop-color='#a7d8e8'/><stop offset='1' stop-color='#dcefe4'/></linearGradient></defs>`,`<rect width='400' height='320' fill='url(#sg)'/><circle cx='312' cy='72' r='34' fill='#fde9a8'/><path d='M0,220 L70,150 L140,205 L210,140 L290,210 L360,160 L400,205 L400,320 L0,320 Z' fill='#5b8f5b'/><path d='M0,255 L90,190 L170,245 L250,185 L330,245 L400,200 L400,320 L0,320 Z' fill='#3f7248'/><path d='M0,290 L110,235 L220,285 L330,235 L400,275 L400,320 L0,320 Z' fill='#2a5236'/>`),
    cave:_SV(`<defs><radialGradient id='cvg' cx='.5' cy='.55' r='.6'><stop offset='0' stop-color='#22ccb8'/><stop offset='1' stop-color='#0f7064'/></radialGradient></defs>`,`<rect width='400' height='320' fill='#0f4c44'/><ellipse cx='200' cy='190' rx='150' ry='120' fill='url(#cvg)'/><g fill='#0a2620'><polygon points='40,0 60,0 50,70'/><polygon points='110,0 134,0 122,95'/><polygon points='180,0 196,0 188,60'/><polygon points='250,0 276,0 263,110'/><polygon points='330,0 348,0 339,75'/></g><ellipse cx='200' cy='300' rx='150' ry='28' fill='#12b3a6' opacity='.22'/><g fill='#5eead4' opacity='.5'><circle cx='150' cy='150' r='1.6'/><circle cx='250' cy='170' r='1.4'/><circle cx='210' cy='130' r='1.2'/></g>`),
    wine:_SV(`<defs><linearGradient id='wg' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#f0a35e'/><stop offset='.45' stop-color='#b45c7a'/><stop offset='1' stop-color='#5a2848'/></linearGradient></defs>`,`<rect width='400' height='320' fill='url(#wg)'/><circle cx='120' cy='90' r='30' fill='#fde9a8' opacity='.85'/><path d='M0,200 C120,160 280,160 400,200 L400,320 L0,320 Z' fill='#6b3a2a'/><g stroke='#3f2018' stroke-width='2' opacity='.5'><path d='M20,230 L60,320'/><path d='M90,224 L130,320'/><path d='M160,220 L200,320'/><path d='M230,222 L270,320'/><path d='M300,226 L340,320'/></g>`),
    city:_SV(`<defs><linearGradient id='cig' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#9a6fd8'/><stop offset='1' stop-color='#2f3c82'/></linearGradient></defs>`,`<rect width='400' height='320' fill='url(#cig)'/><circle cx='320' cy='70' r='26' fill='#fbcfe8' opacity='.55'/><g fill='#0a0f22'><rect x='10' y='190' width='46' height='130'/><rect x='64' y='150' width='40' height='170'/><rect x='112' y='210' width='38' height='110'/><rect x='158' y='120' width='48' height='200'/><rect x='214' y='175' width='42' height='145'/><rect x='264' y='140' width='44' height='180'/><rect x='316' y='200' width='40' height='120'/><rect x='362' y='165' width='34' height='155'/></g><g fill='#fde68a' opacity='.7'><rect x='170' y='140' width='6' height='6'/><rect x='184' y='140' width='6' height='6'/><rect x='170' y='158' width='6' height='6'/><rect x='276' y='160' width='6' height='6'/><rect x='290' y='160' width='6' height='6'/><rect x='74' y='170' width='6' height='6'/><rect x='88' y='170' width='6' height='6'/></g>`),
    standup:_SV(`<defs><radialGradient id='sug' cx='.5' cy='.2' r='.7'><stop offset='0' stop-color='#7c63b8'/><stop offset='1' stop-color='#362a66'/></radialGradient></defs>`,`<rect width='400' height='320' fill='url(#sug)'/><polygon points='200,0 150,320 250,320' fill='#fde68a' opacity='.10'/><ellipse cx='200' cy='300' rx='90' ry='20' fill='#fde68a' opacity='.12'/><g stroke='#0c0a16' stroke-width='6' stroke-linecap='round'><path d='M200,300 L200,200'/></g><circle cx='200' cy='188' r='16' fill='#0c0a16'/><circle cx='200' cy='188' r='9' fill='#1a1626'/><rect x='192' y='300' width='16' height='16' rx='3' fill='#0c0a16'/>`),
  };
  export const scByCat=e=>({'Concerte':'concert','Teatru':'theatre','Experiențe':'nature','Festival':'festival'}[e&&e.cat]||'concert');
  // unquoted data URI (fully encode quotes) so it can't break style="" attributes
  export const sceneURI=t=>"data:image/svg+xml,"+encodeURIComponent(SCENES[t]||SCENES.concert).replace(/'/g,'%27').replace(/"/g,'%22').replace(/\(/g,'%28').replace(/\)/g,'%29');
  export const bgv=e=>`url('${sceneURI((e&&e.sc)||scByCat(e))}') center/cover, ${(e&&e.tone)||'#2a2440'}`;
  // galerie „foto" = variații de scene pe paletă
  export const GAL_SETS={concert:['concert','party','city'],party:['party','concert','city'],theatre:['theatre','city','concert'],festival:['festival','party','city'],nature:['nature','wine','cave'],cave:['cave','nature','city'],wine:['wine','nature','city'],city:['city','concert','party'],standup:['standup','concert','city']};
  export const galFor=e=>{const set=GAL_SETS[(e&&e.sc)||scByCat(e)]||['concert','party','city'];return set.map(t=>`url('${sceneURI(t)}') center/cover, #14101f`);};
  // asignează scene la date + regenerează galeriile ca imagini
  (function(){const SCMAP={coldplay:'concert',celestial:'party',swan:'theatre',salina:'cave',atv:'nature',wine:'wine',nordvale:'festival',smiley:'concert',untold:'festival',delia:'party'};
    Object.keys(SCMAP).forEach(k=>{if(EV[k])EV[k].sc=SCMAP[k];if(TICS[k])TICS[k].sc=SCMAP[k];});
    FEST.sc='festival';Object.values(VEN).forEach(v=>v.sc='city');
    const CATSC={'Concerte':'concert','Experiențe':'nature','Teatru':'theatre','Festival':'festival','Stand-up':'standup','Petrecere':'party'};
    if(typeof CATPOOLS!=='undefined')CATPOOLS.forEach(c=>c.pool.forEach(it=>{if(!it.sc)it.sc=CATSC[c.name]||'concert';}));
    Object.values(EV).forEach(e=>{if(e.gallery)e.gallery=galFor(e);});
    Object.values(TICS).forEach(t=>{if(t.gallery)t.gallery=galFor(t);});
    FEST.gallery=galFor(FEST);})();


  /* MYTIX — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const MYTIX=[{ev:'coldplay',passes:[{name:'Andrei Popescu',code:'TIX-CP-8841'},{name:'Maria Ionescu',code:'TIX-CP-8842',checkedIn:'19 Apr · 22:14 · Poarta A'}],seat:'B2, B3',cat:'Categoria I'},{ev:'salina',passes:[{name:'Andrei Popescu',code:'TIX-SL-2240'}],seat:'—',cat:'Bilet Adult',date:'9 Aug',slot:'09:00–17:00',people:'1 adult'}];

  /* OB — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const OB=[
    {art:`<div class="obart" style="background:linear-gradient(160deg,#171326,#0f0d18);display:grid;place-items:center">
        <div style="position:relative;width:180px;height:160px">
          <div style="position:absolute;left:4px;top:22px;width:100px;height:126px;border-radius:18px;background:${EV.coldplay.tone};transform:rotate(-9deg);box-shadow:var(--sh)"></div>
          <div style="position:absolute;right:0;top:14px;width:100px;height:126px;border-radius:18px;background:${EV.salina.tone};transform:rotate(9deg);box-shadow:var(--sh)"></div>
          <div style="position:absolute;left:40px;top:4px;width:100px;height:132px;border-radius:18px;background:${EV.swan.tone};box-shadow:var(--sh);display:grid;place-items:center;font-size:42px">🎟</div></div></div>`,
      h:'Evenimente ȘI experiențe',p:'Concerte, teatru, stand-up — plus tururi, aventuri și degustări. Totul într-un singur loc.'},
    {art:`<div class="obart" style="background:linear-gradient(160deg,#241a44,#7c3aed)">
        <div style="position:absolute;inset:0;display:flex;align-items:flex-end;justify-content:center;gap:12px;padding-bottom:26px">${[60,92,72,112,82].map((h,i)=>`<span style="width:13px;height:${h}px;border-radius:9px;background:linear-gradient(180deg,#e9d5ff,#a78bfa);opacity:${.62+i*.08}"></span>`).join('')}</div>
        <div style="position:absolute;top:18px;left:18px;color:#fff;font-weight:600">🎪 Nordvale · 4 zile</div><div style="position:absolute;bottom:16px;right:16px;background:rgba(255,255,255,.18);color:#fff;font-size:11px;font-weight:600;padding:5px 10px;border-radius:999px">Cashless</div></div>`,
      h:'Festivaluri & portofel cashless',p:'Program, lineup, brățară cashless și portofel electronic — fără cash, fără cozi.'},
    {art:`<div class="obart" style="background:linear-gradient(160deg,#171326,#0f0d18);display:grid;place-items:center">
        <div style="width:150px;height:150px;border-radius:50%;background:conic-gradient(var(--indigo) 0 70%, #241f3a 0);display:grid;place-items:center"><div style="width:112px;height:112px;border-radius:50%;background:#0f0d18;display:grid;place-items:center;text-align:center"><div><div style="font-size:32px">🤖</div><div style="font-weight:600;font-size:13px;color:var(--indigo-2);margin-top:2px">Pentru tine</div></div></div></div></div>`,
      h:'Sugestii AI, doar pentru tine',p:'Adunăm ce se întâmplă în toată România și îți recomandăm exact ce ți se potrivește. Cu cât ne spui mai multe, cu atât nimerim mai bine.'},
  ];

  /* OBALL — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const OBALL=[...OB,...PREFGROUPS.map((g,gi)=>({pref:true,group:gi}))];

  /* SORTLBL — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const SORTLBL={rec:'Recomandate',price:'Preț ↑',rating:'Rating ★'};

  /* MPCOL — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const MPCOL=['#7c3aed','#0e7490','#b45309','#be185d'];

  /* TX — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const TX=[['Top-up card ••8756','Azi 14:20','+200,00',1],['Cashless Bar · 2× Bere','Ieri 22:10','-24,00',0],['Food Truck · Burger','Ieri 21:32','-32,00',0]];

  /* PEMO — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const PEMO={'Concerte':'🎵','Festivaluri':'🎪','Teatru':'🎭','Stand-up':'🎙','Sport':'🏟','Experiențe':'⛰','Expoziții':'🖼','Petreceri':'🎉','Operă':'🎻','Film':'🎬','Conferințe':'💼','Familie & copii':'🧸','Singur':'🧍','Cu prietenii':'🫂','Cu familia':'👨‍👩‍👧','La întâlnire':'💘','Cu colegii':'🧑‍💼','Luni':'🗓','Marți':'🗓','Miercuri':'🗓','Joi':'🗓','Vineri':'🍸','Sâmbătă':'✨','Duminică':'☀️'};

  /* NOTI — declarat mai jos in prototip, langa ecranul care il foloseste */
  export const NOTI=[['🎫','Biletele tale sunt gata','Coldplay · 2× QR în cont','acum 5 min',1],['🤖','Recomandare nouă pentru tine','Salina Turda — pentru că îți plac experiențele','acum 1h',1],['💳','Top-up reușit · +200 lei','Sold nou: 362 lei','azi 14:20',0],['🔥','Coldplay — ultimele bilete','Categoria II aproape epuizată','ieri',0]];
