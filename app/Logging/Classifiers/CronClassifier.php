<?php

namespace App\Logging\Classifiers;

class CronClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['scheduled command', 'scheduled task'])
            && $this->messageContains($record, ['failed', 'error'])) {
            return new ClassificationResult('cron', 'command_failed');
        }
        if ($this->contextHas($record, 'command') && $this->messageContains($record, ['failed', 'error'])) {
            return new ClassificationResult('cron', 'command_failed');
        }
        if ($this->messageContains($record, 'overran its expected runtime')) {
            return new ClassificationResult('cron', 'schedule_overrun');
        }
        return null;
    }
}
