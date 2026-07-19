<?php

namespace App\Services\Installments;

use App\Models\MarketplaceClient;
use App\Services\PaymentProcessors\MockTokenizableProcessor;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Services\PaymentProcessors\PaymentProcessorInterface;
use App\Services\PaymentProcessors\SupportsTokenizedPayments;

/**
 * Resolves a payment processor instance for a marketplace client, and in
 * particular one that supports tokenized (off-session / MIT) charges — the
 * requirement for installment / BNPL auto-debit.
 */
class ProcessorResolver
{
    /**
     * @return array{processor: PaymentProcessorInterface, type: string}|null
     */
    public function forMarketplaceClient(MarketplaceClient $client): ?array
    {
        // Test mode: bypass the real gateway entirely (staging E2E).
        if (config('installments.fake_processor', false)) {
            return ['processor' => new MockTokenizableProcessor(), 'type' => 'mock'];
        }

        $method = $client->getDefaultPaymentMethod();
        if (! $method) {
            return null;
        }

        $type = $this->processorType($method->slug);
        $settings = $client->getPaymentMethodSettings($method->slug);
        if (! $settings) {
            return null;
        }

        try {
            $processor = PaymentProcessorFactory::makeFromArray($type, $settings);
        } catch (\Throwable $e) {
            return null;
        }

        return ['processor' => $processor, 'type' => $type];
    }

    /**
     * Resolve a tokenization-capable processor, or null if the marketplace's
     * processor cannot do off-session charges.
     */
    public function tokenizableForMarketplaceClient(MarketplaceClient $client): ?SupportsTokenizedPayments
    {
        $resolved = $this->forMarketplaceClient($client);
        if (! $resolved) {
            return null;
        }

        $processor = $resolved['processor'];
        if ($processor instanceof SupportsTokenizedPayments && $processor->supportsTokenization()) {
            return $processor;
        }

        return null;
    }

    protected function processorType(string $slug): string
    {
        return match ($slug) {
            'netopia', 'netopia-payments', 'payment-netopia' => 'netopia',
            'stripe', 'stripe-payments', 'payment-stripe' => 'stripe',
            'euplatesc', 'payment-euplatesc' => 'euplatesc',
            'payu', 'payment-payu' => 'payu',
            default => $slug,
        };
    }
}
