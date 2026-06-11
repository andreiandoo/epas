<?php

namespace App\Logging\Classifiers;

/**
 * Base class for classifiers that decide which category an incoming log
 * record belongs to. Each concrete classifier implements classify() and
 * returns a ClassificationResult — or null to defer to the next classifier
 * in the pipeline.
 */
abstract class AbstractClassifier
{
    /**
     * @param array $record Monolog-shaped record with keys:
     *   message (string), channel (?string), level (int),
     *   exception_class (?string), exception_file (?string),
     *   context (array)
     */
    abstract public function classify(array $record): ?ClassificationResult;

    protected function messageContains(array $record, string|array $needles): bool
    {
        $message = (string) ($record['message'] ?? '');
        $needles = is_array($needles) ? $needles : [$needles];
        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function messageMatches(array $record, string $pattern): bool
    {
        $message = (string) ($record['message'] ?? '');
        return (bool) preg_match($pattern, $message);
    }

    protected function channelIs(array $record, string|array $names): bool
    {
        $channel = (string) ($record['channel'] ?? '');
        $names = is_array($names) ? $names : [$names];
        return in_array($channel, $names, true);
    }

    protected function exceptionIsA(array $record, string|array $classes): bool
    {
        $class = $record['exception_class'] ?? null;
        if (!$class) {
            return false;
        }
        $classes = is_array($classes) ? $classes : [$classes];
        foreach ($classes as $candidate) {
            if ($class === $candidate || is_subclass_of($class, $candidate)) {
                return true;
            }
            if (str_ends_with($class, '\\' . ltrim($candidate, '\\'))) {
                return true;
            }
        }
        return false;
    }

    protected function fileContains(array $record, string|array $needles): bool
    {
        $file = (string) ($record['exception_file'] ?? '');
        if ($file === '') {
            return false;
        }
        $needles = is_array($needles) ? $needles : [$needles];
        foreach ($needles as $needle) {
            if (stripos($file, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function contextHas(array $record, string $key): bool
    {
        return is_array($record['context'] ?? null) && array_key_exists($key, $record['context']);
    }
}
