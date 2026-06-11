<?php

namespace App\Logging\Classifiers;

class EmailClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['email bounced', 'bounce', 'undeliverable'])) {
            return new ClassificationResult('email', 'bounce');
        }
        if ($this->messageContains($record, ['SMTP', 'smtp connection', 'mail transport'])) {
            return new ClassificationResult('email', 'smtp_error');
        }
        if ($this->messageContains($record, ['Brevo', 'sendinblue'])) {
            return new ClassificationResult('email', 'brevo_error');
        }
        if ($this->messageContains($record, ['transport creation failed', 'failed to send email', 'email send failed'])) {
            return new ClassificationResult('email', 'transport_creation_failed');
        }
        if ($this->exceptionIsA($record, [
            'Symfony\Component\Mailer\Exception\TransportException',
            'Symfony\Component\Mailer\Exception\HttpTransportException',
        ])) {
            return new ClassificationResult('email', 'transport_exception');
        }
        if ($this->channelIs($record, 'marketplace') && $this->messageContains($record, ['email', 'mail'])) {
            return new ClassificationResult('email', 'marketplace_mail_error');
        }
        return null;
    }
}
