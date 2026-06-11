<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SECURITY FIX: Centralized security audit logging service
 *
 * Logs security-relevant events for compliance and incident response.
 */
class SecurityAuditLogger
{
    /**
     * Security event types
     */
    public const EVENT_AUTH_SUCCESS = 'auth.success';
    public const EVENT_AUTH_FAILURE = 'auth.failure';
    public const EVENT_AUTH_LOGOUT = 'auth.logout';
    public const EVENT_PASSWORD_CHANGE = 'auth.password_change';
    public const EVENT_PASSWORD_RESET = 'auth.password_reset';

    public const EVENT_ACCESS_DENIED = 'access.denied';
    public const EVENT_TENANT_ACCESS = 'access.tenant';
    public const EVENT_ADMIN_ACTION = 'access.admin_action';

    public const EVENT_PAYMENT_ATTEMPT = 'payment.attempt';
    public const EVENT_PAYMENT_SUCCESS = 'payment.success';
    public const EVENT_PAYMENT_FAILURE = 'payment.failure';
    public const EVENT_REFUND = 'payment.refund';

    public const EVENT_DATA_EXPORT = 'data.export';
    public const EVENT_DATA_DELETE = 'data.delete';
    public const EVENT_BULK_OPERATION = 'data.bulk_operation';

    public const EVENT_RATE_LIMIT_HIT = 'security.rate_limit';
    public const EVENT_SUSPICIOUS_ACTIVITY = 'security.suspicious';
    public const EVENT_CSRF_FAILURE = 'security.csrf_failure';
    public const EVENT_INPUT_VALIDATION = 'security.input_validation';

    /**
     * Log a security event
     */
    public static function log(
        string $eventType,
        array $context = [],
        ?Request $request = null,
        string $level = 'info'
    ): void {
        $request = $request ?? request();

        $logData = [
            'event_type' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'user_id' => $request?->user()?->id,
            'tenant_id' => $request?->attributes->get('tenant')?->id,
            'request_id' => $request?->header('X-Request-ID') ?? uniqid('req_'),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'context' => $context,
        ];

        // Log to dedicated security channel
        Log::channel('security')->{$level}("Security Event: {$eventType}", $logData);
    }

    /**
     * Log authentication success
     */
    public static function authSuccess(int $userId, string $method = 'password', ?Request $request = null): void
    {
        self::log(self::EVENT_AUTH_SUCCESS, [
            'user_id' => $userId,
            'auth_method' => $method,
        ], $request);
    }

    /**
     * Log authentication failure
     */
    public static function authFailure(string $identifier, string $reason, ?Request $request = null): void
    {
        self::log(self::EVENT_AUTH_FAILURE, [
            'identifier' => $identifier,
            'reason' => $reason,
        ], $request, 'warning');
    }

    /**
     * Log access denied
     */
    public static function accessDenied(string $resource, string $action, ?Request $request = null): void
    {
        self::log(self::EVENT_ACCESS_DENIED, [
            'resource' => $resource,
            'action' => $action,
        ], $request, 'warning');
    }

    /**
     * Log payment attempt
     */
    public static function paymentAttempt(
        string $orderId,
        float $amount,
        string $currency,
        string $processor,
        ?Request $request = null
    ): void {
        self::log(self::EVENT_PAYMENT_ATTEMPT, [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'processor' => $processor,
        ], $request);
    }

    /**
     * Log payment success
     */
    public static function paymentSuccess(
        string $orderId,
        string $transactionId,
        float $amount,
        string $currency,
        ?Request $request = null
    ): void {
        self::log(self::EVENT_PAYMENT_SUCCESS, [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
        ], $request);
    }

    /**
     * Log payment failure
     */
    public static function paymentFailure(
        string $orderId,
        string $reason,
        ?string $errorCode = null,
        ?Request $request = null
    ): void {
        self::log(self::EVENT_PAYMENT_FAILURE, [
            'order_id' => $orderId,
            'reason' => $reason,
            'error_code' => $errorCode,
        ], $request, 'warning');
    }

    /**
     * Log rate limit hit
     */
    public static function rateLimitHit(string $endpoint, int $limit, ?Request $request = null): void
    {
        self::log(self::EVENT_RATE_LIMIT_HIT, [
            'endpoint' => $endpoint,
            'limit' => $limit,
        ], $request, 'warning');
    }

    /**
     * Log suspicious activity
     */
    public static function suspiciousActivity(string $description, array $details = [], ?Request $request = null): void
    {
        self::log(self::EVENT_SUSPICIOUS_ACTIVITY, [
            'description' => $description,
            'details' => $details,
        ], $request, 'warning');
    }

    /**
     * Log admin action
     */
    public static function adminAction(
        string $action,
        string $targetType,
        $targetId,
        array $changes = [],
        ?Request $request = null
    ): void {
        self::log(self::EVENT_ADMIN_ACTION, [
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'changes' => $changes,
        ], $request);
    }

    /**
     * Log data export
     */
    public static function dataExport(string $exportType, int $recordCount, ?Request $request = null): void
    {
        self::log(self::EVENT_DATA_EXPORT, [
            'export_type' => $exportType,
            'record_count' => $recordCount,
        ], $request);
    }
}
