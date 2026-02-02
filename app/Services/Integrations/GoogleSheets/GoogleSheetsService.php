<?php

namespace App\Services\Integrations\GoogleSheets;

use App\Models\Integrations\GoogleSheets\GoogleSheetsConnection;
use App\Models\Integrations\GoogleSheets\GoogleSheetsSpreadsheet;
use App\Models\Integrations\GoogleSheets\GoogleSheetsSyncJob;
use App\Models\Integrations\GoogleSheets\GoogleSheetsColumnMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class GoogleSheetsService
{
    protected string $authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $tokenUrl = 'https://oauth2.googleapis.com/token';
    protected string $sheetsApiUrl = 'https://sheets.googleapis.com/v4/spreadsheets';
    protected string $driveApiUrl = 'https://www.googleapis.com/drive/v3';

    // Default column mappings for different data types
    protected array $defaultColumnMappings = [
        'orders' => [
            ['local_field' => 'id', 'column_header' => 'Order ID', 'data_format' => 'text'],
            ['local_field' => 'order_number', 'column_header' => 'Order Number', 'data_format' => 'text'],
            ['local_field' => 'customer_name', 'column_header' => 'Customer Name', 'data_format' => 'text'],
            ['local_field' => 'customer_email', 'column_header' => 'Customer Email', 'data_format' => 'text'],
            ['local_field' => 'event_name', 'column_header' => 'Event', 'data_format' => 'text'],
            ['local_field' => 'total_amount', 'column_header' => 'Total', 'data_format' => 'currency'],
            ['local_field' => 'currency', 'column_header' => 'Currency', 'data_format' => 'text'],
            ['local_field' => 'status', 'column_header' => 'Status', 'data_format' => 'text'],
            ['local_field' => 'payment_method', 'column_header' => 'Payment Method', 'data_format' => 'text'],
            ['local_field' => 'tickets_count', 'column_header' => 'Tickets', 'data_format' => 'number'],
            ['local_field' => 'created_at', 'column_header' => 'Order Date', 'data_format' => 'date'],
        ],
        'tickets' => [
            ['local_field' => 'id', 'column_header' => 'Ticket ID', 'data_format' => 'text'],
            ['local_field' => 'ticket_number', 'column_header' => 'Ticket Number', 'data_format' => 'text'],
            ['local_field' => 'order_number', 'column_header' => 'Order Number', 'data_format' => 'text'],
            ['local_field' => 'event_name', 'column_header' => 'Event', 'data_format' => 'text'],
            ['local_field' => 'event_date', 'column_header' => 'Event Date', 'data_format' => 'date'],
            ['local_field' => 'ticket_type', 'column_header' => 'Ticket Type', 'data_format' => 'text'],
            ['local_field' => 'attendee_name', 'column_header' => 'Attendee Name', 'data_format' => 'text'],
            ['local_field' => 'attendee_email', 'column_header' => 'Attendee Email', 'data_format' => 'text'],
            ['local_field' => 'price', 'column_header' => 'Price', 'data_format' => 'currency'],
            ['local_field' => 'status', 'column_header' => 'Status', 'data_format' => 'text'],
            ['local_field' => 'checked_in', 'column_header' => 'Checked In', 'data_format' => 'text'],
            ['local_field' => 'checked_in_at', 'column_header' => 'Check-in Time', 'data_format' => 'datetime'],
        ],
        'customers' => [
            ['local_field' => 'id', 'column_header' => 'Customer ID', 'data_format' => 'text'],
            ['local_field' => 'name', 'column_header' => 'Name', 'data_format' => 'text'],
            ['local_field' => 'email', 'column_header' => 'Email', 'data_format' => 'text'],
            ['local_field' => 'phone', 'column_header' => 'Phone', 'data_format' => 'text'],
            ['local_field' => 'total_orders', 'column_header' => 'Total Orders', 'data_format' => 'number'],
            ['local_field' => 'total_spent', 'column_header' => 'Total Spent', 'data_format' => 'currency'],
            ['local_field' => 'first_order_at', 'column_header' => 'First Order', 'data_format' => 'date'],
            ['local_field' => 'last_order_at', 'column_header' => 'Last Order', 'data_format' => 'date'],
            ['local_field' => 'created_at', 'column_header' => 'Registered', 'data_format' => 'date'],
        ],
        'events' => [
            ['local_field' => 'id', 'column_header' => 'Event ID', 'data_format' => 'text'],
            ['local_field' => 'name', 'column_header' => 'Event Name', 'data_format' => 'text'],
            ['local_field' => 'date', 'column_header' => 'Date', 'data_format' => 'date'],
            ['local_field' => 'time', 'column_header' => 'Time', 'data_format' => 'text'],
            ['local_field' => 'venue', 'column_header' => 'Venue', 'data_format' => 'text'],
            ['local_field' => 'capacity', 'column_header' => 'Capacity', 'data_format' => 'number'],
            ['local_field' => 'tickets_sold', 'column_header' => 'Tickets Sold', 'data_format' => 'number'],
            ['local_field' => 'revenue', 'column_header' => 'Revenue', 'data_format' => 'currency'],
            ['local_field' => 'status', 'column_header' => 'Status', 'data_format' => 'text'],
        ],
    ];

    // ==========================================
    // OAUTH FLOW
    // ==========================================

    public function getAuthorizationUrl(int $tenantId): string
    {
        $scopes = [
            'https://www.googleapis.com/auth/spreadsheets',
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
        ];

        $params = [
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google_sheets.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => encrypt(['tenant_id' => $tenantId, 'service' => 'sheets']),
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): GoogleSheetsConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.google_sheets.redirect_uri'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token: ' . $response->body());
        }

        $data = $response->json();
        $userInfo = $this->getUserInfo($data['access_token']);

        return GoogleSheetsConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'google_user_id' => $userInfo['sub']],
            [
                'email' => $userInfo['email'],
                'name' => $userInfo['name'] ?? null,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds($data['expires_in'])
                    : null,
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    protected function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');
        return $response->json();
    }

    public function disconnect(GoogleSheetsConnection $connection): bool
    {
        // Revoke the token
        Http::post('https://oauth2.googleapis.com/revoke', [
            'token' => $connection->access_token,
        ]);

        $connection->update([
            'status' => 'revoked',
            'access_token' => null,
            'refresh_token' => null,
        ]);

        return true;
    }

    public function testConnection(GoogleSheetsConnection $connection): array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get($this->driveApiUrl . '/about', ['fields' => 'user']);

        return [
            'success' => $response->successful(),
            'user' => $response->json('user'),
        ];
    }

    // ==========================================
    // SPREADSHEET MANAGEMENT
    // ==========================================

    public function createSpreadsheet(
        GoogleSheetsConnection $connection,
        string $title,
        string $purpose,
        array $options = []
    ): GoogleSheetsSpreadsheet {
        $this->ensureValidToken($connection);

        // Create spreadsheet with sheets for the data type
        $payload = [
            'properties' => [
                'title' => $title,
            ],
            'sheets' => [
                [
                    'properties' => [
                        'title' => ucfirst($purpose),
                        'gridProperties' => [
                            'frozenRowCount' => 1, // Freeze header row
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($connection->access_token)
            ->post($this->sheetsApiUrl, $payload);

        if (!$response->successful()) {
            throw new \Exception('Failed to create spreadsheet: ' . $response->body());
        }

        $data = $response->json();

        $spreadsheet = GoogleSheetsSpreadsheet::create([
            'connection_id' => $connection->id,
            'spreadsheet_id' => $data['spreadsheetId'],
            'name' => $title,
            'purpose' => $purpose,
            'web_view_link' => $data['spreadsheetUrl'],
            'is_auto_sync' => $options['auto_sync'] ?? false,
            'sync_frequency' => $options['sync_frequency'] ?? null,
            'sheet_config' => [
                'sheet_id' => $data['sheets'][0]['properties']['sheetId'],
                'sheet_name' => ucfirst($purpose),
            ],
        ]);

        // Create default column mappings
        $this->createDefaultColumnMappings($spreadsheet, $purpose);

        // Write header row
        $this->writeHeaderRow($connection, $spreadsheet);

        $connection->update(['last_used_at' => now()]);

        return $spreadsheet;
    }

    public function createDefaultColumnMappings(GoogleSheetsSpreadsheet $spreadsheet, string $dataType): void
    {
        $mappings = $this->defaultColumnMappings[$dataType] ?? [];
        $columns = range('A', 'Z');

        foreach ($mappings as $index => $mapping) {
            GoogleSheetsColumnMapping::create([
                'spreadsheet_id' => $spreadsheet->id,
                'data_type' => $dataType,
                'local_field' => $mapping['local_field'],
                'sheet_column' => $columns[$index] ?? 'A',
                'column_header' => $mapping['column_header'],
                'data_format' => $mapping['data_format'],
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    protected function writeHeaderRow(GoogleSheetsConnection $connection, GoogleSheetsSpreadsheet $spreadsheet): void
    {
        $mappings = $spreadsheet->columnMappings()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($mappings->isEmpty()) {
            return;
        }

        $headers = $mappings->pluck('column_header')->toArray();
        $sheetName = $spreadsheet->sheet_config['sheet_name'] ?? 'Sheet1';

        $this->updateRange(
            $connection,
            $spreadsheet->spreadsheet_id,
            "{$sheetName}!A1",
            [$headers]
        );

        // Format header row (bold)
        $this->formatHeaderRow($connection, $spreadsheet);
    }

    protected function formatHeaderRow(GoogleSheetsConnection $connection, GoogleSheetsSpreadsheet $spreadsheet): void
    {
        $sheetId = $spreadsheet->sheet_config['sheet_id'] ?? 0;

        $requests = [
            [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => ['bold' => true],
                            'backgroundColor' => [
                                'red' => 0.9,
                                'green' => 0.9,
                                'blue' => 0.9,
                            ],
                        ],
                    ],
                    'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
                ],
            ],
        ];

        Http::withToken($connection->access_token)
            ->post("{$this->sheetsApiUrl}/{$spreadsheet->spreadsheet_id}:batchUpdate", [
                'requests' => $requests,
            ]);
    }

    public function listSpreadsheets(GoogleSheetsConnection $connection): Collection
    {
        return $connection->spreadsheets()->with('syncJobs')->get();
    }

    public function deleteSpreadsheet(GoogleSheetsSpreadsheet $spreadsheet): bool
    {
        $connection = $spreadsheet->connection;
        $this->ensureValidToken($connection);

        // Delete from Google Drive
        Http::withToken($connection->access_token)
            ->delete("{$this->driveApiUrl}/files/{$spreadsheet->spreadsheet_id}");

        return $spreadsheet->delete();
    }

    // ==========================================
    // DATA SYNC - ORDERS
    // ==========================================

    public function syncOrders(
        GoogleSheetsSpreadsheet $spreadsheet,
        array $orders,
        string $syncType = 'full',
        string $triggeredBy = 'manual'
    ): GoogleSheetsSyncJob {
        $connection = $spreadsheet->connection;
        $this->ensureValidToken($connection);

        $job = GoogleSheetsSyncJob::create([
            'spreadsheet_id' => $spreadsheet->id,
            'sync_type' => $syncType,
            'data_type' => 'orders',
            'status' => 'pending',
            'triggered_by' => $triggeredBy,
        ]);

        $job->markAsRunning();

        try {
            $mappings = $spreadsheet->getColumnMappingsFor('orders');
            $rows = $this->transformDataToRows($orders, $mappings);

            if ($syncType === 'full') {
                // Clear existing data (except header)
                $this->clearSheet($connection, $spreadsheet, startRow: 2);
            }

            // Append or update data
            $result = $this->appendRows($connection, $spreadsheet, $rows);

            $job->update([
                'rows_processed' => count($orders),
                'rows_created' => $result['updatedRows'] ?? count($orders),
            ]);

            $job->markAsCompleted();

            $spreadsheet->update(['last_synced_at' => now()]);
            $connection->update(['last_used_at' => now()]);

        } catch (\Exception $e) {
            $job->markAsFailed(['error' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    // ==========================================
    // DATA SYNC - TICKETS
    // ==========================================

    public function syncTickets(
        GoogleSheetsSpreadsheet $spreadsheet,
        array $tickets,
        string $syncType = 'full',
        string $triggeredBy = 'manual'
    ): GoogleSheetsSyncJob {
        $connection = $spreadsheet->connection;
        $this->ensureValidToken($connection);

        $job = GoogleSheetsSyncJob::create([
            'spreadsheet_id' => $spreadsheet->id,
            'sync_type' => $syncType,
            'data_type' => 'tickets',
            'status' => 'pending',
            'triggered_by' => $triggeredBy,
        ]);

        $job->markAsRunning();

        try {
            $mappings = $spreadsheet->getColumnMappingsFor('tickets');
            $rows = $this->transformDataToRows($tickets, $mappings);

            if ($syncType === 'full') {
                $this->clearSheet($connection, $spreadsheet, startRow: 2);
            }

            $result = $this->appendRows($connection, $spreadsheet, $rows);

            $job->update([
                'rows_processed' => count($tickets),
                'rows_created' => $result['updatedRows'] ?? count($tickets),
            ]);

            $job->markAsCompleted();

            $spreadsheet->update(['last_synced_at' => now()]);
            $connection->update(['last_used_at' => now()]);

        } catch (\Exception $e) {
            $job->markAsFailed(['error' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    // ==========================================
    // DATA SYNC - CUSTOMERS
    // ==========================================

    public function syncCustomers(
        GoogleSheetsSpreadsheet $spreadsheet,
        array $customers,
        string $syncType = 'full',
        string $triggeredBy = 'manual'
    ): GoogleSheetsSyncJob {
        $connection = $spreadsheet->connection;
        $this->ensureValidToken($connection);

        $job = GoogleSheetsSyncJob::create([
            'spreadsheet_id' => $spreadsheet->id,
            'sync_type' => $syncType,
            'data_type' => 'customers',
            'status' => 'pending',
            'triggered_by' => $triggeredBy,
        ]);

        $job->markAsRunning();

        try {
            $mappings = $spreadsheet->getColumnMappingsFor('customers');
            $rows = $this->transformDataToRows($customers, $mappings);

            if ($syncType === 'full') {
                $this->clearSheet($connection, $spreadsheet, startRow: 2);
            }

            $result = $this->appendRows($connection, $spreadsheet, $rows);

            $job->update([
                'rows_processed' => count($customers),
                'rows_created' => $result['updatedRows'] ?? count($customers),
            ]);

            $job->markAsCompleted();

            $spreadsheet->update(['last_synced_at' => now()]);

        } catch (\Exception $e) {
            $job->markAsFailed(['error' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    // ==========================================
    // REAL-TIME APPEND (for new orders/tickets)
    // ==========================================

    public function appendOrder(GoogleSheetsSpreadsheet $spreadsheet, array $order): bool
    {
        $connection = $spreadsheet->connection;
        $this->ensureValidToken($connection);

        $mappings = $spreadsheet->getColumnMappingsFor('orders');
        $rows = $this->transformDataToRows([$order], $mappings);

        $this->appendRows($connection, $spreadsheet, $rows);

        $connection->update(['last_used_at' => now()]);

        return true;
    }

    public function appendTicket(GoogleSheetsSpreadsheet $spreadsheet, array $ticket): bool
    {
        $connection = $spreadsheet->connection;
        $this->ensureValidToken($connection);

        $mappings = $spreadsheet->getColumnMappingsFor('tickets');
        $rows = $this->transformDataToRows([$ticket], $mappings);

        $this->appendRows($connection, $spreadsheet, $rows);

        $connection->update(['last_used_at' => now()]);

        return true;
    }

    // ==========================================
    // SHEETS API OPERATIONS
    // ==========================================

    protected function updateRange(
        GoogleSheetsConnection $connection,
        string $spreadsheetId,
        string $range,
        array $values
    ): array {
        $response = Http::withToken($connection->access_token)
            ->put("{$this->sheetsApiUrl}/{$spreadsheetId}/values/{$range}", [
                'valueInputOption' => 'USER_ENTERED',
                'values' => $values,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to update range: ' . $response->body());
        }

        return $response->json();
    }

    protected function appendRows(
        GoogleSheetsConnection $connection,
        GoogleSheetsSpreadsheet $spreadsheet,
        array $rows
    ): array {
        $sheetName = $spreadsheet->sheet_config['sheet_name'] ?? 'Sheet1';
        $range = "{$sheetName}!A:Z";

        $response = Http::withToken($connection->access_token)
            ->post("{$this->sheetsApiUrl}/{$spreadsheet->spreadsheet_id}/values/{$range}:append", [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
                'values' => $rows,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to append rows: ' . $response->body());
        }

        return $response->json();
    }

    protected function clearSheet(
        GoogleSheetsConnection $connection,
        GoogleSheetsSpreadsheet $spreadsheet,
        int $startRow = 2
    ): void {
        $sheetName = $spreadsheet->sheet_config['sheet_name'] ?? 'Sheet1';
        $range = "{$sheetName}!A{$startRow}:Z10000";

        Http::withToken($connection->access_token)
            ->post("{$this->sheetsApiUrl}/{$spreadsheet->spreadsheet_id}/values/{$range}:clear");
    }

    protected function transformDataToRows(array $data, array $mappings): array
    {
        $rows = [];

        foreach ($data as $item) {
            $row = [];
            foreach ($mappings as $mapping) {
                $value = $this->getNestedValue($item, $mapping['local_field']);
                $row[] = $this->formatValue($value, $mapping['data_format'] ?? 'text');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    protected function getNestedValue(array $data, string $key)
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    protected function formatValue($value, string $format): string
    {
        if ($value === null) {
            return '';
        }

        return match ($format) {
            'date' => $value instanceof \DateTime
                ? $value->format('Y-m-d')
                : (string) $value,
            'datetime' => $value instanceof \DateTime
                ? $value->format('Y-m-d H:i:s')
                : (string) $value,
            'currency' => is_numeric($value)
                ? number_format((float) $value, 2, '.', '')
                : (string) $value,
            'number' => is_numeric($value)
                ? (string) $value
                : '0',
            default => (string) $value,
        };
    }

    // ==========================================
    // TOKEN MANAGEMENT
    // ==========================================

    protected function ensureValidToken(GoogleSheetsConnection $connection): void
    {
        if ($connection->isTokenExpired()) {
            $this->refreshToken($connection);
        }
    }

    protected function refreshToken(GoogleSheetsConnection $connection): void
    {
        if (!$connection->refresh_token) {
            throw new \Exception('No refresh token available');
        }

        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            $connection->update(['status' => 'expired']);
            throw new \Exception('Failed to refresh token');
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => isset($data['expires_in'])
                ? now()->addSeconds($data['expires_in'])
                : null,
        ]);
    }

    // ==========================================
    // CONNECTION MANAGEMENT
    // ==========================================

    public function getConnection(int $tenantId): ?GoogleSheetsConnection
    {
        return GoogleSheetsConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    public function getConnections(int $tenantId): Collection
    {
        return GoogleSheetsConnection::where('tenant_id', $tenantId)->get();
    }

    // ==========================================
    // SCHEDULED SYNC
    // ==========================================

    public function processScheduledSyncs(): void
    {
        $spreadsheets = GoogleSheetsSpreadsheet::where('is_auto_sync', true)
            ->whereHas('connection', fn($q) => $q->where('status', 'active'))
            ->get();

        foreach ($spreadsheets as $spreadsheet) {
            if ($this->shouldSync($spreadsheet)) {
                // Queue sync job based on purpose
                // This would dispatch a job to Laravel's queue
            }
        }
    }

    protected function shouldSync(GoogleSheetsSpreadsheet $spreadsheet): bool
    {
        if (!$spreadsheet->last_synced_at) {
            return true;
        }

        $frequency = $spreadsheet->sync_frequency;
        $lastSync = $spreadsheet->last_synced_at;

        return match ($frequency) {
            'hourly' => $lastSync->diffInHours(now()) >= 1,
            'daily' => $lastSync->diffInDays(now()) >= 1,
            'weekly' => $lastSync->diffInWeeks(now()) >= 1,
            default => false,
        };
    }
}
