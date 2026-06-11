<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderDisputeEvidence\OrderDisputeEvidenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OrderDisputeEvidenceController extends Controller
{
    public function __construct(protected OrderDisputeEvidenceService $service)
    {
    }

    public function download(Order $order): Response
    {
        $data = $this->service->collect($order);

        $html = view('admin.order-dispute-evidence', $data)->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait');

        $filename = sprintf(
            'dispute-evidence-order-%s-%s.pdf',
            $data['order']['order_number'] ?? $order->id,
            now()->format('Ymd-His')
        );

        return $pdf->download($filename);
    }
}
