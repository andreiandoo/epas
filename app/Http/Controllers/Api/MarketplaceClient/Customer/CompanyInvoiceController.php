<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\OrderCompanyInvoice;
use App\Services\Invoicing\CompanyInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Factură pe persoană juridică, partea clientului: căutarea firmei după CUI
 * (checkout, fără cont), cererea după cumpărare și descărcarea facturilor.
 */
class CompanyInvoiceController extends BaseController
{
    public function __construct(protected CompanyInvoiceService $service)
    {
    }

    /** POST /customer/company-invoice/lookup {cui, event_ids[]} */
    public function lookup(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $data = $request->validate([
            'cui' => 'required|string|max:20',
            'event_ids' => 'nullable|array|max:20',
            'event_ids.*' => 'integer',
        ]);

        if (!$this->service->isEnabled($client)) {
            return $this->error('Facturarea pe persoană juridică nu este activă.', 403);
        }

        try {
            $company = $this->service->lookupCompany($data['cui']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'company' => [
                'cui' => $company['cui'],
                'fiscal_code' => $this->service->fiscalCode($company),
                'name' => $company['name'],
                'reg_com' => $company['reg_com'],
                'address' => $company['address'],
                'city' => $company['city'],
                'county' => $company['county'],
                'vat_payer' => $company['vat_payer'],
            ],
            // organizatori persoane fizice: nu emit factură pentru bilete
            'organizers_without_invoice' => $this->service->organizersWithoutInvoice($client->id, $data['event_ids'] ?? []),
        ]);
    }

    /** POST /customer/orders/{order}/company-invoice {cui} */
    public function request(Request $request, string $order): JsonResponse
    {
        $customer = $this->customer($request);
        $model = $this->findOrder($customer, $order);
        if (!$model) {
            return $this->error('Comanda nu a fost găsită.', 404);
        }

        $data = $request->validate(['cui' => 'required|string|max:20']);

        try {
            $this->service->requestForPaidOrder($model, $data['cui'], 'account', 'customer:' . $customer->id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->service->customerView($model->fresh()), 'Cererea de factură a fost înregistrată.');
    }

    /** GET /customer/orders/{order}/company-invoices/{invoice}/download */
    public function download(Request $request, string $order, int $invoice)
    {
        $customer = $this->customer($request);
        $model = $this->findOrder($customer, $order);
        $row = $model ? OrderCompanyInvoice::where('order_id', $model->id)->find($invoice) : null;
        if (!$row || !$row->hasDocument()) {
            return $this->error('Factura nu este disponibilă.', 404);
        }

        $doc = $this->service->documentFor($row);
        if (!$doc) {
            return $this->error('Factura nu este încă disponibilă. Încearcă din nou în câteva minute.', 409);
        }
        [$content, $name] = $doc;

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . addslashes($name) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    protected function customer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();
        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }

    protected function findOrder(MarketplaceCustomer $customer, string $order): ?Order
    {
        return Order::where('marketplace_customer_id', $customer->id)
            ->where(is_numeric($order) ? 'id' : 'order_number', $order)
            ->first();
    }
}
