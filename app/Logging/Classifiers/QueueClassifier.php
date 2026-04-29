<?php

namespace App\Logging\Classifiers;

class QueueClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['MaxAttemptsExceeded', 'has been attempted too many times'])) {
            return new ClassificationResult('queue', 'max_attempts_exceeded');
        }
        if ($this->messageContains($record, ['payload unserializ', 'unserialize']) && $this->contextHas($record, 'job_id')) {
            return new ClassificationResult('queue', 'payload_unserialize_failed');
        }
        if ($this->messageContains($record, 'job timed out') || $this->messageContains($record, 'A job timeout')) {
            return new ClassificationResult('queue', 'job_timeout');
        }
        if ($this->contextHas($record, 'job_id') || $this->contextHas($record, 'job_uuid')) {
            return new ClassificationResult('queue', 'job_failed');
        }
        if ($this->exceptionIsA($record, [
            'Illuminate\Queue\MaxAttemptsExceededException',
            'Illuminate\Queue\TimeoutExceededException',
        ])) {
            return new ClassificationResult('queue', 'job_failed');
        }
        return null;
    }
}
