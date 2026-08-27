<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('access_level', ['owner', 'editor', 'contributor', 'commenter', 'viewer'])->default('viewer');
            $table->json('visible_fields')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_room_id', 'user_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('summary');
            $table->text('detail')->nullable();
            $table->text('result')->nullable();
            $table->text('next_action')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->timestamp('follow_up_completed_at')->nullable()->index();
            $table->foreignId('follow_up_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('follow_up_completion_activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->json('participants')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at'], 'activities_user_occurred_idx');
            $table->index(['customer_id', 'occurred_at'], 'activities_customer_occurred_idx');
            $table->index(['type', 'occurred_at'], 'activities_type_occurred_idx');
        });

        Schema::create('activity_approval_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('product_name')->nullable();
            $table->decimal('normal_price', 16, 2)->nullable();
            $table->decimal('requested_price', 16, 2)->nullable();
            $table->decimal('quantity', 16, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->text('reason')->nullable();
            $table->string('po_number')->nullable();
            $table->decimal('new_order_value', 16, 2)->nullable();
            $table->decimal('current_limit', 16, 2)->nullable();
            $table->decimal('requested_limit', 16, 2)->nullable();
            $table->decimal('outstanding_receivables', 16, 2)->nullable();
            $table->decimal('remaining_limit', 16, 2)->nullable();
            $table->decimal('over_limit', 16, 2)->nullable();
            $table->date('planned_payment_date')->nullable();
            $table->decimal('planned_payment_amount', 16, 2)->nullable();
            $table->unsignedInteger('current_days')->nullable();
            $table->unsignedInteger('requested_days')->nullable();
            $table->unsignedInteger('additional_days')->nullable();
            $table->decimal('transaction_value', 16, 2)->nullable();
            $table->string('recipient')->nullable();
            $table->text('purpose')->nullable();
            $table->string('order_number')->nullable();
            $table->string('condition')->nullable();
            $table->string('support_type')->nullable();
            $table->decimal('budget_amount', 16, 2)->nullable();
            $table->string('period')->nullable();
            $table->text('objective')->nullable();
            $table->string('need_name')->nullable();
            $table->date('needed_at')->nullable();
            $table->string('project_name')->nullable();
            $table->decimal('estimated_value', 16, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->text('customer_need')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'revision', 'rejected'])->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('special_price_items')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable()->index();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['todo', 'in_progress', 'review', 'done', 'blocked', 'cancelled'])->default('todo')->index();
            $table->json('checklist')->nullable();
            $table->text('completion_note')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_role')->default('assignee');
            $table->timestamps();
            $table->primary(['task_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('mentioned_user_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('sha256', 64)->nullable()->index();
            $table->dateTime('captured_at')->nullable();
            $table->dateTime('client_modified_at')->nullable();
            $table->string('verification_status', 30)->default('unavailable')->index();
            $table->json('evidence_metadata')->nullable();
            $table->json('verification_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approval_id')->unique();
            $table->string('type')->index();
            $table->string('title');
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('current_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('previous_value', 16, 2)->nullable();
            $table->decimal('requested_value', 16, 2)->nullable();
            $table->text('reason');
            $table->enum('status', ['draft', 'submitted', 'waiting', 'approved', 'rejected', 'revision', 'cancelled'])->default('draft')->index();
            $table->text('decision_note')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->unsignedInteger('position')->default(1);
            $table->enum('status', ['waiting', 'approved', 'rejected', 'revision'])->default('waiting');
            $table->text('note')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('url')->nullable();
            $table->json('data')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at'], 'notifications_user_read_created_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('module')->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notifications', 'approval_steps', 'approvals', 'attachments', 'comments', 'task_user', 'tasks', 'activity_approval_details', 'activities', 'room_members', 'customer_rooms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
