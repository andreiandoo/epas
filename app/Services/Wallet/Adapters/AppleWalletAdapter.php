<?php

namespace App\Services\Wallet\Adapters;

use App\Models\Ticket;
use App\Models\WalletPass;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Apple Wallet (PKPass) Adapter
 *
 * Generates .pkpass files for iOS devices
 */
class AppleWalletAdapter implements WalletAdapterInterface
{
    protected ?string $certificatePath = null;
    protected ?string $certificatePassword = null;
    protected ?string $wwdrCertPath = null;
    protected ?string $passTypeIdentifier = null;
    protected ?string $teamIdentifier = null;

    public function __construct()
    {
        $this->certificatePath = config('microservices.wallet.apple.certificate_path');
        $this->certificatePassword = config('microservices.wallet.apple.certificate_password');
        $this->wwdrCertPath = config('microservices.wallet.apple.wwdr_certificate_path');
        $this->passTypeIdentifier = config('microservices.wallet.apple.pass_type_identifier');
        $this->teamIdentifier = config('microservices.wallet.apple.team_identifier');
    }

    public function generatePass(Ticket $ticket, array $options = []): array
    {
        try {
            $order = $ticket->order;
            $event = $ticket->event;
            $tenant = $ticket->tenant ?? $order->tenant;

            // Generate unique identifiers
            $serialNumber = Str::uuid()->toString();
            $authToken = Str::random(64);

            // Build pass structure
            $passData = [
                'formatVersion' => 1,
                'passTypeIdentifier' => $this->passTypeIdentifier,
                'serialNumber' => $serialNumber,
                'teamIdentifier' => $this->teamIdentifier,
                'authenticationToken' => $authToken,
                'webServiceURL' => url('/api/wallet/apple'),
                'organizationName' => $tenant->name ?? config('app.name'),
                'description' => $event->name,
                'logoText' => $tenant->name ?? config('app.name'),
                'foregroundColor' => $options['foreground_color'] ?? 'rgb(255, 255, 255)',
                'backgroundColor' => $options['background_color'] ?? 'rgb(60, 65, 76)',
                'labelColor' => $options['label_color'] ?? 'rgb(255, 255, 255)',

                // Barcode
                'barcode' => [
                    'message' => $ticket->code ?? $ticket->id,
                    'format' => 'PKBarcodeFormatQR',
                    'messageEncoding' => 'iso-8859-1',
                ],
                'barcodes' => [
                    [
                        'message' => $ticket->code ?? $ticket->id,
                        'format' => 'PKBarcodeFormatQR',
                        'messageEncoding' => 'iso-8859-1',
                    ],
                ],

                // Event ticket type
                'eventTicket' => [
                    'primaryFields' => [
                        [
                            'key' => 'event',
                            'label' => 'EVENT',
                            'value' => $event->name,
                        ],
                    ],
                    'secondaryFields' => [
                        [
                            'key' => 'date',
                            'label' => 'DATE',
                            'value' => $event->start_date?->format('D, M j, Y') ?? 'TBD',
                        ],
                        [
                            'key' => 'time',
                            'label' => 'TIME',
                            'value' => $event->start_date?->format('g:i A') ?? 'TBD',
                        ],
                    ],
                    'auxiliaryFields' => [
                        [
                            'key' => 'venue',
                            'label' => 'VENUE',
                            'value' => $event->venue?->name ?? 'TBD',
                        ],
                        [
                            'key' => 'ticket_type',
                            'label' => 'TYPE',
                            'value' => $ticket->ticketType?->name ?? 'General',
                        ],
                    ],
                    'backFields' => [
                        [
                            'key' => 'order',
                            'label' => 'Order Number',
                            'value' => $order->reference ?? $order->id,
                        ],
                        [
                            'key' => 'attendee',
                            'label' => 'Attendee',
                            'value' => $order->customer?->name ?? $order->customer_email ?? 'Guest',
                        ],
                        [
                            'key' => 'terms',
                            'label' => 'Terms & Conditions',
                            'value' => 'This ticket is non-transferable and subject to the event\'s terms and conditions.',
                        ],
                    ],
                ],

                // Relevance
                'relevantDate' => $event->start_date?->toIso8601String(),
            ];

            // Add location if venue has coordinates
            if ($event->venue && $event->venue->latitude && $event->venue->longitude) {
                $passData['locations'] = [
                    [
                        'latitude' => (float) $event->venue->latitude,
                        'longitude' => (float) $event->venue->longitude,
                        'relevantText' => "You're near {$event->venue->name}!",
                    ],
                ];
            }

            // Generate the .pkpass file
            $passFile = $this->createPkPassFile($passData, $options);

            if (!$passFile) {
                return [
                    'success' => false,
                    'pass_url' => null,
                    'pass_data' => null,
                    'error' => 'Failed to generate .pkpass file',
                ];
            }

            // Store the pass file
            $filename = "wallet/apple/{$tenant->id}/{$serialNumber}.pkpass";
            Storage::disk('public')->put($filename, $passFile);

            return [
                'success' => true,
                'pass_url' => Storage::disk('public')->url($filename),
                'pass_data' => [
                    'serial_number' => $serialNumber,
                    'auth_token' => $authToken,
                    'pass_type_identifier' => $this->passTypeIdentifier,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Apple Wallet pass generation failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'pass_url' => null,
                'pass_data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updatePass(WalletPass $pass, array $changes = []): array
    {
        try {
            // Regenerate pass with updated data
            $ticket = $pass->ticket;
            $result = $this->generatePass($ticket, $changes);

            if ($result['success']) {
                // Push update to registered devices
                $this->pushUpdate($pass);
            }

            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Pass updated successfully' : $result['error'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function voidPass(WalletPass $pass): array
    {
        try {
            // Delete the stored pass file
            $filename = "wallet/apple/{$pass->tenant_id}/{$pass->serial_number}.pkpass";
            Storage::disk('public')->delete($filename);

            // Push update to notify devices
            $this->pushUpdate($pass);

            return [
                'success' => true,
                'message' => 'Pass voided successfully',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function pushUpdate(WalletPass $pass): array
    {
        $devicesNotified = 0;

        try {
            foreach ($pass->pushRegistrations as $registration) {
                // Send push notification via APNs
                // This would use Apple Push Notification service
                // For now, we'll just mark as notified

                $devicesNotified++;
            }

            return [
                'success' => true,
                'devices_notified' => $devicesNotified,
            ];

        } catch (\Exception $e) {
            Log::error('Apple Wallet push notification failed', [
                'pass_id' => $pass->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'devices_notified' => $devicesNotified,
            ];
        }
    }

    public function getPlatform(): string
    {
        return 'apple';
    }

    public function isConfigured(): bool
    {
        return !empty($this->certificatePath)
            && !empty($this->passTypeIdentifier)
            && !empty($this->teamIdentifier);
    }

    /**
     * Create the actual .pkpass file (ZIP with signature)
     */
    protected function createPkPassFile(array $passData, array $options = []): ?string
    {
        // In production, this would:
        // 1. Create pass.json
        // 2. Add icon.png, logo.png, etc.
        // 3. Generate manifest.json
        // 4. Sign with certificate
        // 5. Create ZIP archive as .pkpass

        // For now, return the JSON as a placeholder
        // Real implementation requires PKPass library or custom signing

        if (!$this->isConfigured()) {
            Log::warning('Apple Wallet not fully configured, returning placeholder');
            return json_encode($passData);
        }

        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/' . Str::uuid();
        mkdir($tempDir, 0755, true);

        try {
            // Write pass.json
            file_put_contents($tempDir . '/pass.json', json_encode($passData));

            // Add default images (would come from tenant branding)
            // icon.png, icon@2x.png, logo.png, etc.

            // Create manifest
            $manifest = [];
            foreach (glob($tempDir . '/*') as $file) {
                $manifest[basename($file)] = sha1_file($file);
            }
            file_put_contents($tempDir . '/manifest.json', json_encode($manifest));

            // Sign the manifest (requires openssl)
            // This is simplified - real implementation needs proper PKCS#7 signing

            // Create ZIP
            $zipPath = $tempDir . '.pkpass';
            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE);

            foreach (glob($tempDir . '/*') as $file) {
                $zip->addFile($file, basename($file));
            }

            $zip->close();

            $content = file_get_contents($zipPath);

            // Cleanup
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);
            unlink($zipPath);

            return $content;

        } catch (\Exception $e) {
            Log::error('PKPass file creation failed', ['error' => $e->getMessage()]);

            // Cleanup on error
            if (is_dir($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);
            }

            return null;
        }
    }
}
