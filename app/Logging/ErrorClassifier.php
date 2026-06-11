<?php

namespace App\Logging;

use App\Logging\Classifiers\ClassificationResult;

/**
 * Runs the configured classifier pipeline. The first classifier to return
 * a non-null ClassificationResult wins. FallbackClassifier always wins if
 * no preceding rule matched.
 */
class ErrorClassifier
{
    /** @var object[] */
    protected array $instances = [];

    public function __construct(?array $classifierClasses = null)
    {
        $classes = $classifierClasses ?? config('system_errors.classifiers', []);
        foreach ($classes as $class) {
            $this->instances[] = app($class);
        }
    }

    public function classify(array $record): ClassificationResult
    {
        foreach ($this->instances as $classifier) {
            $result = $classifier->classify($record);
            if ($result instanceof ClassificationResult) {
                return $result;
            }
        }
        return new ClassificationResult('unknown', null);
    }
}
