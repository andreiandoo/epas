<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Jobs\ExportMarketplaceCustomerDataJob;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceCustomerGdprRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GDPR data subject endpoints — /cont/setari → Privacy tab.
 *
 *  POST /customer/gdpr/export        → enqueue a new export request
 *  GET  /customer/gdpr/export/status → poll the latest pending/processing/ready row
 *  GET  /customer/gdpr/download/{token} → download the ZIP (signed-token gated)
 */
class DataExportController extends BaseController
{
    public function request(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        // Reuse a pending one if it's still warm — avoids spam dispatches
        $existing = MarketplaceCustomerGdprRequest::where('marketplace_customer_id', $customer->id)
            ->where('request_type', MarketplaceCustomerGdprRequest::TYPE_EXPORT)
            ->whereIn('status', [
                MarketplaceCustomerGdprRequest::STATUS_PENDING,
                MarketplaceCustomerGdprRequest::STATUS_PROCESSING,
            ])
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $this->success([
                'request' => $this->present($existing),
                'reused'  => true,
            ], 'Avem deja o cerere de export în curs.');
        }

        $req = MarketplaceCustomerGdprRequest::create([
            'marketplace_client_id'   => $customer->marketplace_client_id,
            'marketplace_customer_id' => $customer->id,
            'request_type'            => MarketplaceCustomerGdprRequest::TYPE_EXPORT,
            'status'                  => MarketplaceCustomerGdprRequest::STATUS_PENDING,
            'requested_at'            => now(),
        ]);

        ExportMarketplaceCustomerDataJob::dispatch($req->id);

        return $this->success([
            'request' => $this->present($req->fresh()),
        ], 'Exportul a fost programat. Vei primi un email când e gata.');
    }

    public function status(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $rows = MarketplaceCustomerGdprRequest::where('marketplace_customer_id', $customer->id)
            ->where('request_type', MarketplaceCustomerGdprRequest::TYPE_EXPORT)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $this->success([
            'latest'  => $rows->first() ? $this->present($rows->first()) : null,
            'history' => $rows->map(fn ($r) => $this->present($r))->all(),
        ]);
    }

    public function download(Request $request, string $token)
    {
        // Public route — no `auth:sanctum` — gated by export_token + expires_at.
        $row = MarketplaceCustomerGdprRequest::where('export_token', $token)
            ->where('status', MarketplaceCustomerGdprRequest::STATUS_COMPLETED)
            ->first();

        if (! $row) {
            abort(404, 'Link-ul de descărcare este invalid sau a expirat.');
        }
        if ($row->expires_at && $row->expires_at->isPast()) {
            abort(410, 'Link-ul de descărcare a expirat. Reia cererea din contul tău.');
        }
        if (! $row->export_file_path || ! Storage::disk('local')->exists($row->export_file_path)) {
            abort(404, 'Fișierul de export nu mai există.');
        }

        $row->update(['downloaded_at' => now()]);

        $filename = 'bilete-online-export-' . $row->marketplace_customer_id . '-' . $row->id . '.zip';
        return Storage::disk('local')->download($row->export_file_path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    protected function present(MarketplaceCustomerGdprRequest $r): array
    {
        $downloadUrl = null;
        if ($r->isReady() && $r->export_token) {
            $downloadUrl = url('/marketplace-client/customer/gdpr/download/' . $r->export_token);
        }
        return [
            'id'              => $r->id,
            'status'          => $r->status,
            'requested_at'    => $r->requested_at?->toIso8601String(),
            'processed_at'    => $r->processed_at?->toIso8601String(),
            'expires_at'      => $r->expires_at?->toIso8601String(),
            'downloaded_at'   => $r->downloaded_at?->toIso8601String(),
            'file_size_bytes' => $r->file_size_bytes,
            'download_url'    => $downloadUrl,
            'error_message'   => $r->status === MarketplaceCustomerGdprRequest::STATUS_FAILED
                ? $r->error_message
                : null,
        ];
    }
}
