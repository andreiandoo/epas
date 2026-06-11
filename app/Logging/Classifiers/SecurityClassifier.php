<?php

namespace App\Logging\Classifiers;

class SecurityClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->exceptionIsA($record, [
            'Illuminate\Session\TokenMismatchException',
        ])) {
            return new ClassificationResult('security', 'csrf_mismatch');
        }
        if ($this->messageContains($record, ['Too Many Attempts', 'rate limit', 'throttle exceeded'])
            || $this->exceptionIsA($record, ['Illuminate\Http\Exceptions\ThrottleRequestsException'])) {
            return new ClassificationResult('security', 'throttle_exceeded');
        }
        if ($this->exceptionIsA($record, [
            'Illuminate\Auth\Access\AuthorizationException',
            'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException',
        ])) {
            return new ClassificationResult('security', 'policy_denied');
        }
        if ($this->messageContains($record, ['suspicious', 'XSS attempt', 'SQL injection', 'malformed payload'])) {
            return new ClassificationResult('security', 'suspicious_payload');
        }
        if ($this->channelIs($record, 'security') && !$this->messageContains($record, 'login')) {
            return new ClassificationResult('security', 'security_audit');
        }
        return null;
    }
}
