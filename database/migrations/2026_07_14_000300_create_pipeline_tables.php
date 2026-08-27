<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->string('color')->default('#6366f1');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->unsignedInteger('sla_days')->nullable();
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['pipeline_id', 'slug']);
        });

        Schema::create('stage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_stage_id')->constrained()->cascadeOnDelete();
            $table->enum('rule_type', ['field', 'note', 'file', 'task', 'approval', 'follow_up', 'reason'])->index();
            $table->string('field_key')->nullable();
            $table->string('label');
            $table->json('configuration')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_id')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->json('participants')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('product_name')->nullable();
            $table->decimal('estimated_quantity', 16, 2)->nullable();
            $table->string('quantity_unit', 30)->nullable();
            $table->decimal('estimated_value', 16, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->decimal('target_price', 16, 2)->nullable();
            $table->decimal('offered_price', 16, 2)->nullable();
            $table->string('current_supplier')->nullable();
            $table->string('competitor')->nullable();
            $table->date('expected_close_date')->nullable()->index();
            $table->text('next_action')->nullable();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->string('lead_source')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->index();
            $table->enum('status', ['open', 'won', 'lost'])->default('open')->index();
            $table->string('lost_reason')->nullable();
            $table->text('lost_reason_detail')->nullable();
            $table->dateTime('stage_entered_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['pipeline_id', 'pipeline_stage_id', 'status'], 'opportunities_pipeline_stage_status_idx');
            $table->index(['owner_id', 'status'], 'opportunities_owner_status_idx');
            $table->index(['customer_id', 'status'], 'opportunities_customer_status_idx');
        });

        Schema::create('opportunity_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('photo_path')->nullable();
            $table->unsignedBigInteger('quantity')->default(0);
            $table->string('quantity_unit', 30)->default('pcs');
            $table->decimal('target_price', 16, 2)->nullable();
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->string('deal_status', 20)->default('on_process')->index();
            $table->foreignId('deal_status_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deal_status_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunity_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->json('validation_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['opportunity_stage_histories', 'opportunity_items', 'opportunities', 'stage_rules', 'pipeline_stages', 'pipelines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
