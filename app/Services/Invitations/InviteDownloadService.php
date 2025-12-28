<?php

namespace App\Services\Invitations;

use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\InviteLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use ZipArchive;

/**
 * Invite Download Service
 *
 * Handles PDF downloads with signed URLs and ZIP creation
 */
class InviteDownloadService
{
    /**
     * Download a single invitation PDF
     *
     * @param Invite $invite
     * @param string|null $ip
     * @param string|null $userAgent
     * @return Response
     */
    public function downloadInvite(Invite $invite, ?string $ip = null, ?string $userAgent = null): Response
    {
        if ($invite->isVoid()) {
            abort(403, 'This invitation has been voided');
        }

        $pdfPath = $invite->getPdfUrl();

        if (!$pdfPath || !Storage::disk('public')->exists($pdfPath)) {
            abort(404, 'Invitation file not found');
        }

        // Mark as downloaded
        $invite->markAsDownloaded();

        // Log download
        InviteLog::logDownload($invite, $pdfPath, $ip, $userAgent);

        // Return file download response
        return response()->download(
            Storage::disk('public')->path($pdfPath),
            "invitation-{$invite->invite_code}.pdf"
        );
    }

    /**
     * Create ZIP file for batch download
     *
     * @param InviteBatch $batch
     * @return string Path to ZIP file
     */
    public function createBatchZip(InviteBatch $batch): string
    {
        $zipPath = storage_path("app/public/invites/batch-{$batch->id}.zip");

        // Ensure directory exists
        $dir = dirname($zipPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Create ZIP
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Failed to create ZIP file");
        }

        $invites = $batch->invites()->where('status', '!=', 'void')->get();

        foreach ($invites as $invite) {
            $pdfPath = $invite->getPdfUrl();

            if ($pdfPath && Storage::disk('public')->exists($pdfPath)) {
                $filename = "invite-{$invite->invite_code}.pdf";
                $fullPath = Storage::disk('public')->path($pdfPath);
                $zip->addFile($fullPath, $filename);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Download batch as ZIP
     *
     * @param InviteBatch $batch
     * @return Response
     */
    public function downloadBatchZip(InviteBatch $batch): Response
    {
        $zipPath = $this->createBatchZip($batch);

        return response()->download($zipPath, "invitations-{$batch->name}.zip")->deleteFileAfterSend();
    }

    /**
     * Get download statistics for a batch
     *
     * @param InviteBatch $batch
     * @return array
     */
    public function getDownloadStats(InviteBatch $batch): array
    {
        return [
            'total_invites' => $batch->qty_generated,
            'downloaded' => $batch->qty_downloaded,
            'not_downloaded' => $batch->qty_generated - $batch->qty_downloaded,
            'download_rate' => $batch->getDownloadedPercentage(),
        ];
    }
}
