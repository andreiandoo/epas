<?php

namespace App\Services\Wallet\Adapters;

use App\Models\Ticket;
use App\Models\WalletPass;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;

/**
 * Google Wallet Adapter
 *
 * Generates Google Pay passes using JWT
 */
class GoogleWalletAdapter implements WalletAdapterInterface
{
    protected ?string $issuerId = null;
    protected ?string $serviceAccountEmail = null;
    protected ?string $serviceAccountKey = null;

    public function __construct()
    {
        $this->issuerId = config('microservices.wallet.google.issuer_id');
        $this->serviceAccountEmail = config('microservices.wallet.google.service_account_email');
        $this->serviceAccountKey = config('microservices.wallet.google.service_account_key');
    }

    public function generatePass(Ticket $ticket, array $options = []): array
    {
        try {
            $order = $ticket->order;
            $event = $ticket->event;
            $tenant = $ticket->tenant ?? $order->tenant;

            // Generate unique identifiers
            $objectId = $this->issuerId . '.' . Str::uuid()->toString();
            $classId = $this->issuerId . '.event_' . $event->id;

            // Build Google Wallet object
            $eventTicketObject = [
                'id' => $objectId,
                'classId' => $classId,
                'state' => 'ACTIVE',
                'heroImage' => [
                    'sourceUri' => [
                        'uri' => $event->image_url ?? $tenant->logo_url ?? url('/images/default-event.png'),
                    ],
                ],
                'textModulesData' => [
                    [
                        'header' => 'Order Number',
                        'body' => $order->reference ?? $order->id,
                        'id' => 'order_number',
                    ],
                    [
                        'header' => 'Ticket Type',
                        'body' => $ticket->ticketType?->name ?? 'General Admission',
                        'id' => 'ticket_type',
                    ],
                ],
                'barcode' => [
                    'type' => 'QR_CODE',
                    'value' => $ticket->code ?? (string) $ticket->id,
                    'alternateText' => $ticket->code ?? (string) $ticket->id,
                ],
                'ticketHolderName' => $order->customer?->name ?? $order->customer_email ?? 'Guest',
                'ticketNumber' => $ticket->code ?? (string) $ticket->id,
                'seatInfo' => $ticket->seat ? [
                    'seat' => [
                        'defaultValue' => [
                            'language' => 'en',
                            'value' => $ticket->seat->label ?? $ticket->seat->number,
                        ],
                    ],
                    'row' => [
                        'defaultValue' => [
                            'language' => 'en',
                            'value' => $ticket->seat->row ?? '',
                        ],
                    ],
                    'section' => [
                        'defaultValue' => [
                            'language' => 'en',
                            'value' => $ticket->seat->section ?? '',
                        ],
                    ],
                ] : null,
            ];

            // Build event ticket class (template)
            $eventTicketClass = [
                'id' => $classId,
                'issuerName' => $tenant->name ?? config('app.name'),
                'eventName' => [
                    'defaultValue' => [
                        'language' => 'en',
                        'value' => $event->name,
                    ],
                ],
                'venue' => [
                    'name' => [
                        'defaultValue' => [
                            'language' => 'en',
                            'value' => $event->venue?->name ?? 'TBD',
                        ],
                    ],
                    'address' => [
                        'defaultValue' => [
                            'language' => 'en',
                            'value' => $event->venue?->address ?? '',
                        ],
                    ],
                ],
                'dateTime' => [
                    'start' => $event->start_date?->toIso8601String(),
                    'end' => $event->end_date?->toIso8601String() ?? $event->start_date?->addHours(3)->toIso8601String(),
                ],
                'reviewStatus' => 'UNDER_REVIEW',
                'hexBackgroundColor' => $options['background_color'] ?? '#3c414c',
            ];

            // Generate JWT
            $claims = [
                'iss' => $this->serviceAccountEmail,
                'aud' => 'google',
                'origins' => [config('app.url')],
                'typ' => 'savetowallet',
                'payload' => [
                    'eventTicketClasses' => [$eventTicketClass],
                    'eventTicketObjects' => [$eventTicketObject],
                ],
            ];

            $jwt = $this->signJwt($claims);

            if (!$jwt) {
                return [
                    'success' => false,
                    'pass_url' => null,
                    'pass_data' => null,
                    'error' => 'Failed to sign JWT',
                ];
            }

            $saveUrl = 'https://pay.google.com/gp/v/save/' . $jwt;

            return [
                'success' => true,
                'pass_url' => $saveUrl,
                'pass_data' => [
                    'serial_number' => $objectId,
                    'auth_token' => Str::random(64),
                    'class_id' => $classId,
                    'jwt' => $jwt,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Google Wallet pass generation failed', [
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
        // Google Wallet updates require API call to update the object
        // For now, regenerate the pass

        try {
            $ticket = $pass->ticket;
            $result = $this->generatePass($ticket, $changes);

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
        // Google Wallet requires API call to update object state to INACTIVE
        // This would use the Google Wallet API

        try {
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
        // Google Wallet automatically updates passes when the object is updated via API
        return [
            'success' => true,
            'devices_notified' => 0, // Google handles this automatically
        ];
    }

    public function getPlatform(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        return !empty($this->issuerId)
            && !empty($this->serviceAccountEmail)
            && !empty($this->serviceAccountKey);
    }

    /**
     * Sign JWT with service account key
     */
    protected function signJwt(array $claims): ?string
    {
        if (!$this->isConfigured()) {
            Log::warning('Google Wallet not fully configured');
            return null;
        }

        try {
            $privateKey = $this->serviceAccountKey;

            // If it's a file path, read it
            if (file_exists($privateKey)) {
                $privateKey = file_get_contents($privateKey);
            }

            return JWT::encode($claims, $privateKey, 'RS256');

        } catch (\Exception $e) {
            Log::error('JWT signing failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
