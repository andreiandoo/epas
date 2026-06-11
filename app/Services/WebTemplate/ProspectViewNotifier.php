<?php

namespace App\Services\WebTemplate;

use App\Models\WebTemplateCustomization;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications when prospects view demo links at milestone thresholds.
 */
class ProspectViewNotifier
{
    private const MILESTONES = [3, 10, 25, 50, 100];

    /**
     * Check if a notification should be sent after a view is recorded.
     */
    public function checkAndNotify(WebTemplateCustomization $customization): void
    {
        $viewCount = $customization->viewed_count;

        if (!in_array($viewCount, self::MILESTONES)) {
            return;
        }

        $label = $customization->label ?? $customization->unique_token;
        $templateName = $customization->template->name ?? 'Template';

        // Send Filament database notification to all admin users
        $admins = \App\Models\User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            Log::info("WebTemplate prospect view milestone: {$label} reached {$viewCount} views");
            return;
        }

        $message = match ($viewCount) {
            3 => "a fost vizualizat de 3 ori — prospectul arată interes!",
            10 => "a atins 10 vizualizări — interes serios!",
            25 => "a atins 25 vizualizări — prospectul revine frecvent!",
            50 => "a depășit 50 vizualizări — engagement foarte mare!",
            100 => "a atins 100 vizualizări — follow-up recomandat!",
            default => "a atins {$viewCount} vizualizări.",
        };

        Notification::make()
            ->title("Demo Template Vizualizat")
            ->body("„{$label}" ({$templateName}) {$message}")
            ->icon(match (true) {
                $viewCount >= 50 => 'heroicon-o-fire',
                $viewCount >= 10 => 'heroicon-o-arrow-trending-up',
                default => 'heroicon-o-eye',
            })
            ->iconColor(match (true) {
                $viewCount >= 50 => 'danger',
                $viewCount >= 10 => 'warning',
                default => 'info',
            })
            ->sendToDatabase($admins);
    }
}
