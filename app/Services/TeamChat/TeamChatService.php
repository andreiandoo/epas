<?php

namespace App\Services\TeamChat;

use App\Models\Chat\ChatConversation;
use App\Models\MarketplaceAdmin;
use App\Models\SupportDepartment;
use App\Models\TeamChat\TeamMessage;
use App\Models\TeamChat\TeamMessageAttachment;
use App\Models\TeamChat\TeamThread;
use App\Models\TeamChat\TeamThreadMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Chatul intern al echipei: conversații 1:1, grupuri (inclusiv „Toată echipa” și câte unul
 * per departament de suport) și fire „Cere ajutor” legate de un chat cu un client.
 */
class TeamChatService
{
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    public static function isAvailable(): bool
    {
        return Schema::hasTable('team_threads');
    }

    // ------------------------------------------------------------ membri

    /**
     * Colegii activi din același marketplace.
     */
    public function colleagues(MarketplaceAdmin $admin): Collection
    {
        return MarketplaceAdmin::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('status', 'active')
            ->where('name', 'not like', '%(System)')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    /**
     * Grupurile implicite: „Toată echipa” (toți operatorii activi) și câte un grup per
     * departament de suport (membrii departamentului). Idempotent, rulat la fiecare listare.
     */
    public function ensureDefaultGroups(MarketplaceAdmin $admin): void
    {
        $clientId = (int) $admin->marketplace_client_id;
        $colleagueIds = $this->colleagues($admin)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ((int) $admin->id && ! in_array((int) $admin->id, $colleagueIds, true)) {
            $colleagueIds[] = (int) $admin->id;
        }

        $team = $this->systemThread($clientId, 'team', TeamThread::TYPE_GROUP, 'Toată echipa');
        $this->syncMembers($team, $colleagueIds);

        if (! Schema::hasTable('support_departments') || ! Schema::hasTable('support_department_admins')) {
            return;
        }

        $departments = SupportDepartment::query()->where('marketplace_client_id', $clientId)->get(['id', 'name']);
        foreach ($departments as $dept) {
            $name = $dept->name;
            if (is_array($name)) {
                $name = $name['ro'] ?? $name['en'] ?? reset($name);
            }
            $thread = $this->systemThread($clientId, 'dept:' . $dept->id, TeamThread::TYPE_GROUP, (string) $name, (int) $dept->id);

            $memberIds = DB::table('support_department_admins')
                ->where('support_department_id', $dept->id)
                ->pluck('marketplace_admin_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $colleagueIds, true))
                ->values()
                ->all();
            $this->syncMembers($thread, $memberIds);
        }
    }

    private function systemThread(int $clientId, string $key, string $type, string $name, ?int $departmentId = null): TeamThread
    {
        $thread = TeamThread::query()->firstOrCreate(
            ['marketplace_client_id' => $clientId, 'system_key' => $key],
            ['type' => $type, 'name' => $name, 'is_system' => true, 'support_department_id' => $departmentId]
        );

        if ($thread->name !== $name && $type === TeamThread::TYPE_GROUP) {
            $thread->forceFill(['name' => $name])->save();
        }

        return $thread;
    }

    /**
     * Membrii grupurilor de sistem urmăresc lista reală (cine pleacă din departament iese din grup).
     */
    private function syncMembers(TeamThread $thread, array $adminIds): void
    {
        $current = TeamThreadMember::query()->where('team_thread_id', $thread->id)->pluck('marketplace_admin_id')->map(fn ($id) => (int) $id)->all();

        $add = array_diff($adminIds, $current);
        if ($add) {
            $lastId = (int) TeamMessage::query()->where('team_thread_id', $thread->id)->max('id');
            DB::table('team_thread_members')->insertOrIgnore(array_map(fn ($id) => [
                'team_thread_id' => $thread->id,
                'marketplace_admin_id' => $id,
                'last_read_message_id' => $lastId, // nu intră cu tot istoricul necitit
                'joined_at' => now(),
            ], array_values($add)));
        }

        $remove = array_diff($current, $adminIds);
        if ($remove) {
            TeamThreadMember::query()->where('team_thread_id', $thread->id)->whereIn('marketplace_admin_id', $remove)->delete();
        }
    }

    // ------------------------------------------------------------ conversații

    public function threadsFor(MarketplaceAdmin $admin): array
    {
        $this->ensureDefaultGroups($admin);

        $memberships = TeamThreadMember::query()
            ->where('marketplace_admin_id', $admin->id)
            ->with('thread')
            ->get()
            ->filter(fn ($m) => $m->thread && (int) $m->thread->marketplace_client_id === (int) $admin->marketplace_client_id);

        $threadIds = $memberships->pluck('team_thread_id')->all();
        if (! $threadIds) {
            return [];
        }

        // Ultimul mesaj din fiecare conversație.
        $lastMessages = TeamMessage::query()
            ->whereIn('id', TeamMessage::query()->selectRaw('MAX(id)')->whereIn('team_thread_id', $threadIds)->groupBy('team_thread_id'))
            ->with('author:id,name')
            ->get()
            ->keyBy('team_thread_id');

        // Partenerii din conversațiile 1:1.
        $dmPartners = TeamThreadMember::query()
            ->whereIn('team_thread_id', $threadIds)
            ->where('marketplace_admin_id', '!=', $admin->id)
            ->with('admin:id,name')
            ->get()
            ->groupBy('team_thread_id');

        $result = [];
        foreach ($memberships as $m) {
            $t = $m->thread;
            $unread = (int) TeamMessage::query()
                ->where('team_thread_id', $t->id)
                ->where('id', '>', (int) $m->last_read_message_id)
                ->where(fn ($q) => $q->whereNull('marketplace_admin_id')->orWhere('marketplace_admin_id', '!=', $admin->id))
                ->count();

            $mentioned = $unread > 0 && TeamMessage::query()
                ->where('team_thread_id', $t->id)
                ->where('id', '>', (int) $m->last_read_message_id)
                ->whereJsonContains('mentions', (int) $admin->id)
                ->exists();

            $partner = null;
            if ($t->type === TeamThread::TYPE_DM) {
                $partner = optional($dmPartners->get($t->id))->first()?->admin;
            }

            $last = $lastMessages->get($t->id);
            $result[] = [
                'id' => (int) $t->id,
                'type' => $t->type,
                'name' => $this->threadName($t, $partner),
                'initials' => $this->initials($this->threadName($t, $partner)),
                'is_system' => (bool) $t->is_system,
                'partner_id' => $partner ? (int) $partner->id : null,
                'members' => $t->type === TeamThread::TYPE_DM ? 2 : (int) TeamThreadMember::query()->where('team_thread_id', $t->id)->count(),
                'unread' => $unread,
                'mentioned' => $mentioned,
                'muted' => $m->muted_until && $m->muted_until->isFuture(),
                'last' => $last ? [
                    'author' => $last->marketplace_admin_id ? ($this->cleanName($last->author?->name) ?: 'Coleg') : null,
                    'mine' => (int) $last->marketplace_admin_id === (int) $admin->id,
                    'text' => $last->trashed() ? 'mesaj șters' : mb_strimwidth((string) ($last->body ?: 'atașament'), 0, 80, '…'),
                ] : null,
                'last_at' => optional($t->last_message_at ?? $t->created_at)->toIso8601String(),
                'chat_ref' => $t->chat_conversation_id ? ChatConversation::query()->whereKey($t->chat_conversation_id)->value('reference') : null,
            ];
        }

        usort($result, fn ($a, $b) => strcmp((string) $b['last_at'], (string) $a['last_at']));

        return $result;
    }

    public function threadFor(MarketplaceAdmin $admin, int $threadId): ?TeamThread
    {
        return TeamThread::query()
            ->whereKey($threadId)
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->whereHas('members', fn ($q) => $q->where('marketplace_admin_id', $admin->id))
            ->first();
    }

    public function openDm(MarketplaceAdmin $admin, int $otherId): TeamThread
    {
        $other = MarketplaceAdmin::query()
            ->whereKey($otherId)
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->firstOrFail();

        $ids = [(int) $admin->id, (int) $other->id];
        sort($ids);
        $key = 'dm:' . implode(':', $ids);

        $thread = TeamThread::query()->firstOrCreate(
            ['marketplace_client_id' => $admin->marketplace_client_id, 'system_key' => $key],
            ['type' => TeamThread::TYPE_DM, 'is_system' => false, 'created_by_marketplace_admin_id' => $admin->id]
        );
        $this->addMembers($thread, $ids);

        return $thread;
    }

    public function createGroup(MarketplaceAdmin $admin, string $name, array $memberIds): TeamThread
    {
        $name = trim(mb_substr($name, 0, 80));
        if ($name === '') {
            throw new \InvalidArgumentException('Dă un nume grupului.');
        }

        $ids = $this->validColleagueIds($admin, $memberIds);
        $ids[] = (int) $admin->id;

        $thread = TeamThread::query()->create([
            'marketplace_client_id' => $admin->marketplace_client_id,
            'type' => TeamThread::TYPE_GROUP,
            'name' => $name,
            'is_system' => false,
            'created_by_marketplace_admin_id' => $admin->id,
        ]);
        $this->addMembers($thread, $ids);
        $this->systemMessage($thread, $this->cleanName($admin->name) . ' a creat grupul „' . $name . '”.');

        return $thread;
    }

    /**
     * „Cere ajutor” din fereastra unui chat cu un client: un fir intern legat de conversație.
     */
    public function askForHelp(MarketplaceAdmin $admin, string $conversationRef, array $memberIds, ?string $note = null): TeamThread
    {
        $conversation = ChatConversation::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('reference', $conversationRef)
            ->firstOrFail();

        $ids = $this->validColleagueIds($admin, $memberIds);
        if (! $ids) {
            throw new \InvalidArgumentException('Alege cel puțin un coleg.');
        }
        $ids[] = (int) $admin->id;

        $who = $conversation->guest_name ?: 'vizitator';
        $thread = TeamThread::query()->create([
            'marketplace_client_id' => $admin->marketplace_client_id,
            'type' => TeamThread::TYPE_HELP,
            'name' => 'Ajutor: ' . $conversation->reference . ' (' . $who . ')',
            'is_system' => false,
            'chat_conversation_id' => $conversation->id,
            'created_by_marketplace_admin_id' => $admin->id,
        ]);
        $this->addMembers($thread, $ids);

        $text = trim((string) $note) !== '' ? trim((string) $note) : 'Am nevoie de ajutor la conversația ' . $conversation->reference . '.';
        $this->post($admin, $thread, $text);

        return $thread;
    }

    public function addMembers(TeamThread $thread, array $adminIds): void
    {
        $lastId = (int) TeamMessage::query()->where('team_thread_id', $thread->id)->max('id');
        $rows = array_map(fn ($id) => [
            'team_thread_id' => $thread->id,
            'marketplace_admin_id' => (int) $id,
            'last_read_message_id' => $lastId,
            'joined_at' => now(),
        ], array_values(array_unique(array_map('intval', $adminIds))));

        if ($rows) {
            DB::table('team_thread_members')->insertOrIgnore($rows);
        }
    }

    public function addMembersBy(MarketplaceAdmin $admin, TeamThread $thread, array $memberIds): void
    {
        if ($thread->type !== TeamThread::TYPE_GROUP && $thread->type !== TeamThread::TYPE_HELP) {
            throw new \InvalidArgumentException('Într-o conversație 1:1 nu se pot adăuga membri.');
        }
        if ($thread->is_system) {
            throw new \InvalidArgumentException('Membrii acestui grup se stabilesc automat.');
        }

        $ids = $this->validColleagueIds($admin, $memberIds);
        $existing = TeamThreadMember::query()->where('team_thread_id', $thread->id)->pluck('marketplace_admin_id')->map(fn ($id) => (int) $id)->all();
        $new = array_values(array_diff($ids, $existing));
        if (! $new) {
            return;
        }

        $this->addMembers($thread, $new);
        $names = MarketplaceAdmin::query()->whereIn('id', $new)->pluck('name')->map(fn ($n) => $this->cleanName($n))->implode(', ');
        $this->systemMessage($thread, $this->cleanName($admin->name) . ' a adăugat: ' . $names . '.');
    }

    public function leave(MarketplaceAdmin $admin, TeamThread $thread): void
    {
        if ($thread->is_system || $thread->type === TeamThread::TYPE_DM) {
            throw new \InvalidArgumentException('Nu poți părăsi această conversație.');
        }

        TeamThreadMember::query()->where('team_thread_id', $thread->id)->where('marketplace_admin_id', $admin->id)->delete();
        $this->systemMessage($thread, $this->cleanName($admin->name) . ' a părăsit grupul.');
    }

    public function mute(MarketplaceAdmin $admin, TeamThread $thread, ?int $minutes): void
    {
        TeamThreadMember::query()
            ->where('team_thread_id', $thread->id)
            ->where('marketplace_admin_id', $admin->id)
            ->update(['muted_until' => $minutes ? now()->addMinutes($minutes) : null]);
    }

    // ------------------------------------------------------------ mesaje

    public function messages(MarketplaceAdmin $admin, TeamThread $thread, int $after = 0, int $before = 0): array
    {
        $query = TeamMessage::withTrashed()
            ->where('team_thread_id', $thread->id)
            ->with(['author:id,name', 'attachments']);

        if ($after > 0) {
            $rows = $query->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        } else {
            if ($before > 0) {
                $query->where('id', '<', $before);
            }
            $rows = $query->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }

        return $rows->map(fn (TeamMessage $m) => $this->present($admin, $m))->all();
    }

    public function post(MarketplaceAdmin $admin, TeamThread $thread, ?string $body, ?UploadedFile $file = null): TeamMessage
    {
        $body = $body !== null ? trim(mb_substr($body, 0, 5000)) : '';
        if ($body === '' && ! $file) {
            throw new \InvalidArgumentException('Mesajul e gol.');
        }

        $stored = $file ? $this->storeFile($thread, $file) : null;

        $message = DB::transaction(function () use ($admin, $thread, $body, $stored) {
            $message = TeamMessage::query()->create([
                'team_thread_id' => $thread->id,
                'marketplace_admin_id' => $admin->id,
                'body' => $body !== '' ? $body : null,
                'mentions' => $this->extractMentions($thread, $body) ?: null,
            ]);

            if ($stored) {
                $message->attachments()->create($stored);
            }

            $thread->forceFill(['last_message_at' => now()])->save();

            TeamThreadMember::query()
                ->where('team_thread_id', $thread->id)
                ->where('marketplace_admin_id', $admin->id)
                ->update(['last_read_message_id' => $message->id]);

            return $message;
        });

        $this->broadcastMessage($thread, $message, 'new');

        return $message;
    }

    public function edit(MarketplaceAdmin $admin, TeamMessage $message, string $body): void
    {
        $this->assertOwn($admin, $message);
        if ($message->created_at && $message->created_at->lt(now()->subMinutes(TeamMessage::EDIT_WINDOW_MINUTES))) {
            throw new \InvalidArgumentException('Mesajul se poate edita doar ' . TeamMessage::EDIT_WINDOW_MINUTES . ' minute după trimitere.');
        }

        $body = trim(mb_substr($body, 0, 5000));
        if ($body === '' && $message->attachments()->doesntExist()) {
            throw new \InvalidArgumentException('Mesajul e gol.');
        }

        $message->forceFill([
            'body' => $body !== '' ? $body : null,
            'mentions' => $this->extractMentions($message->thread, $body) ?: null,
            'edited_at' => now(),
        ])->save();

        $this->broadcastMessage($message->thread, $message, 'edited');
    }

    public function delete(MarketplaceAdmin $admin, TeamMessage $message): void
    {
        $this->assertOwn($admin, $message);
        $message->delete();
        $this->broadcastMessage($message->thread, $message, 'deleted');
    }

    public function markRead(MarketplaceAdmin $admin, TeamThread $thread, int $messageId): void
    {
        TeamThreadMember::query()
            ->where('team_thread_id', $thread->id)
            ->where('marketplace_admin_id', $admin->id)
            ->where('last_read_message_id', '<', $messageId)
            ->update(['last_read_message_id' => $messageId]);
    }

    public function keepAttachment(MarketplaceAdmin $admin, TeamMessageAttachment $attachment, bool $keep): void
    {
        $attachment->forceFill(['keep' => $keep])->save();
    }

    public function typing(MarketplaceAdmin $admin, TeamThread $thread): void
    {
        $this->broadcastToMembers($thread, 'team.typing', [
            'thread_id' => (int) $thread->id,
            'admin_id' => (int) $admin->id,
            'name' => $this->cleanName($admin->name),
        ], (int) $admin->id);
    }

    public function systemMessage(TeamThread $thread, string $text): void
    {
        $message = TeamMessage::query()->create([
            'team_thread_id' => $thread->id,
            'marketplace_admin_id' => null,
            'body' => $text,
        ]);
        $thread->forceFill(['last_message_at' => now()])->save();
        $this->broadcastMessage($thread, $message, 'new');
    }

    // ------------------------------------------------------------ prezentare

    public function present(MarketplaceAdmin $admin, TeamMessage $m): array
    {
        $deleted = $m->trashed();

        return [
            'id' => (int) $m->id,
            'thread_id' => (int) $m->team_thread_id,
            'system' => $m->marketplace_admin_id === null,
            'mine' => (int) $m->marketplace_admin_id === (int) $admin->id,
            'author_id' => $m->marketplace_admin_id ? (int) $m->marketplace_admin_id : null,
            'author' => $m->marketplace_admin_id ? ($this->cleanName($m->author?->name) ?: 'Coleg') : null,
            'body' => $deleted ? null : $m->body,
            'deleted' => $deleted,
            'edited' => (bool) $m->edited_at,
            'editable' => ! $deleted && (int) $m->marketplace_admin_id === (int) $admin->id
                && $m->created_at && $m->created_at->gte(now()->subMinutes(TeamMessage::EDIT_WINDOW_MINUTES)),
            'mentions_me' => in_array((int) $admin->id, array_map('intval', (array) $m->mentions), true),
            'time' => optional($m->created_at)->format('H:i'),
            'date' => optional($m->created_at)->format('d.m.Y'),
            'attachments' => $deleted ? [] : $m->attachments->map(fn (TeamMessageAttachment $a) => [
                'id' => (int) $a->id,
                'name' => $a->name,
                'image' => str_starts_with($a->mime, 'image/'),
                'size' => (int) $a->size,
                'keep' => (bool) $a->keep,
                'purged' => (bool) $a->purged_at,
                'expires' => optional($a->expiresAt())->format('d.m.Y'),
                'url' => $a->purged_at ? null : '/marketplace/team-chat/attachments/' . $a->id,
            ])->values()->all(),
        ];
    }

    public function cleanName(?string $name): string
    {
        return trim(preg_replace('/\s*\(System\)$/', '', (string) $name));
    }

    // ------------------------------------------------------------ intern

    private function threadName(TeamThread $thread, ?MarketplaceAdmin $partner): string
    {
        if ($thread->type === TeamThread::TYPE_DM) {
            return $partner ? $this->cleanName($partner->name) : 'Conversație';
        }

        return (string) ($thread->name ?: 'Grup');
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $p) {
            if ($p !== '' && preg_match('/\p{L}|\p{N}/u', $p)) {
                $letters .= mb_strtoupper(mb_substr($p, 0, 1));
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters ?: '#';
    }

    private function validColleagueIds(MarketplaceAdmin $admin, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }

        return MarketplaceAdmin::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @Nume Prenume → id-urile membrilor conversației al căror nume apare după @.
     */
    private function extractMentions(TeamThread $thread, string $body): array
    {
        if ($body === '' || ! str_contains($body, '@')) {
            return [];
        }

        $members = MarketplaceAdmin::query()
            ->whereIn('id', TeamThreadMember::query()->where('team_thread_id', $thread->id)->select('marketplace_admin_id'))
            ->get(['id', 'name']);

        $hay = mb_strtolower($body);
        $ids = [];
        foreach ($members as $m) {
            $name = mb_strtolower($this->cleanName($m->name));
            $first = explode(' ', $name)[0] ?? '';
            if (($name !== '' && str_contains($hay, '@' . $name)) || ($first !== '' && preg_match('/@' . preg_quote($first, '/') . '\b/u', $hay))) {
                $ids[] = (int) $m->id;
            }
        }
        if (str_contains($hay, '@toti') || str_contains($hay, '@toți') || str_contains($hay, '@all')) {
            $ids = array_merge($ids, $members->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($ids));
    }

    private function storeFile(TeamThread $thread, UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Fișierul nu s-a încărcat.');
        }
        if ($file->getSize() > self::MAX_ATTACHMENT_BYTES) {
            throw new \InvalidArgumentException('Fișierul depășește 10 MB.');
        }

        // Tipul real, din conținut; nu ne bazăm pe ce declară browserul.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $ext = self::ALLOWED_MIME[$mime] ?? null;
        if (! $ext) {
            throw new \InvalidArgumentException('Se pot trimite doar imagini (JPG, PNG, WEBP, GIF) și PDF.');
        }

        $path = 'team-chat/' . $thread->marketplace_client_id . '/' . now()->format('Y/m') . '/' . Str::random(40) . '.' . $ext;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $name = preg_replace('/[^\pL\pN ._()-]+/u', '_', (string) $file->getClientOriginalName()) ?: ('fisier.' . $ext);

        return [
            'path' => $path,
            'name' => mb_substr($name, 0, 180),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ];
    }

    private function assertOwn(MarketplaceAdmin $admin, TeamMessage $message): void
    {
        if ((int) $message->marketplace_admin_id !== (int) $admin->id) {
            throw new \InvalidArgumentException('Poți modifica doar mesajele tale.');
        }
    }

    private function broadcastMessage(TeamThread $thread, TeamMessage $message, string $action): void
    {
        $this->broadcastToMembers($thread, 'team.message', [
            'thread_id' => (int) $thread->id,
            'message_id' => (int) $message->id,
            'action' => $action,
            'author_id' => $message->marketplace_admin_id ? (int) $message->marketplace_admin_id : null,
            'mentions' => array_map('intval', (array) $message->mentions),
            'type' => $thread->type,
        ]);
    }

    /**
     * Fiecare operator ascultă pe canalul lui (team-user.{id}); un singur apel către Reverb.
     */
    private function broadcastToMembers(TeamThread $thread, string $event, array $payload, ?int $exceptAdminId = null): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }

        try {
            $channels = TeamThreadMember::query()
                ->where('team_thread_id', $thread->id)
                ->when($exceptAdminId, fn ($q) => $q->where('marketplace_admin_id', '!=', $exceptAdminId))
                ->pluck('marketplace_admin_id')
                ->map(fn ($id) => new \Illuminate\Broadcasting\PrivateChannel('team-user.' . $id))
                ->all();

            if (! $channels) {
                return;
            }

            $send = function () use ($channels, $event, $payload) {
                try {
                    foreach (array_chunk($channels, 90) as $chunk) {
                        Broadcast::on($chunk)->as($event)->with($payload)->sendNow();
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            };

            app()->runningInConsole() ? $send() : \Illuminate\Support\defer($send);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
