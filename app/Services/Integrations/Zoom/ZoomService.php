<?php

namespace App\Services\Integrations\Zoom;

use App\Models\Integrations\Zoom\ZoomConnection;
use App\Models\Integrations\Zoom\ZoomMeeting;
use App\Models\Integrations\Zoom\ZoomWebinar;
use App\Models\Integrations\Zoom\ZoomParticipant;
use App\Models\Integrations\Zoom\ZoomRecording;
use App\Models\Integrations\Zoom\ZoomWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class ZoomService
{
    protected string $apiBaseUrl = 'https://api.zoom.us/v2';
    protected string $oauthUrl = 'https://zoom.us/oauth';

    // ==========================================
    // OAUTH FLOW
    // ==========================================

    public function getAuthorizationUrl(int $tenantId): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.zoom.client_id'),
            'redirect_uri' => config('services.zoom.redirect_uri'),
            'state' => encrypt(['tenant_id' => $tenantId]),
        ];

        return $this->oauthUrl . '/authorize?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): ZoomConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::withBasicAuth(
            config('services.zoom.client_id'),
            config('services.zoom.client_secret')
        )->asForm()->post($this->oauthUrl . '/token', [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.zoom.redirect_uri'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        // Get user info
        $userInfo = Http::withToken($data['access_token'])
            ->get($this->apiBaseUrl . '/users/me')
            ->json();

        return ZoomConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userInfo['id']],
            [
                'account_id' => $userInfo['account_id'],
                'email' => $userInfo['email'],
                'display_name' => $userInfo['first_name'] . ' ' . $userInfo['last_name'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'token_expires_at' => now()->addSeconds($data['expires_in']),
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'account_type' => $userInfo['type'] ?? null,
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function refreshToken(ZoomConnection $connection): bool
    {
        $response = Http::withBasicAuth(
            config('services.zoom.client_id'),
            config('services.zoom.client_secret')
        )->asForm()->post($this->oauthUrl . '/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);

        if (!$response->successful()) {
            return false;
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return true;
    }

    // ==========================================
    // MEETINGS
    // ==========================================

    public function createMeeting(
        ZoomConnection $connection,
        string $topic,
        array $options = []
    ): ZoomMeeting {
        $meetingData = [
            'topic' => $topic,
            'type' => $options['type'] ?? 2, // Scheduled meeting
            'duration' => $options['duration'] ?? 60,
            'timezone' => $options['timezone'] ?? 'UTC',
            'agenda' => $options['agenda'] ?? null,
            'settings' => array_merge([
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'waiting_room' => true,
                'approval_type' => 2, // No registration required
            ], $options['settings'] ?? []),
        ];

        if (isset($options['start_time'])) {
            $meetingData['start_time'] = $options['start_time'];
        }

        if (isset($options['password'])) {
            $meetingData['password'] = $options['password'];
        }

        $response = $this->makeRequest($connection, 'POST', '/users/me/meetings', $meetingData);

        return ZoomMeeting::create([
            'connection_id' => $connection->id,
            'meeting_id' => (string) $response['id'],
            'uuid' => $response['uuid'] ?? null,
            'host_id' => $response['host_id'],
            'topic' => $response['topic'],
            'agenda' => $response['agenda'] ?? null,
            'type' => $response['type'],
            'start_time' => isset($response['start_time']) ? now()->parse($response['start_time']) : null,
            'duration' => $response['duration'] ?? null,
            'timezone' => $response['timezone'] ?? null,
            'join_url' => $response['join_url'],
            'start_url' => $response['start_url'],
            'password' => $response['password'] ?? null,
            'status' => 'waiting',
            'settings' => $response['settings'] ?? null,
            'correlation_type' => $options['correlation_type'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
        ]);
    }

    public function getMeeting(ZoomConnection $connection, string $meetingId): ?array
    {
        return $this->makeRequest($connection, 'GET', "/meetings/{$meetingId}");
    }

    public function updateMeeting(ZoomConnection $connection, string $meetingId, array $data): bool
    {
        $this->makeRequest($connection, 'PATCH', "/meetings/{$meetingId}", $data);

        $meeting = ZoomMeeting::where('meeting_id', $meetingId)->first();
        if ($meeting) {
            $meeting->update(array_filter([
                'topic' => $data['topic'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'start_time' => isset($data['start_time']) ? now()->parse($data['start_time']) : null,
                'duration' => $data['duration'] ?? null,
                'settings' => $data['settings'] ?? null,
            ]));
        }

        return true;
    }

    public function deleteMeeting(ZoomConnection $connection, string $meetingId): bool
    {
        $this->makeRequest($connection, 'DELETE', "/meetings/{$meetingId}");

        ZoomMeeting::where('meeting_id', $meetingId)->delete();

        return true;
    }

    public function listMeetings(ZoomConnection $connection, string $type = 'scheduled'): Collection
    {
        $response = $this->makeRequest($connection, 'GET', '/users/me/meetings', [
            'type' => $type,
            'page_size' => 100,
        ]);

        foreach ($response['meetings'] ?? [] as $meetingData) {
            ZoomMeeting::updateOrCreate(
                ['connection_id' => $connection->id, 'meeting_id' => (string) $meetingData['id']],
                [
                    'uuid' => $meetingData['uuid'] ?? null,
                    'host_id' => $meetingData['host_id'],
                    'topic' => $meetingData['topic'],
                    'type' => $meetingData['type'],
                    'start_time' => isset($meetingData['start_time']) ? now()->parse($meetingData['start_time']) : null,
                    'duration' => $meetingData['duration'] ?? null,
                    'timezone' => $meetingData['timezone'] ?? null,
                    'join_url' => $meetingData['join_url'],
                ]
            );
        }

        return $connection->meetings()->get();
    }

    // ==========================================
    // WEBINARS
    // ==========================================

    public function createWebinar(
        ZoomConnection $connection,
        string $topic,
        array $options = []
    ): ZoomWebinar {
        $webinarData = [
            'topic' => $topic,
            'type' => $options['type'] ?? 5, // Webinar
            'duration' => $options['duration'] ?? 60,
            'timezone' => $options['timezone'] ?? 'UTC',
            'agenda' => $options['agenda'] ?? null,
            'settings' => array_merge([
                'host_video' => true,
                'panelists_video' => true,
                'approval_type' => 0, // Automatic approval
                'registration_type' => 1, // Register once and can attend any occurrence
            ], $options['settings'] ?? []),
        ];

        if (isset($options['start_time'])) {
            $webinarData['start_time'] = $options['start_time'];
        }

        $response = $this->makeRequest($connection, 'POST', '/users/me/webinars', $webinarData);

        return ZoomWebinar::create([
            'connection_id' => $connection->id,
            'webinar_id' => (string) $response['id'],
            'uuid' => $response['uuid'] ?? null,
            'host_id' => $response['host_id'],
            'topic' => $response['topic'],
            'agenda' => $response['agenda'] ?? null,
            'type' => $response['type'],
            'start_time' => isset($response['start_time']) ? now()->parse($response['start_time']) : null,
            'duration' => $response['duration'] ?? null,
            'timezone' => $response['timezone'] ?? null,
            'join_url' => $response['join_url'],
            'registration_url' => $response['registration_url'] ?? null,
            'password' => $response['password'] ?? null,
            'status' => 'waiting',
            'settings' => $response['settings'] ?? null,
            'correlation_type' => $options['correlation_type'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
        ]);
    }

    // ==========================================
    // PARTICIPANTS / REGISTRANTS
    // ==========================================

    public function addRegistrant(
        ZoomConnection $connection,
        string $meetingId,
        string $email,
        string $firstName,
        ?string $lastName = null,
        array $customQuestions = [],
        bool $isWebinar = false
    ): ZoomParticipant {
        $endpoint = $isWebinar
            ? "/webinars/{$meetingId}/registrants"
            : "/meetings/{$meetingId}/registrants";

        $data = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName ?? '',
        ];

        if (!empty($customQuestions)) {
            $data['custom_questions'] = $customQuestions;
        }

        $response = $this->makeRequest($connection, 'POST', $endpoint, $data);

        return ZoomParticipant::create([
            'connection_id' => $connection->id,
            'participant_type' => $isWebinar ? 'webinar' : 'meeting',
            'meeting_id' => $meetingId,
            'registrant_id' => $response['registrant_id'],
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'registered',
            'join_url' => $response['join_url'],
            'registered_at' => now(),
            'custom_questions' => $customQuestions,
        ]);
    }

    public function listParticipants(
        ZoomConnection $connection,
        string $meetingId,
        bool $isWebinar = false
    ): Collection {
        $endpoint = $isWebinar
            ? "/past_webinars/{$meetingId}/participants"
            : "/past_meetings/{$meetingId}/participants";

        $response = $this->makeRequest($connection, 'GET', $endpoint);

        foreach ($response['participants'] ?? [] as $participantData) {
            ZoomParticipant::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'meeting_id' => $meetingId,
                    'email' => $participantData['user_email'] ?? $participantData['email'] ?? 'unknown',
                ],
                [
                    'participant_type' => $isWebinar ? 'webinar' : 'meeting',
                    'participant_id' => $participantData['id'] ?? null,
                    'first_name' => $participantData['name'] ?? null,
                    'status' => 'attended',
                    'joined_at' => isset($participantData['join_time']) ? now()->parse($participantData['join_time']) : null,
                    'left_at' => isset($participantData['leave_time']) ? now()->parse($participantData['leave_time']) : null,
                    'duration_seconds' => $participantData['duration'] ?? null,
                ]
            );
        }

        return ZoomParticipant::where('meeting_id', $meetingId)->get();
    }

    // ==========================================
    // RECORDINGS
    // ==========================================

    public function listRecordings(ZoomConnection $connection, string $from = null, string $to = null): Collection
    {
        $params = [];
        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $response = $this->makeRequest($connection, 'GET', '/users/me/recordings', $params);

        foreach ($response['meetings'] ?? [] as $meetingData) {
            $meeting = ZoomMeeting::where('meeting_id', (string) $meetingData['id'])->first();

            foreach ($meetingData['recording_files'] ?? [] as $recording) {
                ZoomRecording::updateOrCreate(
                    ['connection_id' => $connection->id, 'recording_id' => $recording['id']],
                    [
                        'meeting_id' => $meeting?->id,
                        'meeting_uuid' => $meetingData['uuid'],
                        'recording_type' => $recording['recording_type'],
                        'file_type' => $recording['file_type'],
                        'file_size' => $recording['file_size'] ?? null,
                        'download_url' => $recording['download_url'] ?? null,
                        'play_url' => $recording['play_url'] ?? null,
                        'password' => $meetingData['password'] ?? null,
                        'status' => 'available',
                        'recording_start' => isset($recording['recording_start']) ? now()->parse($recording['recording_start']) : null,
                        'recording_end' => isset($recording['recording_end']) ? now()->parse($recording['recording_end']) : null,
                    ]
                );
            }
        }

        return $connection->recordings()->get();
    }

    public function getRecordingDownloadUrl(ZoomConnection $connection, string $recordingId): ?string
    {
        $recording = ZoomRecording::where('recording_id', $recordingId)->first();

        if (!$recording || !$recording->download_url) {
            return null;
        }

        // The download URL requires the access token
        return $recording->download_url . '?access_token=' . $connection->access_token;
    }

    // ==========================================
    // WEBHOOK PROCESSING
    // ==========================================

    public function processWebhook(array $payload): void
    {
        $eventType = $payload['event'] ?? 'unknown';
        $accountId = $payload['payload']['account_id'] ?? null;

        $connection = $accountId
            ? ZoomConnection::where('account_id', $accountId)->first()
            : null;

        $event = ZoomWebhookEvent::create([
            'connection_id' => $connection?->id,
            'event_type' => $eventType,
            'event_ts' => $payload['event_ts'] ?? null,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        if (!$connection) {
            $event->markAsFailed('No matching connection found');
            return;
        }

        try {
            $object = $payload['payload']['object'] ?? [];

            switch ($eventType) {
                case 'meeting.started':
                    $this->handleMeetingStarted($object);
                    break;

                case 'meeting.ended':
                    $this->handleMeetingEnded($object);
                    break;

                case 'meeting.participant_joined':
                    $this->handleParticipantJoined($connection, $object);
                    break;

                case 'meeting.participant_left':
                    $this->handleParticipantLeft($connection, $object);
                    break;

                case 'webinar.registration_created':
                    $this->handleRegistrationCreated($connection, $object, 'webinar');
                    break;

                case 'recording.completed':
                    $this->handleRecordingCompleted($connection, $object);
                    break;
            }

            $event->markAsProcessed();
        } catch (\Exception $e) {
            $event->markAsFailed($e->getMessage());
        }
    }

    protected function handleMeetingStarted(array $object): void
    {
        $meetingId = (string) ($object['id'] ?? '');

        ZoomMeeting::where('meeting_id', $meetingId)->update([
            'uuid' => $object['uuid'] ?? null,
            'status' => 'started',
        ]);
    }

    protected function handleMeetingEnded(array $object): void
    {
        $meetingId = (string) ($object['id'] ?? '');

        ZoomMeeting::where('meeting_id', $meetingId)->update([
            'status' => 'finished',
        ]);
    }

    protected function handleParticipantJoined(ZoomConnection $connection, array $object): void
    {
        $meetingId = (string) ($object['id'] ?? '');
        $participant = $object['participant'] ?? [];

        ZoomParticipant::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'meeting_id' => $meetingId,
                'email' => $participant['email'] ?? $participant['user_name'] ?? 'unknown',
            ],
            [
                'participant_type' => 'meeting',
                'participant_id' => $participant['user_id'] ?? null,
                'first_name' => $participant['user_name'] ?? null,
                'status' => 'attended',
                'joined_at' => isset($participant['join_time']) ? now()->parse($participant['join_time']) : now(),
            ]
        );
    }

    protected function handleParticipantLeft(ZoomConnection $connection, array $object): void
    {
        $meetingId = (string) ($object['id'] ?? '');
        $participant = $object['participant'] ?? [];

        ZoomParticipant::where('connection_id', $connection->id)
            ->where('meeting_id', $meetingId)
            ->where(function ($q) use ($participant) {
                $q->where('email', $participant['email'] ?? '')
                    ->orWhere('participant_id', $participant['user_id'] ?? '');
            })
            ->update([
                'left_at' => isset($participant['leave_time']) ? now()->parse($participant['leave_time']) : now(),
            ]);
    }

    protected function handleRegistrationCreated(ZoomConnection $connection, array $object, string $type): void
    {
        $meetingId = (string) ($object['id'] ?? '');
        $registrant = $object['registrant'] ?? [];

        ZoomParticipant::create([
            'connection_id' => $connection->id,
            'participant_type' => $type,
            'meeting_id' => $meetingId,
            'registrant_id' => $registrant['id'] ?? null,
            'email' => $registrant['email'],
            'first_name' => $registrant['first_name'] ?? null,
            'last_name' => $registrant['last_name'] ?? null,
            'status' => 'registered',
            'join_url' => $registrant['join_url'] ?? null,
            'registered_at' => now(),
        ]);
    }

    protected function handleRecordingCompleted(ZoomConnection $connection, array $object): void
    {
        $meetingId = (string) ($object['id'] ?? '');
        $meeting = ZoomMeeting::where('meeting_id', $meetingId)->first();

        foreach ($object['recording_files'] ?? [] as $recording) {
            ZoomRecording::create([
                'connection_id' => $connection->id,
                'meeting_id' => $meeting?->id,
                'recording_id' => $recording['id'],
                'meeting_uuid' => $object['uuid'],
                'recording_type' => $recording['recording_type'],
                'file_type' => $recording['file_type'],
                'file_size' => $recording['file_size'] ?? null,
                'download_url' => $recording['download_url'] ?? null,
                'play_url' => $recording['play_url'] ?? null,
                'password' => $object['password'] ?? null,
                'status' => 'available',
                'recording_start' => isset($recording['recording_start']) ? now()->parse($recording['recording_start']) : null,
                'recording_end' => isset($recording['recording_end']) ? now()->parse($recording['recording_end']) : null,
            ]);
        }
    }

    // ==========================================
    // BUSINESS USE CASES
    // ==========================================

    public function createEventMeeting(
        ZoomConnection $connection,
        int $eventId,
        string $eventName,
        string $startTime,
        int $duration = 60
    ): ZoomMeeting {
        return $this->createMeeting($connection, $eventName, [
            'start_time' => $startTime,
            'duration' => $duration,
            'correlation_type' => 'event',
            'correlation_id' => $eventId,
            'settings' => [
                'waiting_room' => true,
                'approval_type' => 0,
            ],
        ]);
    }

    public function registerAttendeeForMeeting(
        ZoomConnection $connection,
        ZoomMeeting $meeting,
        string $email,
        string $name,
        ?int $localId = null,
        ?string $localType = null
    ): ZoomParticipant {
        $nameParts = explode(' ', $name, 2);

        $participant = $this->addRegistrant(
            $connection,
            $meeting->meeting_id,
            $email,
            $nameParts[0],
            $nameParts[1] ?? null
        );

        if ($localId && $localType) {
            $participant->update([
                'local_type' => $localType,
                'local_id' => $localId,
            ]);
        }

        return $participant;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function makeRequest(
        ZoomConnection $connection,
        string $method,
        string $endpoint,
        array $params = []
    ): array {
        // Refresh token if expired
        if ($connection->isTokenExpired()) {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        $url = $this->apiBaseUrl . $endpoint;

        $request = Http::withToken($connection->access_token);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            'PATCH' => $request->patch($url, $params),
            'PUT' => $request->put($url, $params),
            'DELETE' => $request->delete($url, $params),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            $error = $response->json() ?? [];
            throw new \Exception(
                $error['message'] ?? 'Zoom API request failed',
                $error['code'] ?? $response->status()
            );
        }

        $connection->update(['last_used_at' => now()]);

        return $response->json() ?? [];
    }

    public function getConnection(int $tenantId): ?ZoomConnection
    {
        return ZoomConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }
}
