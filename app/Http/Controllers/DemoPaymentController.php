<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Seating\EventSeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Pagina demo de plată (gateway simulat). Accesibilă doar prin URL semnat
 * generat de DemoProcessor. „Plătește" marchează comanda plătită + locurile
 * vândute; „Refuză" o anulează. Niciun procesator real, niciun ban.
 */
class DemoPaymentController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $success = $request->query('success', '');
        $cancel = $request->query('cancel', '');
        $pid = $request->query('pid', '');

        // Reconstruiește URL-urile de acțiune semnate (păstrează parametrii)
        $payUrl = URL::signedRoute('demo.pay.complete', [
            'order' => $order->id, 'result' => 'success', 'pid' => $pid, 'success' => $success, 'cancel' => $cancel,
        ]);
        $failUrl = URL::signedRoute('demo.pay.complete', [
            'order' => $order->id, 'result' => 'fail', 'pid' => $pid, 'success' => $success, 'cancel' => $cancel,
        ]);

        $meta = $order->meta ?? [];
        $seatLabels = [];
        foreach ($order->tickets()->get() as $t) {
            if (! empty($t->meta['seat_label'])) {
                $seatLabels[] = $t->meta['seat_label'];
            }
        }

        return view('demo-payment.pay', [
            'order'      => $order,
            'total'      => number_format(($order->total_cents ?? 0) / 100, 2, ',', '.'),
            'customer'   => $meta['customer_name'] ?? $order->customer_email,
            'seatLabels' => $seatLabels,
            'payUrl'     => $payUrl,
            'failUrl'    => $failUrl,
        ]);
    }

    public function complete(Request $request, Order $order)
    {
        $result = $request->query('result', 'success');
        $success = $request->query('success', '');
        $cancel = $request->query('cancel', '');

        if ($result === 'success') {
            // Marchează locurile vândute (din meta seated_items)
            foreach (($order->meta['seated_items'] ?? []) as $item) {
                $seatingId = $item['event_seating_id'] ?? null;
                $seatUids = $item['seat_uids'] ?? [];
                if ($seatingId && ! empty($seatUids)) {
                    EventSeat::where('event_seating_id', $seatingId)
                        ->whereIn('seat_uid', $seatUids)
                        ->whereIn('status', ['available', 'held'])
                        ->update([
                            'status'         => 'sold',
                            'version'        => DB::raw('version + 1'),
                            'last_change_at' => now(),
                        ]);
                }
            }

            // paid → observer-ul Order marchează biletele 'valid'
            if (! in_array($order->status, ['paid', 'confirmed', 'completed'])) {
                $order->update(['status' => 'paid']);
            }

            // Activează abonamentul asociat (dacă e o comandă de abonament)
            $subId = $order->meta['subscription_id'] ?? null;
            if ($subId) {
                \App\Models\TenantCustomerSubscription::where('id', $subId)
                    ->where('status', 'pending')
                    ->update(['status' => 'active']);
            }

            $redirect = $success ?: url('/');
            $sep = str_contains($redirect, '?') ? '&' : '?';
            return redirect()->away($redirect . $sep . 'order=' . $order->id . '&status=paid');
        }

        // Eșec → anulează (observer-ul eliberează locurile + anulează biletele)
        if (! in_array($order->status, ['paid', 'confirmed', 'completed'])) {
            $order->update(['status' => 'cancelled']);
        }

        $redirect = $cancel ?: url('/');
        $sep = str_contains($redirect, '?') ? '&' : '?';
        return redirect()->away($redirect . $sep . 'status=cancelled');
    }
}
