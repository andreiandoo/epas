<?php

namespace App\Filament\Pages;

use Filament\Actions\Exports\Models\Export;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $title = 'Data Exports';
    protected static ?string $navigationLabel = 'Exports';
    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.export-status';

    public function getExports(): Collection
    {
        return Export::where('user_id', auth()->id())
            ->latest()
            ->limit(20)
            ->get();
    }

    public function hasInProgressExports(): bool
    {
        return Export::where('user_id', auth()->id())
            ->whereNull('completed_at')
            ->exists();
    }

    public function downloadExport(int $exportId): StreamedResponse
    {
        $export = Export::where('id', $exportId)
            ->where('user_id', auth()->id())
            ->whereNotNull('completed_at')
            ->firstOrFail();

        $disk = $export->getFileDisk();
        $directory = $export->getFileDirectory();
        $files = collect($disk->files($directory))->sort()->values();

        $fileName = ($export->file_name ?? 'export') . '.csv';

        return response()->streamDownload(function () use ($disk, $files) {
            foreach ($files as $file) {
                echo $disk->get($file);
            }
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function deleteExport(int $exportId): void
    {
        $export = Export::where('id', $exportId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $export->deleteFileDirectory();
        $export->delete();

        \Filament\Notifications\Notification::make()
            ->title('Export deleted')
            ->success()
            ->send();
    }
}
