<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AccountingController extends Controller
{
    public function __construct(protected AccountingService $accountingService)
    {}

    /**
     * POST /api/acc/connect
     */
    public function connect(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'provider' => 'required|in:smartbill,fgo,exact,xero,quickbooks,mock',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->accountingService->connect(
                $request->tenant,
                $request->provider,
                $request->credentials,
                $request->settings ?? []
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/acc/map
     */
    public function map(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'entity' => 'required|in:product,tax,account,series,customer_policy',
            'local_ref' => 'required|string',
            'remote_ref' => 'required|string',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $this->accountingService->createMapping(
                $request->tenant,
                $request->entity,
                $request->local_ref,
                $request->remote_ref,
                $request->meta ?? []
            );

            return response()->json(['success' => true, 'message' => 'Mapping created']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/acc/issue
     */
    public function issue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'order_ref' => 'required|string',
            'customer' => 'required|array',
            'lines' => 'required|array',
            'totals' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->accountingService->issueInvoice(
                $request->tenant,
                $request->order_ref,
                $request->all()
            );

            return response()->json($result, 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/acc/credit
     */
    public function credit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'invoice_external_ref' => 'required|string',
            'refund_payload' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->accountingService->createCreditNote(
                $request->tenant,
                $request->invoice_external_ref,
                $request->refund_payload
            );

            return response()->json(['success' => true, 'credit_note' => $result]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
