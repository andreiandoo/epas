<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;

class SmsWebhookController extends Controller
{
    /**
     * Handle SendSMS.ro delivery report callback.
     * Called as GET with status parameter (%d in report_url).
     * Status codes: 1=delivered, 2=undelivered, 4=queued, 8=sent, 16=failed
     */
    public function deliveryReport(Request $request, int $smsLogId)
    {
        $log = SmsLog::find($smsLogId);
        if (!$log) {
            return response()->json(['ok' => false], 404);
        }

        $statusCode = (int) $request->query('status', 0);

        $statusMap = [
            1 => 'delivered',
            2 => 'undelivered',
            4 => 'sent',    // queued at network
            8 => 'sent',    // sent to network
            16 => 'failed', // failed at network
        ];

        $newStatus = $statusMap[$statusCode] ?? null;
        if (!$newStatus) {
            return response()->json(['ok' => true]);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'delivered') {
            $updateData['delivered_at'] = now();
        }

        if ($newStatus === 'failed' || $newStatus === 'undelivered') {
            $updateData['error_message'] = "Delivery report status code: {$statusCode}";
        }

        $log->update($updateData);

        return response()->json(['ok' => true]);
    }
}
