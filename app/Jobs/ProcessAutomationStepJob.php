<?php

namespace App\Jobs;

use App\Models\AutomationEnrollment;
use App\Models\AutomationStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAutomationStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AutomationEnrollment $enrollment) {}

    public function handle(): void
    {
        $step = $this->enrollment->currentStep;

        if (!$step) {
            $this->completeEnrollment();
            return;
        }

        match ($step->type) {
            'email' => $this->processEmailStep($step),
            'wait' => $this->processWaitStep($step),
            'condition' => $this->processConditionStep($step),
            'action' => $this->processActionStep($step),
        };
    }

    protected function processEmailStep(AutomationStep $step): void
    {
        // Send email based on step config
        // Then advance to next step
        $this->advanceToNextStep($step);
    }

    protected function processWaitStep(AutomationStep $step): void
    {
        $delay = $step->config['delay'] ?? '1 day';

        // Re-queue with delay
        self::dispatch($this->enrollment)->delay(now()->add($delay));
    }

    protected function processConditionStep(AutomationStep $step): void
    {
        // Evaluate condition and branch
        $this->advanceToNextStep($step);
    }

    protected function processActionStep(AutomationStep $step): void
    {
        // Execute action (tag, update field, etc.)
        $this->advanceToNextStep($step);
    }

    protected function advanceToNextStep(AutomationStep $currentStep): void
    {
        $nextStep = AutomationStep::where('workflow_id', $currentStep->workflow_id)
            ->where('order', '>', $currentStep->order)
            ->orderBy('order')
            ->first();

        if ($nextStep) {
            $this->enrollment->update(['current_step_id' => $nextStep->id]);
            self::dispatch($this->enrollment);
        } else {
            $this->completeEnrollment();
        }
    }

    protected function completeEnrollment(): void
    {
        $this->enrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->enrollment->workflow->increment('completed_count');
    }
}
