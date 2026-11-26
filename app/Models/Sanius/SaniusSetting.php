<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;

/**
 * Setarile circuitului Sanius pe un eveniment. Implicit oprit (mode = off):
 * fara un rand aici sau cu mode = off, niciun endpoint al circuitului nu raspunde.
 */
class SaniusSetting extends Model
{
    public const MODES = ['off', 'test', 'live'];

    public const ROLES = ['desk', 'tow', 'cashier', 'manager'];

    public const PRODUCT_KEYS = [
        'cycle_weekday', 'cycle_weekend', 'tow', 'damage_small', 'damage_medium', 'damage_large', 'lost', 'parking_car', 'parking_bus',
    ];

    public const PARKING_DEFAULTS = [
        'free_minutes' => 10,
        'free_minutes_with_access' => 240,
        'unit_minutes' => 30,
    ];

    /** Chiria pe cicluri: durata ciclului, tractari incluse pe ciclu, toleranta la returnare. */
    public const RENTAL_DEFAULTS = [
        'cycle_minutes' => 60,
        'tows_per_cycle' => 1,
        'grace_minutes' => 10,
    ];

    public const CONTRACT_DEFAULT_TITLE = 'Contract de închiriere echipamente';

    /** Contractul tipizat folosit la Sf. Ana (Sanius, model 2023); organizatorul il poate modifica din setari. */
    public const CONTRACT_DEFAULT_BODY = <<<'TXT'
Data: {data}, ora: {ora}. Număr sănii: {sanii}.

I. PĂRȚILE CONTRACTANTE
1.1. SC CSOMADCOM SRL, cu sediul în localitatea com. Cozmeni, sat Lăzărești nr. 76, județul Harghita, cod fiscal RO28151402, înregistrat la Registrul Comerțului Harghita sub nr. J19/117/2011, reprezentat(ă) prin Szakács-Czont Márton, în calitate de locator și
1.2. {nume}, cu domiciliul în {adresa}, {act_tip} seria {act_serie} nr. {act_numar}, nr. telefon {telefon}, tichet parcare nr. {tichet_parcare}, în calitate de locatar,
au convenit să încheie prezentul contract de închiriere, cu respectarea dispozițiilor art. 1777-1835 Cod civil.

II. OBIECTUL CONTRACTULUI
2.1. Obiectul contractului îl constituie cedarea folosinței asupra echipamentului sportiv: „Sanie EKO SNOW-STAR 100”, la prețul afișat la casierie.

III. TERMENUL CONTRACTULUI
3.1. Termenul contractului este de 1 zi, la data semnării prezentului contract.

IV. PREȚUL CONTRACTULUI
4.1. Prețul închirierii – chiria – pentru folosirea bunului care face obiectul închirierii este conform art. 2.1., calculat de la data și ora semnării prezentului contract.
4.2. Plata chiriei se face în numerar sau cu card bancar la casă, după predarea echipamentului închiriat, predare confirmată de reprezentantul locatorului.

V. OBLIGAȚIILE PĂRȚILOR
5.1. Locatorul se obligă:
a) să predea bunul în starea corespunzătoare folosinței;
b) să asigure folosința bunului închiriat pe toată durata contractului.
5.2. Obligațiile locatarului sunt următoarele:
a) să folosească bunul închiriat potrivit destinației sale și să se îngrijească de bunul închiriat ca de proprietatea sa;
b) să plătească chiria în cuantumul și la termenele stabilite prin prezentul contract;
c) la încheierea duratei contractului, să restituie bunul închiriat în locația de unde l-a preluat, în starea în care l-a primit, adică în stare bună.

VI. SUBÎNCHIRIEREA ȘI CESIUNEA CONTRACTULUI
6.1. Subînchirierea bunului închiriat sau cesiunea contractului de închiriere unui terț nu este permisă.

