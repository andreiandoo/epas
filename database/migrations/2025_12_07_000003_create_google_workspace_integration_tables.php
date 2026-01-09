<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_workspace_connections')) {
            return;
        }

        Schema::create('google_workspace_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('google_user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('enabled_services')->nullable(); // drive, calendar, gmail
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'google_user_id']);
        });

        if (Schema::hasTable('google_drive_files')) {
            return;
        }

        Schema::create('google_drive_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('file_id');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('web_view_link')->nullable();
            $table->string('parent_folder_id')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('google_workspace_connections')->onDelete('cascade');
        });

        if (Schema::hasTable('google_calendar_events')) {
            return;
        }

        Schema::create('google_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('event_id');
            $table->string('calendar_id')->default('primary');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->boolean('is_all_day')->default(false);
            $table->json('attendees')->nullable();
            $table->string('status')->default('confirmed');
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('google_workspace_connections')->onDelete('cascade');
        });

        if (Schema::hasTable('google_gmail_messages')) {
            return;
        }

        Schema::create('google_gmail_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('message_id')->nullable();
            $table->string('thread_id')->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_html')->default(false);
            $table->json('attachments')->nullable();
            $table->string('status')->default('pending');
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('google_workspace_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_gmail_messages');
        Schema::dropIfExists('google_calendar_events');
        Schema::dropIfExists('google_drive_files');
        Schema::dropIfExists('google_workspace_connections');
    }
};
