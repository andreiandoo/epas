<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainVerificationService
{
    public function initiateVerification(Domain $domain, string $method = DomainVerification::METHOD_DNS_TXT): DomainVerification
    {
        // Invalidate any existing pending verifications
        $domain->verifications()
            ->pending()
            ->update(['status' => DomainVerification::STATUS_EXPIRED]);

        return DomainVerification::create([
            'domain_id' => $domain->id,
            'tenant_id' => $domain->tenant_id,
            'verification_method' => $method,
            'status' => DomainVerification::STATUS_PENDING,
        ]);
    }

    public function verify(DomainVerification $verification): bool
    {
        if ($verification->isExpired()) {
            $verification->update(['status' => DomainVerification::STATUS_EXPIRED]);
            return false;
        }

        $verification->incrementAttempts();

        try {
            $result = match ($verification->verification_method) {
                DomainVerification::METHOD_DNS_TXT => $this->verifyDnsTxt($verification),
                DomainVerification::METHOD_META_TAG => $this->verifyMetaTag($verification),
                DomainVerification::METHOD_FILE_UPLOAD => $this->verifyFileUpload($verification),
                default => false,
            };

            if ($result) {
                $verification->markAsVerified();

                // Activate the domain
                $verification->domain->update([
                    'is_active' => true,
                    'activated_at' => now(),
                ]);

                Log::info('Domain verified successfully', [
                    'domain_id' => $verification->domain_id,
                    'domain' => $verification->domain->domain,
                    'method' => $verification->verification_method,
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $verification->markAsFailed($e->getMessage());

            Log::error('Domain verification failed', [
                'domain_id' => $verification->domain_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function verifyDnsTxt(DomainVerification $verification): bool
    {
        $domain = $verification->domain->domain;
        $recordName = $verification->getDnsRecordName() . '.' . $domain;
        $expectedValue = $verification->getDnsRecordValue();

        // Try TXT record lookup
        $records = @dns_get_record($recordName, DNS_TXT);

        if (!$records) {
            // Also try without prefix as fallback
            $records = @dns_get_record($domain, DNS_TXT);
        }

        if (!$records) {
            $verification->update([
                'last_error' => 'No TXT records found for ' . $recordName,
                'last_attempt_at' => now(),
            ]);
            return false;
        }

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';

            // Check for exact match or tixello-verify format
            if ($txt === $expectedValue || $txt === "tixello-verify={$expectedValue}") {
                $verification->update([
                    'verification_data' => ['dns_record' => $record],
                ]);
                return true;
            }
        }

        $verification->update([
            'last_error' => 'Token not found in TXT records',
            'last_attempt_at' => now(),
        ]);

        return false;
    }

    protected function verifyMetaTag(DomainVerification $verification): bool
    {
        $domain = $verification->domain->domain;
        $expectedToken = $verification->verification_token;

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get("https://{$domain}");

            if (!$response->successful()) {
                // Try HTTP if HTTPS fails
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->get("http://{$domain}");
            }

            if (!$response->successful()) {
                $verification->update([
                    'last_error' => 'Could not fetch domain homepage: ' . $response->status(),
                    'last_attempt_at' => now(),
                ]);
                return false;
            }

            $html = $response->body();

            // Look for meta tag with our verification token
            $pattern = '/<meta\s+name=["\']tixello-verification["\']\s+content=["\']([^"\']+)["\']/i';

            if (preg_match($pattern, $html, $matches)) {
                if ($matches[1] === $expectedToken) {
                    $verification->update([
                        'verification_data' => ['found_in' => 'meta_tag'],
                    ]);
                    return true;
                }
            }

            // Also check reverse attribute order
            $pattern2 = '/<meta\s+content=["\']([^"\']+)["\']\s+name=["\']tixello-verification["\']/i';

            if (preg_match($pattern2, $html, $matches)) {
                if ($matches[1] === $expectedToken) {
                    $verification->update([
                        'verification_data' => ['found_in' => 'meta_tag'],
                    ]);
                    return true;
                }
            }

            $verification->update([
                'last_error' => 'Meta tag not found or token mismatch',
                'last_attempt_at' => now(),
            ]);

            return false;
        } catch (\Exception $e) {
            $verification->update([
                'last_error' => 'HTTP error: ' . $e->getMessage(),
                'last_attempt_at' => now(),
            ]);
            return false;
        }
    }

    protected function verifyFileUpload(DomainVerification $verification): bool
    {
        $domain = $verification->domain->domain;
        $filePath = $verification->getFileUploadPath();
        $expectedContent = $verification->getFileUploadContent();

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get("https://{$domain}{$filePath}");

            if (!$response->successful()) {
                // Try HTTP if HTTPS fails
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->get("http://{$domain}{$filePath}");
            }

            if (!$response->successful()) {
                $verification->update([
                    'last_error' => 'Verification file not found: ' . $response->status(),
                    'last_attempt_at' => now(),
                ]);
                return false;
            }

            $content = trim($response->body());

            if ($content === $expectedContent) {
                $verification->update([
                    'verification_data' => ['found_in' => 'file_upload'],
                ]);
                return true;
            }

            $verification->update([
                'last_error' => 'File content does not match verification token',
                'last_attempt_at' => now(),
            ]);

            return false;
        } catch (\Exception $e) {
            $verification->update([
                'last_error' => 'HTTP error: ' . $e->getMessage(),
                'last_attempt_at' => now(),
            ]);
            return false;
        }
    }

    public function getVerificationInstructions(DomainVerification $verification): array
    {
        $domain = $verification->domain->domain;

        return match ($verification->verification_method) {
            DomainVerification::METHOD_DNS_TXT => [
                'method' => 'DNS TXT Record',
                'instructions' => [
                    "1. Log in to your domain registrar or DNS provider",
                    "2. Add a new TXT record with the following details:",
                    "   - Name/Host: {$verification->getDnsRecordName()}",
                    "   - Value: {$verification->getDnsRecordValue()}",
                    "   - TTL: 300 (or lowest available)",
                    "3. Wait 5-10 minutes for DNS propagation",
                    "4. Click 'Verify' to complete verification",
                ],
                'record_name' => $verification->getDnsRecordName() . '.' . $domain,
                'record_value' => $verification->getDnsRecordValue(),
            ],
            DomainVerification::METHOD_META_TAG => [
                'method' => 'Meta Tag',
                'instructions' => [
                    "1. Add the following meta tag to your website's <head> section:",
                    $verification->getMetaTagHtml(),
                    "2. Make sure the tag is on your homepage (https://{$domain})",
                    "3. Click 'Verify' to complete verification",
                ],
                'meta_tag' => $verification->getMetaTagHtml(),
            ],
            DomainVerification::METHOD_FILE_UPLOAD => [
                'method' => 'File Upload',
                'instructions' => [
                    "1. Create a file at: https://{$domain}{$verification->getFileUploadPath()}",
                    "2. The file should contain only this text:",
                    $verification->getFileUploadContent(),
                    "3. Make sure the file is publicly accessible",
                    "4. Click 'Verify' to complete verification",
                ],
                'file_path' => $verification->getFileUploadPath(),
                'file_content' => $verification->getFileUploadContent(),
            ],
            default => [],
        };
    }
}
