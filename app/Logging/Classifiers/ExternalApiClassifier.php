<?php

namespace App\Logging\Classifiers;

class ExternalApiClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['ANAF', 'anaf'])) {
            return new ClassificationResult('external_api', 'anaf_error');
        }
        if ($this->messageContains($record, ['Bunny', 'bunny.net', 'BunnyCDN'])) {
            return new ClassificationResult('external_api', 'bunny_error');
        }
        if ($this->messageContains($record, ['Cloudflare', 'cloudflare API', 'cf-ray'])) {
            return new ClassificationResult('external_api', 'cloudflare_error');
        }
        if ($this->messageContains($record, ['Brevo', 'Sendinblue'])) {
            // Email classifier already grabs Brevo when message references mail; this catches non-mail Brevo API errors.
            return new ClassificationResult('external_api', 'brevo_api');
        }
        if ($this->messageContains($record, ['Meta API', 'Facebook Graph', 'Instagram API'])) {
            return new ClassificationResult('external_api', 'meta_api');
        }
        if ($this->messageContains($record, ['Google API', 'Google Maps', 'Geocoding'])) {
            return new ClassificationResult('external_api', 'google_api');
        }
        if ($this->messageContains($record, ['Mapbox', 'mapbox API'])) {
            return new ClassificationResult('external_api', 'mapbox_api');
        }
        if ($this->messageContains($record, ['cURL error', 'cURL connect', 'connection reset', 'Could not resolve host'])) {
            return new ClassificationResult('external_api', 'curl_error');
        }
        if ($this->exceptionIsA($record, [
            'GuzzleHttp\Exception\ConnectException',
            'GuzzleHttp\Exception\ServerException',
            'GuzzleHttp\Exception\ClientException',
            'Illuminate\Http\Client\ConnectionException',
            'Illuminate\Http\Client\RequestException',
        ])) {
            return new ClassificationResult('external_api', 'http_client');
        }
        return null;
    }
}
