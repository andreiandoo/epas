<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketTemplateValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Biletul cu design propriu al tenantului demo „Federația Română de Karate WUKF”
 * (Ticket Customizer): șablon 200 × 80 mm, legat de toate competițiile tenantului.
 *
 *   php artisan wukf:seed-ticket-template
 *   php artisan wukf:seed-ticket-template --no-logo
 *   php artisan wukf:seed-ticket-template --keep-design
 *
 * Idempotent: șablonul e căutat după tenant_id + nume și actualizat, nu dublat.
 * Pur aditiv: nu șterge nimic, nu atinge alți tenanți și nu suprascrie șablonul
 * ales deja manual pe un eveniment.
 *
 * Designul folosește DOAR ce randează efectiv TicketPreviewGenerator::renderToHtml
 * (calea DomPDF din care iese PDF-ul real): chei plate pe layer (content, fontSize
 * în mm, fontWeight normal/bold, color, textAlign, shapeKind, fillColor,
 * borderRadius, qrData, src, objectFit, opacity). Fontul este mereu DejaVu Sans
 * (are diacritice românești); nu există letter-spacing, text-transform sau
 * familie monospace în acel renderer, deci textele fixe sunt scrise direct cu
 * majuscule.
 */
class WukfSeedTicketTemplate extends Command
{
    protected $signature = 'wukf:seed-ticket-template
        {--logo=https://competitie.tixello.ro/assets/logo-wukf.png : Adresa siglei (PNG); e înglobată în șablon la rulare}
        {--no-logo : Fără siglă pe bilet}
        {--keep-design : Dacă șablonul există deja, nu îi rescrie designul (păstrează modificările făcute în editor)}';

    protected $description = 'Creează/actualizează biletul cu design propriu al tenantului WUKF și îl leagă de competiții';

    private const TENANT_SLUG = 'wukf';
    private const TEMPLATE_NAME = 'WUKF — Bilet competiție';

    // Identitatea federației
    private const NAVY = '#01012F';
    private const BLUE = '#1151D3';
    private const BRIGHT = '#4B86FF';
    private const PAPER = '#EEF3FB';
    private const RED = '#E01020';
    private const SOFT = '#C9D6F5';   // text secundar pe bleumarin
    private const MUTED = '#4A5680';  // text secundar pe cotor

    /** Progresia centurilor, de la alb la negru. */
    private const BELTS = ['#FFFFFF', '#F2C200', '#EE7A1B', '#2E8B3D', '#1F5FBF', '#6B4226', '#111111'];

    private const W = 200;      // mm
    private const H = 80;       // mm
    private const STUB_X = 146; // de aici începe cotorul
    private const BELT_H = 2.6; // banda centurilor, pe marginea de jos

    private array $colCache = [];

    public function handle(): int
    {
        $this->info('Bilet WUKF — ' . self::TEMPLATE_NAME);

        // ── Pași obligatorii ────────────────────────────────────────────────
        try {
            $tenant = Tenant::where('slug', self::TENANT_SLUG)->first();
        } catch (\Throwable $e) {
            $this->error('Nu pot citi tenantul: ' . $e->getMessage());

            return self::FAILURE;
        }
        if (! $tenant) {
            $this->error('Tenantul „' . self::TENANT_SLUG . '” nu există. Rulează întâi: php artisan wukf:seed');

            return self::FAILURE;
        }
        $this->line("  tenant: #{$tenant->id} {$tenant->name}");

        $logoSrc = $this->option('no-logo') ? null : $this->resolveLogo((string) $this->option('logo'));
        $data = $this->templateData($logoSrc);

        $validation = app(TicketTemplateValidator::class)->validate($data);
        foreach ($validation['warnings'] ?? [] as $w) {
            $this->warn('  [validator] ' . $w);
        }
        if (empty($validation['ok'])) {
            $this->error('Șablonul nu trece de TicketTemplateValidator — nu am salvat nimic:');
            foreach ($validation['errors'] ?? [] as $err) {
                $this->line('    - ' . $err);
            }

            return self::FAILURE;
        }
        $this->line('  șablon valid: ' . self::W . ' × ' . self::H . ' mm, ' . count($data['layers']) . ' straturi');

        try {
            $template = $this->seedTemplate($tenant, $data);
        } catch (\Throwable $e) {
            $this->error('Eroare la salvarea șablonului: ' . $e->getMessage());
            $this->line('  ' . $e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }

        // ── Pași opționali: avertizează și merge mai departe ─────────────────
        $attached = 0;
        $already = 0;
        $skipped = [];
        $this->guarded('legare evenimente', function () use ($tenant, $template, &$attached, &$already, &$skipped) {
            [$attached, $already, $skipped] = $this->attachToEvents($tenant, $template);
        });

        $this->guarded('previzualizare', function () use ($template) {
            app(TicketPreviewGenerator::class)->saveTemplatePreview($template);
            $this->line('  previzualizare regenerată');
        });

        $this->guarded('microserviciu', function () use ($tenant) {
            $active = $tenant->microservices()
                ->where('slug', 'ticket-customizer')
                ->wherePivot('is_active', true)
                ->exists();
            if (! $active) {
                $this->warn('  microserviciul „ticket-customizer” nu e activ pe tenant — editorul vizual răspunde 403 până e activat (php artisan wukf:seed îl activează).');
            }
        });

        // ── Rezumat ──────────────────────────────────────────────────────────
        $base = rtrim((string) (config('app.url') ?: 'https://core.tixello.com'), '/');
        if (! str_contains($base, 'tixello')) {
            $base = 'https://core.tixello.com';
        }

        $this->newLine();
        $this->info('Gata.');
        $this->line('  Șablon ID:        ' . $template->id . '  („' . $template->name . '”, ' . $template->status . ', implicit: ' . ($template->is_default ? 'da' : 'nu') . ')');
        $this->line('  Editor vizual:    ' . $base . '/tenant/ticket-customizer/' . $template->id . '/editor');
        $this->line('  Lista șabloane:   ' . $base . '/tenant/ticket-templates');
        $this->line('  Sigla:            ' . ($logoSrc === null ? 'fără' : (str_starts_with($logoSrc, 'data:') ? 'înglobată în șablon' : 'adresă externă (' . $logoSrc . ')')));
        $this->line('  Evenimente:       ' . $attached . ' legate acum, ' . $already . ' aveau deja acest șablon, ' . count($skipped) . ' lăsate neatinse');
        foreach ($skipped as $s) {
            $this->line('    ' . $s);
        }

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */
    /* Șablon                                                              */
    /* ------------------------------------------------------------------ */

    private function seedTemplate(Tenant $tenant, array $data): TicketTemplate
    {
        $template = TicketTemplate::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('name', self::TEMPLATE_NAME)
            ->orderBy('id')
            ->first();

        $attrs = [
            'tenant_id'   => $tenant->id,
            'name'        => self::TEMPLATE_NAME,
            'description' => 'Bilet de spectator 200 × 80 mm în identitatea Federației Române de Karate WUKF: panou bleumarin, cotor cu cod QR și banda centurilor.',
            'status'      => 'active',
            'is_default'  => true,
        ];

        if ($template) {
            if (method_exists($template, 'trashed') && $template->trashed()) {
                $template->restore();
                $this->line("  șablon restaurat din coș: #{$template->id}");
            }
            if ($this->option('keep-design') && ! empty($template->template_data['layers'] ?? [])) {
                $this->line('  --keep-design: designul existent rămâne neschimbat');
            } else {
                $attrs['template_data'] = $data;
            }
            $template->fill($this->only('ticket_templates', $attrs));
            if ($template->isDirty()) {
                $template->save();
                $this->line("  șablon actualizat: #{$template->id}");
            } else {
                $this->line("  șablon neschimbat: #{$template->id}");
            }
        } else {
            $attrs['template_data'] = $data;
            $attrs['version'] = 1;
            $template = TicketTemplate::create($this->only('ticket_templates', $attrs));
            $this->line("  șablon creat: #{$template->id}");
        }

        // Un singur șablon implicit — doar printre șabloanele ACESTUI tenant.
        $unset = TicketTemplate::where('tenant_id', $tenant->id)
            ->where('id', '!=', $template->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
        if ($unset > 0) {
            $this->line("  {$unset} alt(e) șablon(e) al(e) tenantului nu mai sunt implicite");
        }

        return $template;
    }

    /** @return array{0: int, 1: int, 2: array<int, string>} [legate acum, deja legate, sărite] */
    private function attachToEvents(Tenant $tenant, TicketTemplate $template): array
    {
        if (! $this->has('events', 'ticket_template_id')) {
            $this->warn('  coloana events.ticket_template_id lipsește — nu leg evenimentele (șablonul implicit al tenantului rămâne valabil).');

            return [0, 0, []];
        }

        $attached = 0;
        $already = 0;
        $skipped = [];

        $events = Event::where('tenant_id', $tenant->id)->orderBy('id')->get(['id', 'slug', 'ticket_template_id']);
        foreach ($events as $event) {
            $current = $event->ticket_template_id;
            $label = '#' . $event->id . ' ' . $this->plain($event->slug);

            if ((int) $current === (int) $template->id) {
                $already++;
                continue;
            }
            if (! empty($current)) {
                $skipped[] = $label . ' — are deja șablonul #' . $current . ', lăsat așa';
                continue;
            }

            // Update direct pe rând: fără observere și fără alte coloane atinse.
            Event::where('id', $event->id)->whereNull('ticket_template_id')->update(['ticket_template_id' => $template->id]);
            $attached++;
            $this->line('  legat: ' . $label);
        }

        if ($events->isEmpty()) {
            $this->warn('  tenantul nu are evenimente — nimic de legat.');
        }

        return [$attached, $already, $skipped];
    }

    /* ------------------------------------------------------------------ */
    /* Design                                                              */
    /* ------------------------------------------------------------------ */

    private function templateData(?string $logoSrc): array
    {
        $panelH = self::H - self::BELT_H;          // înălțimea de deasupra benzii centurilor
        $stubW = self::W - self::STUB_X;
        $stubC = self::STUB_X + $stubW / 2;        // axa cotorului
        $nbsp = "\u{00A0}\u{00A0}\u{00A0}";        // rămâne invizibil când locul lipsește

        $layers = [];
        $z = 0;

        $rect = function (string $id, string $name, float $x, float $y, float $w, float $h, string $fill, float $radius = 0, float $opacity = 1, string $kind = 'rect') use (&$layers, &$z) {
            $layers[] = [
                'id' => $id, 'type' => 'shape', 'z' => ++$z, 'name' => $name,
                'frame' => ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h],
                'rotation' => 0, 'opacity' => $opacity, 'visible' => true,
                'shapeKind' => $kind, 'fillColor' => $fill, 'borderColor' => $fill,
                'borderWidth' => 0, 'borderRadius' => $radius,
            ];
        };

        $text = function (string $id, string $name, string $content, float $x, float $y, float $w, float $h, float $size, string $color, string $weight = 'normal', string $align = 'left') use (&$layers, &$z) {
            $layers[] = [
                'id' => $id, 'type' => 'text', 'z' => ++$z, 'name' => $name,
                'frame' => ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h],
                'rotation' => 0, 'opacity' => 1, 'visible' => true,
                'content' => $content, 'fontSize' => $size, 'fontWeight' => $weight,
                'fontFamily' => 'Inter', 'color' => $color, 'textAlign' => $align,
            ];
        };

        // ── Fundaluri ────────────────────────────────────────────────────────
        $rect('bg_main', 'Panou principal', 0, 0, self::STUB_X, $panelH, self::NAVY);
        $rect('bg_stub', 'Cotor', self::STUB_X, 0, $stubW, $panelH, self::PAPER);
        $rect('edge_band', 'Bandă albastră', 0, 0, 4, $panelH, self::BLUE);
        $rect('accent_red', 'Accent roșu', 4, 0, 1.1, $panelH, self::RED);

        // Perforație: șir de puncte bleumarin pe muchia cotorului.
        for ($i = 0; $i < 12; $i++) {
            $rect('perf_' . ($i + 1), 'Perforație ' . ($i + 1), self::STUB_X + 0.6, round(3.6 + $i * 6.1, 2), 1.2, 1.2, self::NAVY, 0, 1, 'circle');
        }

        // Banda centurilor: șapte blocuri egale pe toată lățimea, pe marginea de jos.
        $beltNames = ['albă', 'galbenă', 'portocalie', 'verde', 'albastră', 'maro', 'neagră'];
        $beltW = self::W / count(self::BELTS);
        foreach (self::BELTS as $i => $color) {
            $x = round($i * $beltW, 2);
            $w = $i === count(self::BELTS) - 1 ? round(self::W - $x, 2) : round($beltW, 2);
            $rect('belt_' . ($i + 1), 'Centura ' . $beltNames[$i], $x, $panelH, $w, self::BELT_H, $color);
        }

        // ── Panoul principal ─────────────────────────────────────────────────
        if ($logoSrc !== null) {
            $rect('logo_disc', 'Disc siglă', 118, 8, 24, 24, self::PAPER, 0, 1, 'circle');
            $layers[] = [
                'id' => 'logo', 'type' => 'image', 'z' => ++$z, 'name' => 'Sigla WUKF',
                'frame' => ['x' => 121.75, 'y' => 11.75, 'w' => 16.5, 'h' => 16.5],
                'rotation' => 0, 'opacity' => 1, 'visible' => true,
                'src' => $logoSrc, 'objectFit' => 'contain',
            ];
        }

        $text('org_label', 'Federația', 'FEDERAȚIA ROMÂNĂ DE KARATE WUKF', 11, 8.5, 102, 3.6, 2.5, self::BRIGHT, 'bold');
        $text('event_title', 'Titlul competiției', '{{event.name}}', 11, 13.5, 102, 22.4, 5.6, '#FFFFFF', 'bold');

        $rect('divider', 'Linie despărțitoare', 11, 38, 129, 0.25, self::BRIGHT, 0, 0.4);

        $text('lbl_date', 'Etichetă dată', 'DATA', 11, 39.8, 60, 3, 2.0, self::BRIGHT, 'bold');
        $text('date', 'Data', '{{date.start_formatted}}', 11, 42.6, 78, 5, 3.5, '#FFFFFF', 'bold');
        $text('time', 'Ora', '{{date.time_label}}', 90, 43, 50, 4.4, 3.0, self::SOFT, 'normal', 'right');

        $text('lbl_venue', 'Etichetă locație', 'LOCAȚIA', 11, 48.6, 60, 3, 2.0, self::BRIGHT, 'bold');
        $text('venue', 'Locația', '{{venue.name}}', 11, 51.4, 129, 4.6, 3.2, '#FFFFFF', 'bold');
        $text('city', 'Orașul', '{{venue.city}}', 11, 55.9, 129, 3.8, 2.6, self::SOFT);

        // Blocul biletului: tipul în stânga; sector / rând / loc în dreapta — fără
        // etichete fixe, deci la accesul general partea dreaptă rămâne pur și simplu liberă.
        $rect('type_block', 'Bloc tip bilet', 11, 61.3, 129, 11.6, self::BLUE, 1.6);
        $text('ticket_type', 'Tip bilet', '{{ticket.type}}', 14.5, 62.6, 70, 4.8, 3.4, '#FFFFFF', 'bold');
        $text('ticket_price', 'Preț', '{{ticket.price}}', 14.5, 67.6, 70, 3.8, 2.6, '#DCE6FB');
        $text('seat_section', 'Sector', '{{ticket.section}}', 86, 62.9, 51, 4.2, 3.0, '#FFFFFF', 'bold', 'right');
        $text('seat_row', 'Rând și loc', '{{ticket.row}}' . $nbsp . '{{ticket.seat}}', 86, 67.6, 51, 3.8, 2.6, '#DCE6FB', 'normal', 'right');

        $text('footer', 'Microtext', 'Ticketing by Tixello', 11, 74.1, 80, 2.8, 1.9, '#7F8EC4');

        // ── Cotorul ──────────────────────────────────────────────────────────
        $sx = self::STUB_X + 2;
        $sw = $stubW - 4;
        $text('stub_label', 'Etichetă cotor', 'BILET DE ACCES', $sx, 5.5, $sw, 3.4, 2.3, self::BLUE, 'bold', 'center');

        $layers[] = [
            'id' => 'qr', 'type' => 'qr', 'z' => ++$z, 'name' => 'Cod QR',
            'frame' => ['x' => $stubC - 17.5, 'y' => 11.5, 'w' => 35, 'h' => 35],
            'rotation' => 0, 'opacity' => 1, 'visible' => true,
            'qrData' => '{{qrcode}}', 'qrForeground' => '#000000', 'qrBackground' => '#ffffff',
        ];

        $text('stub_code', 'Cod bilet', '{{ticket.code_short}}', $sx, 50.5, $sw, 6, 4.2, self::NAVY, 'bold', 'center');
        $text('stub_order', 'Număr comandă', '{{order.code}}', $sx, 57.2, $sw, 3.6, 2.4, self::MUTED, 'normal', 'center');
        $rect('stub_divider', 'Linie cotor', $stubC - 17, 62.2, 34, 0.25, self::BLUE, 0, 0.35);
        $text('stub_buyer', 'Cumpărător', '{{buyer.name}}', $sx, 64, $sw, 3.8, 2.6, self::NAVY, 'bold', 'center');
        $text('stub_hint', 'Indicație', 'Prezintă codul la intrare', $sx, 70.6, $sw, 3.2, 2.1, self::MUTED, 'normal', 'center');

        return [
            'meta' => [
                'version'      => '1.0',
                'dpi'          => 300,
                'size_mm'      => ['w' => self::W, 'h' => self::H],
                'orientation'  => 'landscape',
                'bleed_mm'     => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                'safe_area_mm' => 5,
                'background'   => ['color' => self::NAVY, 'image' => '', 'positionX' => 50, 'positionY' => 50],
                'baseTextColor' => '#ffffff',
            ],
            'assets' => [],
            'layers' => $layers,
        ];
    }

    /**
     * Sigla ca data URI înglobat în șablon (fără cerere de rețea la fiecare bilet și
     * fără canal alfa — aplatizată pe culoarea discului). Dacă nu poate fi adusă
     * acum, rămâne adresa externă: rendererul o aduce singur, iar la eșec DomPDF
     * pune doar un marcaj de imagine lipsă, nu oprește generarea PDF-ului.
     */
    private function resolveLogo(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https://#i', $url)) {
            $this->warn('  --logo trebuie să fie o adresă https — biletul rămâne fără siglă.');

            return null;
        }

        try {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 1, 'max_redirects' => 2]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false || strlen($raw) < 100 || strlen($raw) > 400 * 1024) {
                throw new \RuntimeException('răspuns gol sau prea mare');
            }
            $info = @getimagesizefromstring($raw);
            if (! $info || ! in_array($info[2] ?? 0, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                throw new \RuntimeException('nu este o imagine PNG/JPEG');
            }

            $mime = $info['mime'] ?? 'image/png';
            if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
                $src = @imagecreatefromstring($raw);
                if ($src !== false) {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $canvas = imagecreatetruecolor($w, $h);
                    imagefilledrectangle($canvas, 0, 0, $w - 1, $h - 1, imagecolorallocate($canvas, 0xEE, 0xF3, 0xFB));
                    imagealphablending($canvas, true);
                    imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
                    ob_start();
                    imagepng($canvas, null, 9);
                    $flat = (string) ob_get_clean();
                    if (strlen($flat) > 100) {
                        $raw = $flat;
                        $mime = 'image/png';
                    }
                }
            }

            $this->line('  siglă înglobată (' . round(strlen($raw) / 1024, 1) . ' KB)');

            return 'data:' . $mime . ';base64,' . base64_encode($raw);
        } catch (\Throwable $e) {
            $this->warn('  sigla nu a putut fi înglobată (' . $e->getMessage() . ') — folosesc adresa externă.');

            return $url;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Utilitare                                                           */
    /* ------------------------------------------------------------------ */

    /** Slug-ul poate fi traductibil (array) — îl aduce la text simplu pentru afișare. */
    private function plain($value): string
    {
        if (is_array($value)) {
            return (string) ($value['ro'] ?? $value['en'] ?? (reset($value) ?: ''));
        }

        return (string) $value;
    }

    private function guarded(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->warn("  [{$label}] sărit: " . $e->getMessage());
        }
    }

    /** Coloanele reale ale tabelei (cache per rulare). Gol dacă nu pot fi citite. */
    private function cols(string $table): array
    {
        if (! array_key_exists($table, $this->colCache)) {
            try {
                $this->colCache[$table] = Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $this->colCache[$table] = [];
            }
        }

        return $this->colCache[$table];
    }

    private function has(string $table, string $column): bool
    {
        return in_array($column, $this->cols($table), true);
    }

    /** Păstrează doar cheile care sunt coloane reale (protecție la schema drift). */
    private function only(string $table, array $data): array
    {
        $cols = $this->cols($table);
        if (empty($cols)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($cols));
    }
}
