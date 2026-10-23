<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\OrderCompanyInvoice;
use App\Services\Invoicing\CompanyInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facturile pe firmă cerute organizatorului pentru biletele lui: le vede,
 * încarcă PDF-ul emis de el (pleacă imediat la client) și îl poate înlocui.
 */
class CompanyInvoiceController extends BaseController
{
    public function __construct(protected CompanyInvoiceService $service)
    {
    }

    /** GET /organizer/company-invoices?status= */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);

        $rows = OrderCompanyInvoice::with('order:id,order_number,paid_at,customer_name,customer_email,event_id')
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('issuer', OrderCompanyInvoice::ISSUER_ORGANIZER)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return $this->success([
            'pending_count' => $rows->where('status', OrderCompanyInvoice::STATUS_REQUESTED)->count(),
            'items' => $rows->map(fn (OrderCompanyInvoice $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'order_number' => $r->order?->order_number,
                'paid_at' => $r->order?->paid_at?->toIso8601String(),
                'requested_at' => $r->created_at?->toIso8601String(),
                'company' => [
                    'name' => $r->company['name'] ?? '',
                    'cui' => $this->service->fiscalCode($r->company ?? []),
                    'reg_com' => $r->company['reg_com'] ?? '',
                    'address' => $r->company['address'] ?? '',
                ],
                'buyer' => ['name' => $r->order?->customer_name, 'email' => $r->order?->customer_email],
                'lines' => $r->lines ?? [],
                'amount' => (float) $r->amount,
                'currency' => $r->currency,
                'file_name' => $r->file_name,
                'uploaded_at' => $r->uploaded_at?->toIso8601String(),
                'sent_to_customer_at' => $r->sent_to_customer_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** POST /organizer/company-invoices/{id}/upload (file: PDF) */
    public function upload(Request $request, int $id): JsonResponse
    {
        $organizer = $this->organizer($request);
        $row = $this->find($organizer, $id);
        if (!$row) {
            return $this->error('Cererea nu a fost găsită.', 404);
        }

        $request->validate(['file' => 'required|file|mimes:pdf|max:10240']);

        try {
            $row = $this->service->storeOrganizerUpload($row, $request->file('file'), 'organizer:' . $organizer->id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            // fișierul e salvat; trimiterea către client se poate relua din admin
            return $this->error('Factura a fost încărcată, dar emailul către client nu a plecat. Încearcă din nou sau contactează-ne.', 502);
        }

        return $this->success(['status' => $row->status, 'sent_to_customer_at' => $row->sent_to_customer_at?->toIso8601String()], 'Factura a fost trimisă clientului.');
    }

    /** GET /organizer/company-invoices/{id}/file */
    public function file(Request $request, int $id)
    {
        $organizer = $this->organizer($request);
        $row = $this->find($organizer, $id);
        $doc = $row ? $this->service->documentFor($row) : null;
        if (!$doc) {
            return $this->error('Fișierul nu există.', 404);
        }
        [$content, $name] = $doc;

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    protected function find(MarketplaceOrganizer $organizer, int $id): ?OrderCompanyInvoice
    {
        return OrderCompanyInvoice::where('marketplace_organizer_id', $organizer->id)
            ->where('issuer', OrderCompanyInvoice::ISSUER_ORGANIZER)
            ->find($id);
    }

    protected function organizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();
        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}
