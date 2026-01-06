<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\SmsPaymentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to send SMS payment links asynchronously
 *
 * This job handles the async delivery of payment links via SMS,
 * allowing for scheduled sends and queue-based processing.
 */
class SendSmsPaymentLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Tenant ID
     */
    protected int $tenantId;

    /**
     * Payment data
     */
    protected array $paymentData;

    /**
     * Create a new job instance.
     *
     * @param int $tenantId
     * @param array $paymentData
     */
    public function __construct(int $tenantId, array $paymentData)
    {
        $this->tenantId = $tenantId;
        $this->paymentData = $paymentData;
        $this->onQueue('sms-payments');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $tenant = Tenant::findOrFail($this->tenantId);

            $config = TenantPaymentConfig::where('tenant_id', $this->tenantId)
                ->where('processor', 'sms')
                ->where('is_active', true)
                ->first();

            if (!$config) {
                Log::warning('SMS payment config not found', [
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            $processor = new SmsPaymentProcessor($config);

            if (!$processor->isConfigured()) {
                Log::warning('SMS payment processor not configured', [
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            // Remove async flag to prevent infinite loop
            $this->paymentData['send_async'] = false;

            $result = $processor->createPayment($this->paymentData);

            Log::info('SMS payment link sent successfully', [
                'tenant_id' => $this->tenantId,
                'payment_id' => $result['payment_id'] ?? null,
                'phone' => $this->maskPhoneNumber($this->paymentData['phone_number'] ?? ''),
            ]);
        } catch (\Exception $e) {
            Log::error('SMS payment link failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                // Final failure - log for manual review
                Log::critical('SMS payment link permanently failed', [
                    'tenant_id' => $this->tenantId,
                    'phone' => $this->maskPhoneNumber($this->paymentData['phone_number'] ?? ''),
                    'amount' => $this->paymentData['amount'] ?? 0,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendSmsPaymentLink job failed permanently', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'payment_data' => array_merge($this->paymentData, [
                'phone_number' => $this->maskPhoneNumber($this->paymentData['phone_number'] ?? ''),
            ]),
        ]);
    }

    /**
     * Mask phone number for logging
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function maskPhoneNumber(string $phoneNumber): string
    {
        $length = strlen($phoneNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phoneNumber, 0, 3) . str_repeat('*', $length - 6) . substr($phoneNumber, -3);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'sms-payment',
            'tenant:' . $this->tenantId,
        ];
    }
}