VII. RĂSPUNDEREA CONTRACTUALĂ
7.1. Deteriorarea, distrugerea sau pierderea bunului închiriat obligă locatarul la plata sumei de 116,50 Euro pentru sania Snowstar, calculată la cursul valutar al zilei la banca locatorului, Raiffeisen Bank.
7.2. Echipamentele închiriate sunt folosite de locatar pe propria responsabilitate, locatorul nu este răspunzător de eventualele accidente survenite în timpul utilizării echipamentelor.

VIII. FORȚA MAJORĂ
8.1. Niciuna dintre părțile contractante nu răspunde de neexecutarea la termen sau/și de executarea în mod necorespunzător – total sau parțial – a oricărei obligații care îi revine în baza prezentului contract, dacă neexecutarea sau/și executarea obligației respective a fost cauzată de forța majoră așa cum este definită de lege.

IX. NOTIFICĂRILE ÎNTRE PĂRȚI
9.1. În accepțiunea părților contractante, orice notificare adresată de una dintre acestea celeilalte este valabil îndeplinită dacă va fi transmisă la adresa/sediul prevăzut în partea introductivă a prezentului contract. Notificările verbale nu se iau în considerare de niciuna dintre părți.

X. SOLUȚIONAREA LITIGIILOR
10.1. În cazul în care eventualele neînțelegeri privind validitatea prezentului contract sau rezultate din interpretarea, executarea sau încetarea lui nu se vor putea rezolva pe cale amiabilă, părțile au convenit să se adreseze instanței judecătorești competente a județului Harghita.

XI. CLAUZE GENERALE REFERITOARE LA PROTECȚIA DATELOR CU CARACTER PERSONAL
11.1. Părțile colectează și prelucrează datele cu caracter personal înscrise în prezentul contract în conformitate cu legislația în vigoare, în modalități care asigură confidențialitatea și securitatea adecvată a acestor date, în vederea asigurării protecției împotriva prelucrării neautorizate sau ilegale și împotriva pierderii, a distrugerii sau a deteriorării accidentale.
11.2. În procesul de prelucrare a datelor cu caracter personal, părțile aplică prevederile Regulamentului (UE) 2016/679 al Parlamentului European și al Consiliului din 27 aprilie 2016 privind protecția persoanelor fizice în ceea ce privește prelucrarea datelor cu caracter personal și privind libera circulație a acestor date și de abrogare a Directivei 95/46/CE (regulamentul general privind protecția datelor) și ale legislației naționale.
11.3. Datele cu caracter personal comunicate în cadrul prezentului contract vor fi prelucrate în scopul executării prezentului contract.
11.4. Datele cu caracter personal colectate și prelucrate în vederea executării prezentului contract sunt următoarele: nume și prenume, adresa, serie și număr carte de identitate, cod numeric personal, număr de telefon, tichet de parcare.
11.5. Datele personale comunicate în cadrul prezentului contract pot fi comunicate instituțiilor publice, în conformitate cu prevederile legale în vigoare.
11.6. În situația în care este necesară prelucrarea datelor personale în alte scopuri decât cele prevăzute la pct. 11.3, partea care realizează prelucrarea va informa cealaltă parte și îi va solicita acordul scris cu privire la prelucrarea datelor cu caracter personal, în conformitate cu prevederile legislației în vigoare.
11.7. Părțile își garantează reciproc dreptul la informare și acces la datele cu caracter personal, dreptul la rectificare, actualizare, portabilitate, ștergere, la restricționare și opoziție în conformitate cu prevederile legislației în vigoare.
11.8. Datele personale înscrise în prezentul contract sunt păstrate pe întreaga perioadă de executare a contractului și ulterior încetării acestuia, în conformitate cu prevederile legale referitoare la arhivarea documentelor.

XII. CLAUZE FINALE
12.1. Prezentul contract reprezintă voința părților și înlătură orice altă înțelegere verbală dintre acestea, anterioară sau ulterioară încheierii lui.
12.2. Prin semnarea contractului părțile au luat la cunoștință faptul că declararea unei identități false și uzul de fals se pedepsesc conform art. 323 și 327 din Codul Penal.
12.3. Prezentul contract s-a încheiat într-un număr de 2 exemplare.

