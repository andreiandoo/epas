<?php

namespace App\Services\Activities;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceEmailTemplate;
use App\Models\Order;
use App\Services\MarketplaceEmailService;
use Illuminate\Support\Facades\Log;

/**
 * Order confirmation e-mail for activity orders (access tickets, experiences,
 * packages). The event e-mail can't describe them: it looked for an event and
 * printed "Eveniment" and "Bilet × 1 … 0,00". Same transport, log and admin
 * copy as the event e-mail.
 */
class ActivityOrderEmail
{
    public function send(Order $order): void
    {
        $marketplace = $order->marketplaceClient;
        if (!$marketplace || !$order->customer_email) {
            return;
        }

        $mail = $this->compose($order);
        [$subject, $html, $name, $money, $headline, $ticketCount] = [$mail['subject'], $mail['html'], $mail['name'], $mail['money'], $mail['headline'], $mail['ticket_count']];
        [$first, $ticketsHtml, $downloadUrl, $currency] = [$mail['first'], $mail['tickets_html'], $mail['download_url'], $mail['currency']];

        // The marketplace's own "ticket_purchase" template, when it has one.
        $template = MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace->id)
            ->where('slug', 'ticket_purchase')->where('is_active', true)->first();
        if ($template) {
            $rendered = $template->render([
                'customer_name'    => $name,
                'customer_email'   => $order->customer_email,
                'order_number'     => $order->order_number,
                'event_name'       => $headline,
                'event_date'       => trim(($first['date_label'] ?? '') . ' · ' . ($first['time_label'] ?? ''), ' ·'),
                'venue_name'       => $first['location']['name'] ?? '',
                'venue_city'       => $first['location']['city'] ?? '',
                'venue_location'   => implode(', ', array_filter([$first['location']['name'] ?? '', $first['location']['city'] ?? ''])),
                'ticket_count'     => (string) $ticketCount,
                'total_amount'     => $money($order->total),
                'insurance_amount' => '',
                'marketplace_name' => $marketplace->name,
                'tickets_list'     => $ticketsHtml,
                'download_url'     => $downloadUrl,
            ]);
            $subject = ($rendered['subject'] ?? '') ?: $subject;
            $html    = ($rendered['body_html'] ?? '') ?: $html;
        }

        BaseController::sendViaMarketplace($marketplace, $order->customer_email, $name, $subject, $html, [
            'marketplace_customer_id' => $order->marketplace_customer_id,
            'order_id'                => $order->id,
            'template_slug'           => 'ticket_purchase',
        ]);

