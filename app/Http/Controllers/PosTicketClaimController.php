<?php

namespace App\Http\Controllers;

use App\Models\PosTicketClaim;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PosTicketClaimController extends Controller
{
    /**
     * Show the claim form page
     */
    public function show(string $token)
    {
        $claim = PosTicketClaim::where('token', $token)->first();

        if (!$claim) {
            return response()->view('pos-claim', [
                'error' => 'not_found',
                'claim' => null,
            ], 404);
        }

        if ($claim->isClaimed()) {
            return view('pos-claim', [
                'error' => 'already_claimed',
                'claim' => $claim,
            ]);
        }

        if ($claim->isExpired()) {
            return view('pos-claim', [
                'error' => 'expired',
                'claim' => $claim,
            ]);
        }

        // Determine which step to show
        $step = $claim->email ? 'optional' : 'required';

        return view('pos-claim', [
            'error' => null,
            'claim' => $claim,
            'step' => $step,
        ]);
    }

    /**
     * Check claim status (for mobile app polling)
     */
    public function status(string $token)
    {
        $claim = PosTicketClaim::where('token', $token)->first();

        if (!$claim) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $claim->isExpired() ? 'expired' : $claim->status,
                'has_email' => !empty($claim->email),
                'is_claimed' => $claim->isClaimed(),
                'customer_name' => $claim->first_name ? ($claim->first_name . ' ' . $claim->last_name) : null,
                'customer_email' => $claim->email,
            ],
        ]);
    }

    /**
     * Step 1: Submit required fields (first_name, last_name, email)
     */
    public function submitRequired(Request $request, string $token)
    {
        $claim = PosTicketClaim::where('token', $token)->first();

        if (!$claim || !$claim->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Link invalid sau expirat.',
            ], 422);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        $order = $claim->order()->with(['tickets.ticketType', 'event', 'marketplaceClient'])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Comanda nu a fost găsită.',
            ], 404);
        }

        $client = $order->marketplaceClient;

        // Find or create MarketplaceCustomer
        $customer = null;
        if ($client) {
            $customer = MarketplaceCustomer::where('email', $validated['email'])
                ->where('marketplace_client_id', $client->id)
                ->first();

            if (!$customer) {
                $customer = MarketplaceCustomer::create([
                    'marketplace_client_id' => $client->id,
                    'email' => $validated['email'],
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'status' => 'active',
                ]);
            } else {
                if (empty($customer->first_name)) {
                    $customer->update([
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                    ]);
                }
            }
        }

        // Update order with customer info
        $updateData = [
            'customer_email' => $validated['email'],
            'customer_name' => $validated['first_name'] . ' ' . $validated['last_name'],
        ];
        if ($customer) {
            $updateData['marketplace_customer_id'] = $customer->id;
        }
        $order->update($updateData);

        // Update all tickets on this order with attendee info
        $fullName = $validated['first_name'] . ' ' . $validated['last_name'];
        foreach ($order->tickets as $ticket) {
            $ticket->update([
                'attendee_name' => $fullName,
                'attendee_email' => $validated['email'],
            ]);
        }

        // Save required data on the claim record
        $claim->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
        ]);

        // Send ticket email
        try {
            $this->sendTicketEmail($order, $validated['email'], $claim->event_name);
        } catch (\Throwable $e) {
            Log::error('PosTicketClaim: Failed to send ticket email', [
                'claim_id' => $claim->id,
                'order_id' => $order->id,
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'step' => 'optional',
            'message' => 'Datele au fost salvate! Biletele vor fi trimise pe email.',
        ]);
    }

    /**
     * Step 2: Submit optional fields (phone, city, gender, date_of_birth)
     */
    public function submitOptional(Request $request, string $token)
    {
        $claim = PosTicketClaim::where('token', $token)->first();

        if (!$claim || $claim->isClaimed() || $claim->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Link invalid sau expirat.',
            ], 422);
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
        ]);

        // Update claim record
        $claim->update(array_filter($validated, fn($v) => $v !== null));

        // Update MarketplaceCustomer if exists
        if ($claim->email) {
            $order = $claim->order()->with('marketplaceClient')->first();
            $client = $order?->marketplaceClient;

            if ($client) {
                $customer = MarketplaceCustomer::where('email', $claim->email)
                    ->where('marketplace_client_id', $client->id)
                    ->first();

                if ($customer) {
                    $updates = [];
                    if (!empty($validated['phone']) && empty($customer->phone)) {
                        $updates['phone'] = $validated['phone'];
                    }
                    if (!empty($validated['city']) && empty($customer->city)) {
                        $updates['city'] = $validated['city'];
                    }
                    if (!empty($validated['gender']) && empty($customer->gender)) {
                        $updates['gender'] = $validated['gender'];
                    }
                    if (!empty($validated['date_of_birth']) && empty($customer->birth_date)) {
                        $updates['birth_date'] = $validated['date_of_birth'];
                    }
                    if (!empty($updates)) {
                        $customer->update($updates);
                    }
                }
            }
        }

        // Mark as claimed
        $claim->markClaimed();

        return response()->json([
            'success' => true,
            'complete' => true,
            'message' => 'Mulțumim! Datele tale au fost salvate.',
        ]);
    }

    /**
     * Skip optional step and mark as claimed
     */
    public function skipOptional(Request $request, string $token)
    {
        $claim = PosTicketClaim::where('token', $token)->first();

        if (!$claim || $claim->isClaimed() || $claim->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Link invalid sau expirat.',
            ], 422);
        }

        $claim->markClaimed();

        return response()->json([
            'success' => true,
            'complete' => true,
            'message' => 'Finalizat!',
        ]);
    }

    /**
     * Direct download - generate PDF ticket and stream to browser
     */
    public function download(string $token)
    {
        $claim = PosTicketClaim::where('token', $token)->first();

        if (!$claim) {
            return response()->view('pos-claim', ['error' => 'not_found', 'claim' => null], 404);
        }

        if ($claim->isExpired()) {
            return response()->view('pos-claim', ['error' => 'expired', 'claim' => $claim]);
        }

        $order = $claim->order()->with(['tickets.ticketType', 'event.venue', 'marketplaceClient'])->first();

        if (!$order) {
            return response()->view('pos-claim', ['error' => 'not_found', 'claim' => null], 404);
        }

        // Mark as claimed if still pending (direct download = no personal data)
        if ($claim->isPending()) {
            $claim->markClaimed();
        }

        $event = $order->event;
        $venue = $event?->venue;
        $client = $order->marketplaceClient;

        // Resolve venue name (translatable)
        $rawVenueName = $venue?->name;
        $venueName = is_array($rawVenueName)
            ? ($rawVenueName['ro'] ?? $rawVenueName['en'] ?? reset($rawVenueName) ?: null)
            : $rawVenueName;

        // Build venue location string
        $venueLocation = $venueName;
        if ($venue?->city) {
            $venueLocation = $venueLocation ? "{$venueLocation}, {$venue->city}" : $venue->city;
        }
        if ($venue?->address) {
            $venueLocation = $venueLocation ? "{$venueLocation}, {$venue->address}" : $venue->address;
        }

        // Event date formatted
        $eventDateFormatted = null;
        if ($event?->event_date) {
            $eventDateFormatted = $event->event_date instanceof \Carbon\Carbon
                ? $event->event_date->format('d.m.Y')
                : $event->event_date;
        }

        // Access time (door_time or start_time)
        $accessTime = $event?->door_time ?? $event?->start_time;

        // Organizer data
        $organizerName = $client?->company_name ?? $client?->name ?? null;
        $organizerCui = $client?->cui ?? null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pos-claim-download', [
            'claim' => $claim,
            'order' => $order,
            'event' => $event,
            'venueLocation' => $venueLocation,
            'eventDateFormatted' => $eventDateFormatted,
            'accessTime' => $accessTime,
            'organizerName' => $organizerName,
            'organizerCui' => $organizerCui,
        ])->setOption('isRemoteEnabled', true)
          ->setPaper([0, 0, 396, 700], 'portrait');

        $safeName = preg_replace('/[^a-zA-Z0-9 ]/', '', $claim->event_name);
        $safeName = str_replace(' ', '-', trim($safeName));
        $filename = 'bilet-' . mb_substr($safeName, 0, 30) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Send ticket email to customer
     */
    private function sendTicketEmail(Order $order, string $email, string $eventName): void
    {
        $order->load(['tickets.ticketType', 'marketplaceClient']);
        $client = $order->marketplaceClient;

        $ticketRows = '';
        foreach ($order->tickets as $ticket) {
            $typeName = $ticket->ticketType?->name ?? 'Bilet';
            $code = $ticket->code;
            $ticketRows .= "<tr><td style='padding:8px;border-bottom:1px solid #eee;'>{$typeName}</td><td style='padding:8px;border-bottom:1px solid #eee;font-family:monospace;'>{$code}</td></tr>";
        }

        $totalFormatted = number_format($order->total, 2, ',', '.') . ' ' . ($order->currency ?? 'RON');
        $marketplaceName = $client?->name ?? 'AmBilet';

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#333;'>Biletele tale pentru {$eventName}</h2>
            <p>Comanda: <strong>{$order->order_number}</strong></p>
            <p>Total: <strong>{$totalFormatted}</strong></p>
            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='background:#f5f5f5;'>
                    <th style='padding:10px;text-align:left;'>Tip bilet</th>
                    <th style='padding:10px;text-align:left;'>Cod</th>
                </tr>
                {$ticketRows}
            </table>
            <p style='color:#666;font-size:13px;'>Prezintă acest email sau codurile la intrare.</p>
            <p style='color:#999;font-size:12px;margin-top:30px;'>Trimis de {$marketplaceName}</p>
        </div>";

        $subject = "Biletele tale - {$eventName}";

        // Route through marketplace transport so the slug auto-routes to the
        // transactional provider (ticket_delivery is in EmailRouting whitelist).
        if ($client) {
            \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                $client,
                $email,
                '',
                $subject,
                $html,
                [
                    'order_id' => $order->id,
                    'template_slug' => 'ticket_delivery',
                ]
            );
            return;
        }

        // No marketplace context — fall back to default mailer (legacy behaviour).
        $fromEmail = 'noreply@ambilet.ro';
        $fromName = $marketplaceName;
        Mail::html($html, function ($message) use ($email, $fromEmail, $fromName, $subject) {
            $message->to($email)
                ->from($fromEmail, $fromName)
                ->subject($subject);
        });
    }
}
