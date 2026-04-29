<?php

namespace App\Logging\Classifiers;

class PaymentClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['NETOPIA', 'netopia', 'payment decline', 'card declined'])) {
            return new ClassificationResult('payment', 'netopia_decline');
        }
        if ($this->messageContains($record, ['Stripe', 'stripe webhook', 'stripe error'])) {
            return new ClassificationResult('payment', 'stripe_failed');
        }
        if ($this->messageContains($record, ['refund failed', 'refund error', 'PaymentRefundService'])
            || $this->fileContains($record, 'PaymentRefundService')) {
            return new ClassificationResult('payment', 'refund_failed');
        }
        if ($this->contextHas($record, 'payment_status')
            && in_array($record['context']['payment_status'] ?? null, ['failed', 'declined'], true)) {
            return new ClassificationResult('payment', 'payment_status_failed');
        }
        if ($this->messageContains($record, ['gateway timeout', 'gateway error', 'PSP timeout'])) {
            return new ClassificationResult('payment', 'gateway_timeout');
        }
        if ($this->channelIs($record, 'marketplace')
            && $this->messageContains($record, ['checkout', 'payment', 'order'])) {
            // Falls back to payment if it's clearly checkout-related
            if ($this->messageContains($record, ['fail', 'error', 'declined', 'rejected'])) {
                return new ClassificationResult('payment', 'checkout_error');
            }
        }
        return null;
    }
}