Locatar: {nume}. Declar că am luat la cunoștință clauzele contractului și mă angajez să respect regulamentul de funcționare.
TXT;

    protected $table = 'sanius_settings';

    protected $fillable = [
        'event_id', 'marketplace_organizer_id', 'mode', 'members', 'included_tows',
        'products', 'parking', 'ping_timeout_seconds', 'rental', 'contract',
    ];

    protected $casts = [
        'members' => 'array',
        'products' => 'array',
        'parking' => 'array',
        'rental' => 'array',
        'contract' => 'array',
        'included_tows' => 'integer',
        'ping_timeout_seconds' => 'integer',
    ];

    public function isOff(): bool
    {
        return ! in_array($this->mode, ['test', 'live'], true);
    }

    public function isTestMode(): bool
    {
        return $this->mode === 'test';
    }

    /** Rolul unui membru in circuit (null = nu are rol). */
    public function roleOf(?int $memberId): ?string
    {
        if (! $memberId) {
            return null;
        }
        foreach ((array) $this->members as $m) {
            if ((int) ($m['member_id'] ?? 0) === $memberId) {
                $role = $m['role'] ?? null;

                return in_array($role, self::ROLES, true) ? $role : null;
            }
        }

        return null;
    }

    public function productId(string $key): ?int
    {
        $id = $this->products[$key] ?? null;

        return $id ? (int) $id : null;
    }

    public function parkingRules(): array
    {
        $own = array_filter((array) $this->parking, fn ($v) => $v !== null && $v !== '');

        return array_map('intval', array_merge(self::PARKING_DEFAULTS, array_intersect_key($own, self::PARKING_DEFAULTS)));
    }

    /** @return array{cycle_minutes: int, tows_per_cycle: int, grace_minutes: int, weekend_extra_dates: string[]} */
    public function rentalRules(): array
    {
        $own = (array) $this->rental;
        $rules = self::RENTAL_DEFAULTS;
        foreach (array_keys(self::RENTAL_DEFAULTS) as $k) {
            if (isset($own[$k]) && $own[$k] !== '' && is_numeric($own[$k])) {
                $rules[$k] = (int) $own[$k];
            }
        }
        $rules['cycle_minutes'] = max(1, $rules['cycle_minutes']);
        $rules['tows_per_cycle'] = max(0, $rules['tows_per_cycle']);
        $rules['grace_minutes'] = max(0, $rules['grace_minutes']);
        $rules['weekend_extra_dates'] = self::cleanDates($own['weekend_extra_dates'] ?? []);

        return $rules;
    }

    /** @return string[] zile Y-m-d, unice, sortate */
    public static function cleanDates(mixed $dates): array
    {
        $out = [];
        foreach ((array) $dates as $d) {
            $d = substr(trim((string) $d), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
                $out[$d] = true;
            }
        }
        $out = array_keys($out);
        sort($out);

        return $out;
    }

    /** @return array{required: bool, title: string, body: string, version: int, stations: string[]} */
    public function contractRules(): array
    {
        $own = (array) $this->contract;
        $stations = array_values(array_filter(array_map(
            fn ($s) => mb_substr(trim((string) $s), 0, 40),
            (array) ($own['stations'] ?? [])
        ), fn ($s) => $s !== ''));

        return [
            'required' => (bool) ($own['required'] ?? false),
            'title' => trim((string) ($own['title'] ?? '')) ?: self::CONTRACT_DEFAULT_TITLE,
            'body' => trim((string) ($own['body'] ?? '')) ?: self::CONTRACT_DEFAULT_BODY,
            'version' => max(1, (int) ($own['version'] ?? 1)),
            'stations' => $stations ?: ['Casa 1'],
        ];
    }
}
