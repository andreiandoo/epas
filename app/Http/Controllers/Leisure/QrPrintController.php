<?php

namespace App\Http\Controllers\Leisure;

use App\Http\Controllers\Controller;
use App\Models\Leisure\PhysicalResource;
use Illuminate\Http\Request;

class QrPrintController extends Controller
{
    public function show(Request $request)
    {
        $tenantId = auth()->user()?->tenant?->id;
        abort_unless($tenantId, 403);

        $ids = (array) $request->input('ids', []);
        abort_if(empty($ids), 422, 'No resources selected.');

        $resources = PhysicalResource::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->orderBy('resource_type')
            ->orderBy('name')
            ->get();

        return view('leisure.qr-print', [
            'resources' => $resources,
            'tenantName' => auth()->user()?->tenant?->public_name ?? auth()->user()?->tenant?->name,
        ]);
    }
}
