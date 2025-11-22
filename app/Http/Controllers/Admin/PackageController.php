<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePackageJob;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantPackage;
use App\Services\PackageGeneratorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        private PackageGeneratorService $packageGenerator
    ) {}

    /**
     * Download the deployment package for a domain
     */
    public function download(Tenant $tenant, Domain $domain): StreamedResponse
    {
        $this->authorize('update', $tenant);

        $package = $domain->packages()
            ->where('status', TenantPackage::STATUS_READY)
            ->latest()
            ->firstOrFail();

        if (!$package->file_path || !Storage::exists($package->file_path)) {
            abort(404, 'Package file not found');
        }

        $package->incrementDownloadCount();

        $filename = sprintf(
            'tixello-%s-v%s.js',
            str_replace('.', '-', $domain->domain),
            $package->version
        );

        return Storage::download($package->file_path, $filename, [
            'Content-Type' => 'application/javascript',
        ]);
    }

    /**
     * Generate a new package for a domain
     */
    public function generate(Request $request, Tenant $tenant, Domain $domain)
    {
        $this->authorize('update', $tenant);

        // Dispatch the job to generate the package
        GeneratePackageJob::dispatch($domain);

        return redirect()->back()->with('success', 'Package generation started. Please refresh in a moment.');
    }

    /**
     * Regenerate the package for a domain
     */
    public function regenerate(Request $request, Tenant $tenant, Domain $domain)
    {
        $this->authorize('update', $tenant);

        // Invalidate existing packages
        $domain->packages()
            ->where('status', TenantPackage::STATUS_READY)
            ->update(['status' => TenantPackage::STATUS_INVALIDATED]);

        // Dispatch the job to generate a new package
        GeneratePackageJob::dispatch($domain);

        return redirect()->back()->with('success', 'Package regeneration started. Please refresh in a moment.');
    }

    /**
     * Show installation instructions for a domain
     */
    public function instructions(Tenant $tenant, Domain $domain)
    {
        $this->authorize('update', $tenant);

        $package = $domain->packages()
            ->where('status', TenantPackage::STATUS_READY)
            ->latest()
            ->first();

        return view('admin.packages.instructions', [
            'tenant' => $tenant,
            'domain' => $domain,
            'package' => $package,
        ]);
    }

    /**
     * Download full deployment ZIP with all files
     */
    public function downloadZip(Tenant $tenant, Domain $domain): StreamedResponse
    {
        $this->authorize('update', $tenant);

        $package = $domain->packages()
            ->where('status', TenantPackage::STATUS_READY)
            ->latest()
            ->firstOrFail();

        // Create a temporary ZIP file
        $zipPath = storage_path('app/temp/' . $package->package_hash . '.zip');

        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Add the main loader script
        if ($package->file_path && Storage::exists($package->file_path)) {
            $zip->addFromString('tixello-loader.min.js', Storage::get($package->file_path));
        }

        // Add index.html template
        $indexHtml = $this->generateIndexHtml($tenant, $domain, $package);
        $zip->addFromString('index.html', $indexHtml);

        // Add .htaccess for Apache
        $htaccess = $this->generateHtaccess();
        $zip->addFromString('.htaccess', $htaccess);

        // Add README
        $readme = $this->generateReadme($tenant, $domain, $package);
        $zip->addFromString('README.md', $readme);

        $zip->close();

        $package->incrementDownloadCount();

        $filename = sprintf(
            'tixello-%s-v%s.zip',
            str_replace('.', '-', $domain->domain),
            $package->version
        );

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function generateIndexHtml(Tenant $tenant, Domain $domain, TenantPackage $package): string
    {
        $installCode = $package->getInstallationCode();

        return <<<HTML
<!DOCTYPE html>
<html lang="{$tenant->locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$tenant->public_name} - Events & Tickets</title>
    <meta name="description" content="Buy tickets for events by {$tenant->public_name}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }
        #tixello-app { min-height: 100vh; }
        .tixello-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f9fafb;
        }
        .tixello-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    {$installCode}
    <noscript>
        <div style="padding: 20px; text-align: center;">
            <h1>JavaScript Required</h1>
            <p>Please enable JavaScript to use this website.</p>
        </div>
    </noscript>
</body>
</html>
HTML;
    }

    private function generateHtaccess(): string
    {
        return <<<HTACCESS
# Tixello Event Platform - Apache Configuration

# Enable rewrite engine
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# SPA routing - redirect all requests to index.html
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /index.html [L]

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>
HTACCESS;
    }

    private function generateReadme(Tenant $tenant, Domain $domain, TenantPackage $package): string
    {
        $coreUrl = config('app.url');

        return <<<README
# Tixello Event Platform - Deployment Package

## Package Information
- **Tenant:** {$tenant->public_name}
- **Domain:** {$domain->domain}
- **Version:** {$package->version}
- **Generated:** {$package->generated_at?->format('Y-m-d H:i:s')}

## Installation Instructions

### Option 1: Simple Upload
1. Upload all files to your web server's root directory
2. Ensure your web server is configured to serve `index.html` for all routes
3. Access your domain to verify the installation

### Option 2: Custom Integration
If you want to integrate with an existing website, add this code to your HTML:

```html
{$package->getInstallationCode()}
```

## Server Requirements
- HTTPS enabled (required for security)
- Ability to serve static files
- Rewrite rules for SPA routing (Apache .htaccess included)

## For Nginx Users
Add this to your server block:

```nginx
location / {
    try_files \$uri \$uri/ /index.html;
}
```

## Support
For technical support, contact your Tixello account manager or visit:
{$coreUrl}/support

## Security Note
This package is domain-locked to {$domain->domain} and will not function on other domains.
The code is obfuscated and protected. Do not attempt to modify the JavaScript files.

---
Powered by Tixello Event Platform
README;
    }
}
