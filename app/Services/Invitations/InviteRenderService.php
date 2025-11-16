<?php

namespace App\Services\Invitations;

use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\InviteLog;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * Invite Render Service
 *
 * Renders PDF/PNG for invitations using Ticket Template microservice
 */
class InviteRenderService
{
    public function __construct(
        protected TicketPreviewGenerator $previewGenerator
    ) {}

    /**
     * Render a single invitation
     *
     * @param Invite $invite
     * @return array {pdf, png, signed_download}
     */
    public function renderInvite(Invite $invite): array
    {
        $batch = $invite->batch;
        $template = $batch->template;

        if (!$template) {
            throw new \Exception("No template assigned to batch");
        }

        // Prepare data for template
        $data = $this->prepareTemplateData($invite);

        // Add watermark to template
        $templateData = $template->template_data;
        $watermark = $batch->getWatermark();

        // TODO: Add watermark layer to template
        // This would modify the template_data to include a watermark layer

        // Generate preview (SVG/PNG)
        $preview = $this->previewGenerator->generatePreview($templateData, $data, 2);

        // Save files
        $basePath = "invites/batch-{$batch->id}";
        $filename = "invite-{$invite->id}";

        $pdfPath = "{$basePath}/{$filename}.pdf";
        $pngPath = $preview['path'] ?? null;

        // TODO: Convert SVG to PDF
        // For now, we'll use the SVG as the "PDF"
        if ($pngPath) {
            Storage::disk('public')->copy($pngPath, $pdfPath);
        }

        // Generate signed download URL (valid for 30 days)
        $signedUrl = $this->generateSignedUrl($invite, $pdfPath);

        $urls = [
            'pdf' => $pdfPath,
            'png' => $pngPath,
            'signed_download' => $signedUrl,
            'signed_expires_at' => now()->addDays(30)->toIso8601String(),
        ];

        $invite->setUrls($urls);
        $invite->markAsRendered();

        InviteLog::logRender($invite, $urls);

        return $urls;
    }

    /**
     * Render entire batch
     *
     * @param InviteBatch $batch
     * @return int Number of invites rendered
     */
    public function renderBatch(InviteBatch $batch): int
    {
        $batch->updateStatus('rendering');

        $rendered = 0;

        $batch->invites()->where('status', 'created')->each(function (Invite $invite) use (&$rendered) {
            try {
                $this->renderInvite($invite);
                $rendered++;
            } catch (\Exception $e) {
                InviteLog::logError($invite, 'RENDER_ERROR', $e->getMessage());
            }
        });

        $batch->updateStatus('ready');

        return $rendered;
    }

    /**
     * Prepare template data for rendering
     *
     * @param Invite $invite
     * @return array
     */
    protected function prepareTemplateData(Invite $invite): array
    {
        $recipient = $invite->recipient ?? [];

        return [
            'event' => [
                'name' => 'Event ' . $invite->batch->event_ref,
            ],
            'ticket' => [
                'type' => 'INVITATION',
                'price' => 'FREE',
                'section' => $invite->seat_ref ?? 'General',
                'number' => $invite->invite_code,
            ],
            'buyer' => [
                'name' => $recipient['name'] ?? 'Guest',
                'email' => $recipient['email'] ?? '',
            ],
            'codes' => [
                'barcode' => $invite->invite_code,
                'qrcode' => $invite->qr_data,
            ],
            'organizer' => [
                'name' => $invite->tenant->name ?? 'Event Organizer',
            ],
        ];
    }

    /**
     * Generate signed download URL
     *
     * @param Invite $invite
     * @param string $path
     * @return string
     */
    protected function generateSignedUrl(Invite $invite, string $path): string
    {
        // Generate signed URL valid for 30 days
        return \URL::temporarySignedRoute(
            'api.inv.download',
            now()->addDays(30),
            ['id' => $invite->id, 'code' => $invite->invite_code]
        );
    }
}
