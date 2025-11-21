<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer segments
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions'); // Segment rules
            $table->boolean('is_dynamic')->default(true); // Auto-update membership
            $table->integer('member_count')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_dynamic']);
        });

        // Customer segment membership
        Schema::create('customer_segment_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('customer_segments')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->timestamp('added_at');
            $table->timestamps();

            $table->unique(['segment_id', 'customer_id']);
        });

        // Email campaigns
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->onDelete('set null');
            $table->string('name');
            $table->string('subject');
            $table->text('content');
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'paused'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // Campaign recipients
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->enum('status', ['pending', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        // Automation workflows
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type'); // purchase, signup, event_day, custom
            $table->json('trigger_conditions')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('enrolled_count')->default(0);
            $table->integer('completed_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Workflow steps
        Schema::create('automation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->onDelete('cascade');
            $table->integer('order');
            $table->string('type'); // email, wait, condition, action
            $table->json('config'); // Step configuration
            $table->timestamps();

            $table->index(['workflow_id', 'order']);
        });

        // Customer journey through workflow
        Schema::create('automation_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('current_step_id')->nullable()->constrained('automation_steps')->onDelete('set null');
            $table->enum('status', ['active', 'completed', 'cancelled', 'paused'])->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
        });

        // Customer notes and activities
        Schema::create('customer_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type'); // note, email, call, meeting, purchase
            $table->text('content')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activities');
        Schema::dropIfExists('automation_enrollments');
        Schema::dropIfExists('automation_steps');
        Schema::dropIfExists('automation_workflows');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('customer_segment_members');
        Schema::dropIfExists('customer_segments');
    }
};
