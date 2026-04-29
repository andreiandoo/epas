<?php

namespace App\Logging\Classifiers;

class AuthClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, 'organizer login failed')) {
            return new ClassificationResult('auth', 'login_failed');
        }
        if ($this->messageContains($record, ['admin login failed', 'authentication failed'])) {
            return new ClassificationResult('auth', 'admin_login_failed');
        }
        if ($this->messageContains($record, ['password reset', 'reset password'])
            && $this->messageContains($record, ['fail', 'invalid token', 'expired'])) {
            return new ClassificationResult('auth', 'password_reset_failed');
        }
        if ($this->messageContains($record, ['token refresh', 'invalid token', 'token expired'])) {
            return new ClassificationResult('auth', 'token_invalid');
        }
        if ($this->exceptionIsA($record, [
            'Illuminate\Auth\AuthenticationException',
            'Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException',
        ])) {
            return new ClassificationResult('auth', 'unauthenticated');
        }
        if ($this->channelIs($record, 'security') && $this->messageContains($record, 'login')) {
            return new ClassificationResult('auth', 'login_failed');
        }
        return null;
    }
}
