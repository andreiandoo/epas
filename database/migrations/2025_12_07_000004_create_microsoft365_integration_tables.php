<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microsoft365_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('microsoft_user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('enabled_services')->nullable(); // onedrive, outlook, teams, calendar
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'microsoft_user_id']);
        });

        Schema::create('microsoft_onedrive_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('item_id');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('web_url')->nullable();
            $table->string('parent_reference')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('microsoft365_connections')->onDelete('cascade');
        });

        Schema::create('microsoft_outlook_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('message_id')->nullable();
            $table->string('conversation_id')->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->text('body');
            $table->string('body_type')->default('html');
            $table->json('attachments')->nullable();
            $table->string('status')->default('pending');
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('microsoft365_connections')->onDelete('cascade');
        });

        Schema::create('microsoft_teams_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('team_id');
            $table->string('channel_id');
            $table->string('message_id')->nullable();
            $table->text('content');
            $table->string('content_type')->default('html');
            $table->string('status')->default('pending');
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('microsoft365_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microsoft_teams_messages');
        Schema::dropIfExists('microsoft_outlook_messages');
        Schema::dropIfExists('microsoft_onedrive_files');
        Schema::dropIfExists('microsoft365_connections');
    }
};
