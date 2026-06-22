<?php

namespace App\Jobs;

use App\Mail\LeisureTicketsConfirmation;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Trimite emailul tranzactional cu biletele leisure (Sf. Ana, alte locatii
 * agrement). Dispatchat din LeisureController::posSale dupa DB::commit, sau
 * din CheckoutController public la order confirmation.
 *
 * Locale-ul mail-ului = $order->locale (fallback 'ro'). Cere ca aria
 * Mail/LeisureTicketsConfirmation + view-ul mail.leisure.tickets sa fie
 * existente — vezi commit-ul "Leisure email transactional".
 *
 * QR codes sunt generate folosind QR Server API (extern, no deps) cu
 * fallback inline base64. Daca API-ul e offline, biletul iese fara
 * imagine QR — codul text ramane lizibil + scanabil din scanner POS app.
 */
class SendLeisureTicketsEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $orderId,
        public string $eventName,
        public array $issuer,
        public ?array $issuerSecondary,
        public array $ticketsData, // [{ id, code, ticket_type, service_category, issuing_company }]
        public string $visitDate,
        public string $locale,
    ) {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            Log::warning('[SendLeisureTicketsEmail] Order not found', ['order_id' => $this->orderId]);
            return;
        }

        $to = $order->customer_email;
        if (!$to || str_contains($to, 'pos@')) {
            // Email default pentru vânzări cash în POS → nu trimitem mail.
            // Memory: pos@ambilet.ro e cash POS default, NU date reale.
            return;
        }

        // Generăm QR-uri inline (data URI base64) pentru fiecare bilet
        $ticketsWithQr = [];
        foreach ($this->ticketsData as $t) {
            $code = $t['code'] ?? '';
            $qrUri = $this->generateQrDataUri('https://ambilet.ro/v/' . $code);
            $ticketsWithQr[] = array_merge($t, ['qr_data_uri' => $qrUri]);
        }

        // Locale-ul efectiv (whitelist)
        $loc = in_array($this->locale, ['ro', 'hu', 'en'], true) ? $this->locale : 'ro';

        try {
            Mail::to($to)->send(new LeisureTicketsConfirmation(
                order: $order,
                tickets: $ticketsWithQr,
                issuer: $this->issuer,
                issuerSecondary: $this->issuerSecondary,
                eventName: $this->eventName,
                visitDate: $this->visitDate,
                locale: $loc,
            ));
        } catch (\Throwable $e) {
            Log::error('[SendLeisureTicketsEmail] send failed', [
                'order_id' => $this->orderId,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e; // retry
        }
    }

    /**
     * Genereaza QR ca data URI (PNG base64) folosind QR Server API extern.
     * Returneaza null daca API-ul nu raspunde — email-ul iese fara imagine
     * dar codul text ramane scanabil.
     */
    protected function generateQrDataUri(string $payload): ?string
    {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='
            . urlencode($payload) . '&format=png&margin=2';
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 8, 'user_agent' => 'AmBilet/1.0'],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $imageData = @file_get_contents($url, false, $context);
            if ($imageData !== false && strlen($imageData) > 100) {
                return 'data:image/png;base64,' . base64_encode($imageData);
            }
        } catch (\Throwable $e) {
            Log::debug('[QR] generate failed: ' . $e->getMessage());
        }
        return null;
    }
}
