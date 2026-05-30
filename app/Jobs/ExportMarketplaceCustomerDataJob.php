<?php

namespace App\Jobs;

use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceCustomerGdprRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bundle a customer's data into a ZIP and email a one-time download link.
 *
 * Covers:
 *   - profile.json        (the formatCustomer payload + raw user record)
 *   - orders.json         (all orders + line items)
 *   - tickets.json        (all tickets ever issued, with event title)
 *   - reviews.json        (reviews left by the customer)
 *   - support.json        (support tickets opened by the customer)
 *   - points.json         (gamification balance + history if available)
 *   - beneficiaries.json  (saved beneficiaries from /cont/setari → Familie)
 *
 * Stored at `storage/app/gdpr-exports/<token>.zip` (local disk). Link expires
 * after 14 days; cleanup is left to a future cron — older ZIPs simply 404
 * because the download endpoint enforces expires_at.
 */
class ExportMarketplaceCustomerDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 min for very heavy customers
    public int $tries   = 2;

    public function __construct(public int $gdprRequestId) {}

    public function handle(): void
    {
        $req = MarketplaceCustomerGdprRequest::find($this->gdprRequestId);
        if (! $req) return;

        $req->update([
            'status'       => MarketplaceCustomerGdprRequest::STATUS_PROCESSING,
            'processed_at' => null,
        ]);

        try {
            $customer = MarketplaceCustomer::find($req->marketplace_customer_id);
            if (! $customer) {
                throw new \RuntimeException('Customer not found.');
            }

            $data = $this->gatherData($customer);
            $zipPath = $this->writeZip($req, $data);
            $sizeBytes = Storage::disk('local')->size($zipPath);

            $token = Str::random(60);

            $req->update([
                'status'           => MarketplaceCustomerGdprRequest::STATUS_COMPLETED,
                'export_file_path' => $zipPath,
                'export_token'     => $token,
                'file_size_bytes'  => $sizeBytes,
                'processed_at'     => now(),
                'expires_at'       => now()->addDays(14),
            ]);

            $this->sendReadyEmail($customer, $req->fresh());
        } catch (\Throwable $e) {
            Log::error('GDPR export failed', [
                'request_id' => $req->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            $req->update([
                'status'        => MarketplaceCustomerGdprRequest::STATUS_FAILED,
                'error_message' => Str::limit($e->getMessage(), 500),
                'processed_at'  => now(),
            ]);
        }
    }

    protected function gatherData(MarketplaceCustomer $customer): array
    {
        $data = [
            'meta' => [
                'export_generated_at' => now()->toIso8601String(),
                'customer_id'         => $customer->id,
                'marketplace_client_id' => $customer->marketplace_client_id,
                'note' => 'Acesta este un export al datelor tale personale stocate de bilete.online. Comenzile, biletele și facturile sunt incluse parțial; pentru documentele fiscale complete contactează suportul.',
            ],

            'profile' => array_merge(
                $customer->only([
                    'id', 'email', 'first_name', 'last_name', 'phone', 'birth_date',
                    'gender', 'address', 'city', 'state', 'postal_code', 'country',
                    'locale', 'status', 'accepts_marketing', 'marketing_consent_at',
                    'created_at', 'last_login_at', 'total_orders', 'total_spent',
                ]),
                [
                    'email_verified_at' => optional($customer->email_verified_at)->toIso8601String(),
                    'settings'          => $customer->settings,
                ]
            ),
        ];

        // --- Orders ---
        try {
            $orders = \App\Models\Order::where('marketplace_customer_id', $customer->id)
                ->with(['orderItems'])
                ->orderByDesc('id')
                ->limit(500)
                ->get();
            $data['orders'] = $orders->map(function ($o) {
                return [
                    'id'             => $o->id,
                    'order_number'   => $o->order_number ?? null,
                    'status'         => $o->status,
                    'total_amount'   => (float) ($o->total_amount ?? 0),
                    'currency'       => $o->currency,
                    'customer_email' => $o->customer_email,
                    'customer_name'  => $o->customer_name,
                    'created_at'     => optional($o->created_at)->toIso8601String(),
                    'items'          => $o->orderItems->map(fn ($i) => $i->only([
                        'id', 'ticket_type_id', 'event_id', 'quantity', 'price',
                    ]))->all(),
                ];
            })->all();
        } catch (\Throwable $e) {
            $data['orders'] = ['_error' => 'Could not fetch orders: ' . $e->getMessage()];
        }

        // --- Tickets ---
        try {
            $tickets = \App\Models\Ticket::where('marketplace_customer_id', $customer->id)
                ->orderByDesc('id')
                ->limit(2000)
                ->get();
            $data['tickets'] = $tickets->map(fn ($t) => $t->only([
                'id', 'order_id', 'ticket_type_id', 'event_id', 'price', 'status',
                'qr_code', 'used_at', 'created_at',
            ]))->all();
        } catch (\Throwable $e) {
            $data['tickets'] = ['_error' => 'Could not fetch tickets: ' . $e->getMessage()];
        }

        // --- Reviews ---
        try {
            $reviews = \DB::table('marketplace_customer_reviews')
                ->where('marketplace_customer_id', $customer->id)
                ->limit(500)->get();
            $data['reviews'] = $reviews->all();
        } catch (\Throwable $e) {
            $data['reviews'] = ['_error' => $e->getMessage()];
        }

        // --- Support tickets ---
        try {
            $support = \DB::table('support_tickets')
                ->where('opener_type', MarketplaceCustomer::class)
                ->where('opener_id', $customer->id)
                ->limit(500)->get();
            $data['support_tickets'] = $support->all();
        } catch (\Throwable $e) {
            $data['support_tickets'] = ['_error' => $e->getMessage()];
        }

        // --- Beneficiaries ---
        try {
            $bens = \App\Models\MarketplaceCustomerBeneficiary::where('marketplace_customer_id', $customer->id)
                ->withTrashed()
                ->get();
            $data['beneficiaries'] = $bens->map(fn ($b) => $b->only([
                'id', 'name', 'relation', 'birth_date', 'email', 'phone',
                'interests', 'notes', 'is_active', 'created_at', 'deleted_at',
            ]))->all();
        } catch (\Throwable $e) {
            $data['beneficiaries'] = ['_error' => $e->getMessage()];
        }

        // --- Points ---
        try {
            $points = \DB::table('customer_points')
                ->where('marketplace_customer_id', $customer->id)
                ->first();
            if ($points) $data['points'] = (array) $points;
        } catch (\Throwable $e) {
            $data['points'] = ['_error' => $e->getMessage()];
        }

        return $data;
    }

    protected function writeZip(MarketplaceCustomerGdprRequest $req, array $data): string
    {
        $dir = 'gdpr-exports';
        Storage::disk('local')->makeDirectory($dir);

        $stamp = now()->format('Ymd-His');
        $zipFile = $dir . '/export-' . $req->marketplace_customer_id . '-' . $req->id . '-' . $stamp . '.zip';
        $absPath = Storage::disk('local')->path($zipFile);

        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is not available.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($absPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create export ZIP at ' . $absPath);
        }

        $zip->addFromString('README.txt',
            "bilete.online — export GDPR\n" .
            "Generat: " . now()->toIso8601String() . "\n\n" .
            "Acest arhivă conține datele personale stocate de bilete.online despre contul tău.\n" .
            "Fiecare fișier .json conține un set logic de date.\n" .
            "Link-ul de descărcare e valabil 14 zile.\n"
        );

        foreach ($data as $section => $payload) {
            $zip->addFromString(
                $section . '.json',
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        $zip->close();
        return $zipFile;
    }

    protected function sendReadyEmail(MarketplaceCustomer $customer, MarketplaceCustomerGdprRequest $req): void
    {
        $downloadUrl = url('/marketplace-client/customer/gdpr/download/' . $req->export_token);
        $expiresAt   = $req->expires_at?->format('d.m.Y H:i');

        $html = '<!doctype html><html><body style="font-family:Hanken Grotesk,Arial,sans-serif;color:#1B1714;background:#F4EFE3;padding:24px">'
            . '<h1 style="font-family:Fraunces,Georgia,serif;color:#1B1714;font-size:32px;margin:0 0 16px">Exportul datelor tale e gata</h1>'
            . '<p>Salut' . ($customer->first_name ? ' ' . htmlspecialchars($customer->first_name) : '') . ',</p>'
            . '<p>Ai cerut un export al datelor tale personale stocate de bilete.online. Arhiva ZIP e pregătită.</p>'
            . '<p style="margin:24px 0"><a href="' . $downloadUrl . '" style="background:#E84527;color:#F4EFE3;padding:14px 28px;border-radius:9999px;font-weight:700;text-decoration:none;display:inline-block">Descarcă arhiva</a></p>'
            . '<p style="font-size:14px;color:#5A4F41">Link-ul e valabil până la <strong>' . $expiresAt . '</strong>. După această dată va trebui să reiei cererea din /cont/setari → Privacy.</p>'
            . '<hr style="border:none;border-top:1px solid rgba(27,23,20,.12);margin:24px 0">'
            . '<p style="font-size:13px;color:#5A4F41">Dacă nu ai cerut acest export, contactează imediat suportul bilete.online.</p>'
            . '</body></html>';

        try {
            // Use the marketplace email helper if the AuthController exposes it,
            // otherwise fall back to Laravel's default mailer.
            $authClass = \App\Http\Controllers\Api\MarketplaceClient\Customer\AuthController::class;
            $client    = $customer->marketplaceClient;

            if ($client && method_exists($authClass, 'sendMarketplaceEmail')) {
                $ref = new \ReflectionMethod($authClass, 'sendMarketplaceEmail');
                $ref->setAccessible(true);
                $ref->invoke(new $authClass(), $client, $customer->email, $customer->first_name ?: '', 'Datele tale sunt gata de descărcat', $html, [
                    'marketplace_customer_id' => $customer->id,
                    'template_slug'           => 'gdpr_export_ready',
                ]);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('sendMarketplaceEmail failed for GDPR export — falling back', [
                'request_id' => $req->id,
                'error'      => $e->getMessage(),
            ]);
        }

        Mail::raw("Arhiva ta GDPR e gata. Descarcă: $downloadUrl (valabil până la $expiresAt)", function ($m) use ($customer) {
            $m->to($customer->email)->subject('Datele tale sunt gata de descărcat — bilete.online');
        });
    }
}
