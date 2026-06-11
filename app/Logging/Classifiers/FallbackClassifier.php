<?php

namespace App\Logging\Classifiers;

/**
 * Last classifier in the chain. Always matches; assigns one of:
 *   - 'app' if there's an exception class (a real PHP/Laravel error)
 *   - 'marketplace' if the channel is the marketplace one
 *   - 'unknown' otherwise
 */
class FallbackClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if (!empty($record['exception_class'])) {
            $sub = match (true) {
                $this->exceptionIsA($record, 'TypeError') => 'type_error',
                $this->exceptionIsA($record, 'ArgumentCountError') => 'argument_error',
                $this->exceptionIsA($record, 'Error') => 'php_error',
                $this->exceptionIsA($record, 'RuntimeException') => 'runtime_error',
                $this->exceptionIsA($record, 'InvalidArgumentException') => 'invalid_argument',
                default => 'app_exception',
            };
            return new ClassificationResult('app', $sub);
        }

        if ($this->channelIs($record, 'marketplace')) {
            return new ClassificationResult('marketplace', 'unclassified_marketplace');
        }

        return new ClassificationResult('unknown', null);
    }
}
