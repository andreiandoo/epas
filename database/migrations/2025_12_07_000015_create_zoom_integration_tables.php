<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zoom OAuth connections
        Schema::create('zoom_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('account_id');
            $table->string('user_id'); // Zoom user ID
            $table->string('email');
            $table->string('display_name')->nullable();
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token'); // Encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('account_type')->nullable(); // Basic, Pro, Business, Enterprise
            $table->string('status')->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id']);
        });

        // Zoom meetings (scheduled or instant)
        Schema::create('zoom_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('meeting_id'); // Zoom meeting ID
            $table->string('uuid')->nullable(); // Unique meeting instance ID
            $table->string('host_id');
            $table->string('topic');
            $table->text('agenda')->nullable();
            $table->integer('type'); // 1=instant, 2=scheduled, 3=recurring no fixed, 8=recurring fixed
            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable(); // minutes
            $table->string('timezone')->nullable();
            $table->string('join_url')->nullable();
            $table->string('start_url')->nullable(); // Host start URL
            $table->string('password')->nullable();
            $table->string('status')->default('waiting'); // waiting, started, finished
            $table->json('settings')->nullable(); // Waiting room, join before host, etc.
            $table->string('correlation_type')->nullable(); // events, webinars
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zoom_connections')->onDelete('cascade');
            $table->index(['meeting_id']);
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Zoom webinars
        Schema::create('zoom_webinars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('webinar_id');
            $table->string('uuid')->nullable();
            $table->string('host_id');
            $table->string('topic');
            $table->text('agenda')->nullable();
            $table->integer('type'); // 5=webinar, 6=recurring no fixed, 9=recurring fixed
            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable();
            $table->string('timezone')->nullable();
            $table->string('join_url')->nullable();
            $table->string('registration_url')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('waiting');
            $table->json('settings')->nullable();
            $table->string('correlation_type')->nullable();
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zoom_connections')->onDelete('cascade');
            $table->index(['webinar_id']);
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Meeting/Webinar registrants and participants
        Schema::create('zoom_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('participant_type'); // meeting, webinar
            $table->string('meeting_id'); // meeting_id or webinar_id
            $table->string('registrant_id')->nullable();
            $table->string('participant_id')->nullable();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('status')->default('registered'); // registered, approved, pending, denied, attended
            $table->string('join_url')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_seconds')->nullable(); // Time spent in meeting
            $table->string('local_type')->nullable(); // customers, attendees
            $table->unsignedBigInteger('local_id')->nullable();
            $table->json('custom_questions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zoom_connections')->onDelete('cascade');
            $table->index(['participant_type', 'meeting_id']);
            $table->index(['local_type', 'local_id']);
        });

        // Meeting recordings
        Schema::create('zoom_recordings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->string('recording_id');
            $table->string('meeting_uuid');
            $table->string('recording_type'); // shared_screen_with_speaker_view, audio_only, etc.
            $table->string('file_type'); // MP4, M4A, TRANSCRIPT, etc.
            $table->bigInteger('file_size')->nullable();
            $table->string('download_url')->nullable();
            $table->string('play_url')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('available'); // available, deleted
            $table->timestamp('recording_start')->nullable();
            $table->timestamp('recording_end')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zoom_connections')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('zoom_meetings')->onDelete('set null');
        });

        // Zoom webhooks
        Schema::create('zoom_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->string('event_type'); // meeting.started, meeting.ended, webinar.registration_created
            $table->string('event_ts')->nullable();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('zoom_connections')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_webhook_events');
        Schema::dropIfExists('zoom_recordings');
        Schema::dropIfExists('zoom_participants');
        Schema::dropIfExists('zoom_webinars');
        Schema::dropIfExists('zoom_meetings');
        Schema::dropIfExists('zoom_connections');
    }
};