        try {
            (new MarketplaceEmailService($marketplace))->sendAdminNotification(
                slug: 'admin_new_order',
                settingKey: 'orders_email',
                variables: [
                    'order_number'   => $order->order_number,
                    'customer_name'  => $name,
                    'customer_email' => $order->customer_email,
                    'total_amount'   => number_format((float) $order->total, 2),
                    'currency'       => $currency,
                    'tickets_count'  => $ticketCount,
                    'event_name'     => $headline,
                    'view_url'       => url("/marketplace/orders/{$order->id}"),
                ],
                fallbackSubject: '[Admin] Comandă nouă: ' . $order->order_number . ' · ' . $headline,
                fallbackHtml: '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin:0 0 20px;font-family:Arial,sans-serif;font-size:13px;">Notificare admin · comandă nouă de activități · '
                    . e($order->order_number) . ' · ' . e($money($order->total)) . ' · <a href="' . e(url("/marketplace/orders/{$order->id}")) . '">vezi comanda</a></div>' . $html,
            );
        } catch (\Throwable $e) {
            Log::warning('Activity order admin notification failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * Subject and body of the confirmation (no template, nothing sent).
     */
    public function compose(Order $order): array
    {
        $marketplace = $order->marketplaceClient;
        $lines    = BookingDescriber::lines($order);
        $currency = $order->currency ?: 'RON';
        $money    = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $currency;
        $name     = $order->customer_name ?: 'Client';
        $first    = $lines[0] ?? null;
        $headline = ($first['title'] ?? 'Activitate') . (count($lines) > 1 ? ' + ' . (count($lines) - 1) . ' alte' : '');
        $subject  = "Confirmare comandă #{$order->order_number} — {$headline}";

        $domain = rtrim((string) ($marketplace->domain ?? ''), '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }
        $downloadUrl = $domain ? $domain . '/api/proxy.php?action=order.download-tickets-pdf&order=' . urlencode($order->order_number) : '';
        $accountUrl  = $domain ? $domain . '/cont/bilete' : '';

        $ticketsHtml = '';
        $ticketCount = 0;
        foreach ($lines as $line) {
            $ticketsHtml .= $this->lineHtml($line, $name, $money, $ticketCount);
        }

        $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
        $html .= '<body style="margin:0;padding:0;background:#f4f4f1;font-family:Arial,Helvetica,sans-serif;color:#212121;">';
        $html .= '<div style="max-width:640px;margin:0 auto;padding:24px 16px;">';
        $html .= '<div style="text-align:center;padding:12px 0 20px;"><h1 style="margin:0;font-size:22px;color:#2C5E4D;">' . e($marketplace->name) . '</h1></div>';
        $html .= '<div style="background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;">';
        $html .= '<p style="margin:0 0 12px;font-size:16px;">Salut, <strong>' . e($name) . '</strong>!</p>';
        $html .= '<p style="margin:0 0 12px;font-size:15px;color:#444;">Comanda ta <strong>#' . e($order->order_number) . '</strong> este confirmată.</p>';
        $html .= '<p style="margin:0;font-size:15px;color:#444;">Mai jos ai biletele. La intrare arată codul QR de pe telefon sau tipărit.</p>';
        $html .= '</div>';
        $html .= $ticketsHtml;

        if ($downloadUrl) {
            $html .= '<div style="text-align:center;margin:24px 0 8px;"><a href="' . e($downloadUrl) . '" style="display:inline-block;background:#1B7F4E;color:#fff;font-size:16px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">Descarcă biletele (PDF)</a></div>';
        }
        if ($accountUrl) {
            $html .= '<p style="text-align:center;margin:0 0 20px;font-size:13px;color:#666;">Le găsești oricând și în <a href="' . e($accountUrl) . '" style="color:#1B7F4E;">contul tău</a>.</p>';
        }

        // Payment details
        $meta = $order->meta ?? [];
        $html .= '<div style="background:#fff;border-radius:12px;padding:20px 24px;">';
        $html .= '<h3 style="margin:0 0 12px;font-size:16px;">Detalii comandă</h3>';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;" cellpadding="0" cellspacing="0">';
        $html .= $this->row('Nr. comandă', '#' . e($order->order_number));
        $html .= $this->row('Data comenzii', e(($order->paid_at ?? $order->created_at)?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? ''));
        $html .= '<tr><td colspan="2" style="padding:8px 0;"><hr style="border:none;border-top:1px solid #eee;margin:0;"></td></tr>';
        foreach ($lines as $line) {
            $label = $line['title'] . ($line['variant'] ? ' — ' . $line['variant'] : '') . ' × ' . $line['quantity'];
            $html .= $this->row(e($label), $money($line['total']), true);
        }
        $html .= '<tr><td colspan="2" style="padding:8px 0;"><hr style="border:none;border-top:1px solid #eee;margin:0;"></td></tr>';
        $html .= $this->row('Subtotal', $money($order->subtotal), true);
        if ((float) ($meta['commission_added_on_top'] ?? 0) > 0) {
            $html .= $this->row('Comision serviciu', $money($meta['commission_added_on_top']), true);
        }
        if ($order->processing_fee_passed && (int) $order->processing_fee_cents > 0) {
            $html .= $this->row('Taxă procesare card', $money($order->processing_fee_cents / 100), true);
        }
        if ((float) ($order->points_discount ?? 0) > 0) {
            $html .= $this->row('Plătit cu ' . (int) $order->points_used . ' puncte', '-' . $money($order->points_discount), true);
        }
        $html .= '<tr style="border-top:2px solid #212121;"><td style="padding:10px 0 4px;font-weight:700;font-size:16px;">Total plătit</td><td style="padding:10px 0 4px;text-align:right;font-weight:700;font-size:16px;">' . $money($order->total) . '</td></tr>';
        $html .= '</table></div>';
        $html .= '<p style="text-align:center;padding:20px 0;font-size:12px;color:#999;margin:0;">Acest e-mail a fost trimis de ' . e($marketplace->name) . '.</p>';
        $html .= '</div></body></html>';

        return [
            'subject'      => $subject,
            'html'         => $html,
            'name'         => $name,
            'money'        => $money,
            'headline'     => $headline,
            'ticket_count' => $ticketCount,
            'first'        => $first,
            'tickets_html' => $ticketsHtml,
            'download_url' => $downloadUrl,
            'currency'     => $currency,
        ];
    }

    private function lineHtml(array $line, string $customerName, callable $money, int &$ticketCount): string
    {
        $h  = '<div style="margin-bottom:24px;border:1px solid #e3e3dc;border-radius:12px;overflow:hidden;background:#fff;">';
        $h .= '<div style="background:#2C5E4D;color:#fff;padding:18px 22px;">';
        $h .= '<h2 style="margin:0 0 6px;font-size:19px;">' . e($line['title']) . '</h2>';
        $facts = array_filter([
            $line['location'] ? implode(', ', array_filter([$line['location']['name'], $line['location']['city']])) : null,
            $line['date_label'],
            $line['time_label'],
        ]);
        $h .= '<p style="margin:0;font-size:14px;color:#dbe8e2;">' . e(implode(' · ', $facts)) . '</p>';
        $h .= '</div>';

        $details = [];
        if ($line['variant']) {
            $details[] = ['Bilet', e($line['variant']) . ' × ' . $line['quantity']];
        }
        if (!empty($line['location']['address'])) {
            $addr = e($line['location']['address']);
            if (!empty($line['location']['maps'])) {
                $addr .= ' · <a href="' . e($line['location']['maps']) . '" style="color:#1B7F4E;">hartă</a>';
            }
            $details[] = ['Adresă', $addr];
        }
        if ($line['meeting_point']) {
            $details[] = ['Punct de întâlnire', e($line['meeting_point'])];
        }
        if ($line['vehicle_plate']) {
            $details[] = ['Număr mașină', e($line['vehicle_plate'])];
        }
        foreach ($line['addons'] as $addon) {
            $details[] = ['Supliment', e($addon['name']) . ' × ' . (int) $addon['qty'] . ((int) ($addon['included'] ?? 0) > 0 ? ' (' . (int) $addon['included'] . ' incluse)' : '')];
        }
        foreach ($line['components'] as $c) {
            $details[] = ['Include', e($c['title']) . ($c['variant'] ? ' — ' . e($c['variant']) : '') . ' × ' . $c['quantity'] . ' · ' . e($c['time_label'])];
        }
        if ($details) {
            $h .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:14px 0 4px;" cellpadding="0" cellspacing="0">';
            foreach ($details as [$k, $v]) {
                $h .= '<tr><td style="padding:3px 12px 3px 22px;color:#777;white-space:nowrap;vertical-align:top;">' . $k . '</td><td style="padding:3px 22px 3px 0;">' . $v . '</td></tr>';
            }
            $h .= '</table>';
        }

        $tickets = collect($line['tickets']);
        foreach ($line['components'] as $c) {
            $tickets = $tickets->merge($c['tickets']);
        }
        foreach ($tickets as $ticket) {
            $ticketCount++;
            $info = BookingDescriber::ticket($ticket) ?? [];
            $qr = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                'size' => '180x180', 'data' => $ticket->getVerifyUrl(), 'color' => '212121', 'margin' => '0', 'format' => 'png',
            ]);
            $h .= '<table style="width:100%;border-top:1px dashed #d6d6cf;" cellpadding="0" cellspacing="0"><tr>';
            $h .= '<td style="padding:18px 18px 18px 22px;width:150px;vertical-align:top;text-align:center;"><img src="' . $qr . '" alt="Cod QR" width="130" height="130" style="display:block;border:1px solid #eee;border-radius:8px;"><p style="margin:6px 0 0;font-size:12px;color:#666;font-family:monospace;">' . e($ticket->code) . '</p></td>';
            $h .= '<td style="padding:18px 22px 18px 0;vertical-align:top;font-size:14px;">';
            $h .= '<p style="margin:0 0 6px;font-size:16px;font-weight:700;">' . e($info['ticket_type'] ?? 'Bilet') . '</p>';
            if (!empty($info['package'])) {
                $h .= '<p style="margin:0 0 4px;color:#555;">' . e($info['name']) . '</p>';
            }
            if (empty($ticket->meta['companion'])) {
                $h .= '<p style="margin:0 0 4px;color:#555;">Beneficiar: <strong>' . e($ticket->attendee_name ?: $customerName) . '</strong></p>';
            }
            $h .= '<p style="margin:0;color:#555;">' . e(trim(($info['date_label'] ?? '') . ' · ' . ($info['time_label'] ?? ''), ' ·')) . '</p>';
            $h .= '</td></tr></table>';
        }

        return $h . '</div>';
    }

    private function row(string $label, string $value, bool $right = false): string
    {
        return '<tr><td style="padding:4px 0;color:#666;">' . $label . '</td><td style="padding:4px 0;' . ($right ? 'text-align:right;' : 'font-weight:600;') . '">' . $value . '</td></tr>';
    }
}
