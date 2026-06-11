<?php

namespace App\Services\Insurance\Adapters;

use Illuminate\Support\Str;

/**
 * Mock Insurance Provider Adapter
 *
 * For testing and development purposes
 */
class MockInsurerAdapter implements InsurerAdapterInterface
{
    public function quote(array $params): array
    {
        // Mock: return the configured premium
        $premium = $params['premium'] ?? 0;

        return [
            'premium' => $premium,
            'currency' => 'EUR',
            'details' => [
                'provider' => 'Mock Insurer',
                'coverage_amount' => $params['ticket_price'] ?? 0,
                'valid_until' => now()->addDays(30)->toIso8601String(),
            ],
        ];
    }

    public function issue(array $params): array
    {
        // Mock: generate fake policy number
        $policyNumber = 'MOCK-' . strtoupper(Str::random(12));

        // Simulate 95% success rate
        if (rand(1, 100) <= 95) {
            return [
                'policy_number' => $policyNumber,
                'policy_doc_url' => "https://mock-insurer.test/policies/{$policyNumber}.pdf",
                'details' => [
                    'issued_at' => now()->toIso8601String(),
                    'expires_at' => $params['event']['date'] ?? now()->addMonths(6)->toIso8601String(),
                    'coverage' => [
                        'cancellation' => true,
                        'medical' => false,
                        'liability' => false,
                    ],
                ],
            ];
        }

        // Simulate failure
        throw new \Exception('Mock provider: Random failure (5% chance)');
    }

    public function void(string $policyNumber): array
    {
        // Mock: always succeed
        return [
            'success' => true,
            'message' => "Policy {$policyNumber} voided successfully",
        ];
    }

    public function refund(string $policyNumber, ?float $amount = null): array
    {
        // Mock: always succeed with full refund if no amount specified
        return [
            'success' => true,
            'refund_amount' => $amount ?? 0,
            'message' => "Refund processed for policy {$policyNumber}",
        ];
    }

    public function sync(string $policyNumber): array
    {
        // Mock: return issued status
        return [
            'status' => 'issued',
            'doc_url' => "https://mock-insurer.test/policies/{$policyNumber}.pdf",
            'details' => [
                'last_updated' => now()->toIso8601String(),
            ],
        ];
    }
}
