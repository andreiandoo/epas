<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    /**
     * Stream a database backup file for download.
     *
     * Deliberately a plain controller route (not a Livewire action): backups
     * are multi-hundred-MB dumps, and Livewire base64-encodes downloaded file
     * contents into its JSON response — which would blow memory. response()
     * ->download() returns a BinaryFileResponse that streams via sendfile with
     * a constant memory footprint.
     *
     * Locked to super-admins and hardened against path traversal: the route
     * pattern already forbids slashes, and we additionally basename() the input
     * and assert the resolved realpath lives inside the backups directory.
     */
    public function download(string $filename): BinaryFileResponse
    {
        $user = auth()->user();
        abort_unless($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin(), 403);

        $dir = config('backup.storage.local_path', storage_path('backups'));
        $realDir = realpath($dir);
        abort_unless($realDir !== false, 404);

        $filename = basename($filename);
        $path = realpath($realDir . DIRECTORY_SEPARATOR . $filename);

        abort_unless(
            $path !== false
                && str_starts_with($path, $realDir . DIRECTORY_SEPARATOR)
                && is_file($path),
            404
        );

        return response()->download($path, $filename);
    }
}
