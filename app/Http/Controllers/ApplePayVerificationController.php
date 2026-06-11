<?php

namespace App\Http\Controllers;

use App\Services\ApplePayDomainService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ApplePayVerificationController extends Controller
{
    /**
     * Serve the Apple Pay domain verification file
     *
     * This endpoint needs to be accessible at:
     * https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association
     */
    public function __invoke(): Response
    {
        // Cache the verification file content for 24 hours
        $content = Cache::remember('apple_pay_verification_file', 86400, function () {
            return ApplePayDomainService::getVerificationFileContent();
        });

        // If content is empty, return a default response
        if (empty($content)) {
            // Fallback - fetch directly from Stripe
            $content = @file_get_contents('https://stripe.com/files/apple-pay/apple-developer-merchantid-domain-association');
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain');
    }
}
