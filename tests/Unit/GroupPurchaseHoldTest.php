<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\Tixello\GroupPurchaseService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Regula de rezervare pentru „cumpărăm împreună".
 *
 * E singura bucată din flux care nu se poate verifica la mână: depinde de
 * relația dintre trei momente (acum, +48h, ziua dinaintea evenimentului), iar
 * greșeala nu se vede decât atunci când un grup ține locuri până în seara
 * concertului — adică prea târziu.
 *
 * Nu atinge baza de date: `holdDeadline` primește un `Event` neînregistrat și
 * un „acum" explicit.
 */
class GroupPurchaseHoldTest extends TestCase
{
    private function eventOn(string $date): Event
    {
        $e = new Event();
        $e->event_date = $date;

        return $e;
    }

    public function test_fereastra_e_de_48h_cand_evenimentul_e_departe(): void
    {
        $now = Carbon::parse('2026-08-14 10:00:00');
        $deadline = (new GroupPurchaseService())->holdDeadline($this->eventOn('2026-09-20'), $now);

        $this->assertNotNull($deadline);
        $this->assertSame('2026-08-16 10:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_fereastra_nu_intra_in_ziua_evenimentului(): void
    {
        /* Evenimentul e peste doua zile: 48h ar duce fix in ziua lui. Termenul
           se taie la sfarsitul zilei dinainte. */
        $now = Carbon::parse('2026-08-14 10:00:00');
        $deadline = (new GroupPurchaseService())->holdDeadline($this->eventOn('2026-08-16'), $now);

        $this->assertNotNull($deadline);
        $this->assertSame('2026-08-15', $deadline->format('Y-m-d'));
        $this->assertSame('23:59:59', $deadline->format('H:i:s'));
    }

    public function test_evenimentul_de_maine_se_poate_pana_diseara(): void
    {
        /* „Cu cel putin o zi inainte" inseamna ca ziua de AZI e inca buna
           pentru un eveniment de maine — dar numai pana la miezul noptii. */
        $now = Carbon::parse('2026-08-14 10:00:00');
        $deadline = (new GroupPurchaseService())->holdDeadline($this->eventOn('2026-08-15'), $now);

        $this->assertNotNull($deadline);
        $this->assertSame('2026-08-14 23:59:59', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_refuza_cand_evenimentul_e_azi(): void
    {
        $now = Carbon::parse('2026-08-14 10:00:00');

        $this->assertNull((new GroupPurchaseService())->holdDeadline($this->eventOn('2026-08-14'), $now));
    }

    public function test_refuza_cand_ar_ramane_sub_o_ora(): void
    {
        /* 23:30, eveniment MAINE: termenul ar fi 23:59:59 azi — 29 de minute.
           N-ai timp nici sa inviti, nici sa plateasca cineva. */
        $now = Carbon::parse('2026-08-14 23:30:00');

        $this->assertNull((new GroupPurchaseService())->holdDeadline($this->eventOn('2026-08-15'), $now));
    }

    public function test_aceeasi_ora_tarzie_e_ok_daca_evenimentul_e_poimaine(): void
    {
        /* Acelasi „acum", alt eveniment: termenul devine maine seara, adica
           mai mult de 24 de ore. Diferenta e data de eveniment, nu de ora. */
        $now = Carbon::parse('2026-08-14 23:30:00');
        $deadline = (new GroupPurchaseService())->holdDeadline($this->eventOn('2026-08-16'), $now);

        $this->assertNotNull($deadline);
        $this->assertSame('2026-08-15 23:59:59', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_fara_data_nu_se_poate_calcula(): void
    {
        $e = new Event();
        $e->event_date = null;
        $e->range_start_date = null;

        $this->assertNull((new GroupPurchaseService())->holdDeadline($e, Carbon::parse('2026-08-14 10:00:00')));
    }
}
