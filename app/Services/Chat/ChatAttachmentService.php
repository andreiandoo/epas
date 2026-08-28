<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatConversation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Secure IMAGE-ONLY attachment handling for live chat.
 *
 * Security model (no antivirus on the box, so we accept images only and never
 * trust the bytes):
 *  - hard size cap (config chat.attachments.max_size_kb);
 *  - mime allow-list restricted to jpeg/png/webp;
 *  - pixel-dimension cap to stop decompression bombs;
 *  - the file is RE-ENCODED through GD (imagecreatefromstring → imagejpeg/png),
 *    so only raw pixel data survives — any embedded script/EXIF/polyglot payload
 *    is dropped;
 *  - stored on the PRIVATE 'local' disk (storage/app), never in the webroot;
 *  - served only via access-checked endpoints (never a public URL), scoped by
 *    marketplace_client_id + conversation + a per-file random token.
 */
class ChatAttachmentService
{
    private const DISK = 'local';
    private const MAX_PIXELS = 25_000_000; // ~25 MP guard against decompression bombs

    /**
     * Validate + re-encode + store a base64 image. Returns an attachment
     * descriptor to embed in a message's attachments array, or throws
     * \RuntimeException with a safe message on rejection.
     *
     * @return array{token:string,name:string,mime:string,size:int,w:int,h:int}
     */
    public function storeBase64(ChatConversation $conversation, string $base64, ?string $originalName = null): array
    {
        if (!(bool) config('chat.attachments.enabled', true)) {
            throw new \RuntimeException('Atașamentele sunt dezactivate.');
        }

        // Accept both a full data URI and raw base64.
        if (str_contains($base64, 'base64,')) {
            $base64 = substr($base64, strpos($base64, 'base64,') + 7);
        }
        $bytes = base64_decode($base64, true);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Fișier invalid.');
        }

        $maxBytes = (int) config('chat.attachments.max_size_kb', 3072) * 1024;
        if (strlen($bytes) > $maxBytes) {
            throw new \RuntimeException('Imaginea depășește dimensiunea maximă.');
        }

        // Sniff real type from the bytes — never trust a client-declared mime.
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new \RuntimeException('Fișierul nu este o imagine validă.');
        }
        [$w, $h, $type] = [$info[0], $info[1], $info[2]];
        if ($w < 1 || $h < 1 || ($w * $h) > self::MAX_PIXELS) {
            throw new \RuntimeException('Imagine prea mare.');
        }

        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \RuntimeException('Tip de imagine nepermis (doar JPG, PNG, WEBP).');
        }

        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Procesarea imaginilor nu este disponibilă.');
        }

        // Re-encode: this is what makes it safe — only decoded pixels are written
        // back out, dropping any non-image payload smuggled into the file.
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            throw new \RuntimeException('Imaginea nu a putut fi procesată.');
        }

        // PNG keeps transparency; everything else becomes JPEG (smaller).
        $isPng = $type === IMAGETYPE_PNG;
        ob_start();
        if ($isPng) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagepng($img, null, 6);
            $mime = 'image/png';
            $ext = 'png';
        } else {
            $flat = imagecreatetruecolor($w, $h);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
            imagejpeg($flat, null, 85);
            imagedestroy($flat);
            $mime = 'image/jpeg';
            $ext = 'jpg';
        }
        $clean = ob_get_clean();
        imagedestroy($img);

        if (!$clean) {
            throw new \RuntimeException('Imaginea nu a putut fi salvată.');
        }

        $token = Str::random(32);
        $path = $this->pathFor($conversation, $token, $ext);
        Storage::disk(self::DISK)->put($path, $clean);

        return [
            'token' => $token,
            'name' => $this->safeName($originalName, $ext),
            'mime' => $mime,
            'size' => strlen($clean),
            'w' => $w,
            'h' => $h,
        ];
    }

    /**
     * Resolve the stored bytes for an attachment token that belongs to this
     * conversation. Returns [bytes, mime] or null if not found.
     *
     * @return array{0:string,1:string}|null
     */
    public function read(ChatConversation $conversation, string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $ext => $mime) {
            $path = $this->pathFor($conversation, $token, $ext);
            if (Storage::disk(self::DISK)->exists($path)) {
                return [Storage::disk(self::DISK)->get($path), $mime];
            }
        }
        return null;
    }

    private function pathFor(ChatConversation $conversation, string $token, string $ext): string
    {
        return 'chat-attachments/' . (int) $conversation->marketplace_client_id
            . '/' . (int) $conversation->id . '/' . $token . '.' . $ext;
    }

    private function safeName(?string $name, string $ext): string
    {
        $name = $name ? preg_replace('/[^\w.\- ]+/u', '', $name) : '';
        $name = trim((string) $name);
        if ($name === '') {
            return 'imagine.' . $ext;
        }
        return Str::limit($name, 60, '');
    }
}
