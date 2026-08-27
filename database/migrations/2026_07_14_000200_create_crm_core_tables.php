<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id')->unique();
            $table->string('company_name');
            $table->string('brand_name')->nullable();
            $table->string('contact_name');
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('source')->default('other');
            $table->string('business_type')->nullable();
            $table->string('product_interest')->nullable();
            $table->unsignedBigInteger('estimated_need')->nullable();
            $table->string('estimated_need_unit', 30)->default('pcs');
            $table->text('notes')->nullable();
            $table->enum('status', ['leads_adds', 'cold_lead', 'warm_lead', 'leads_hold', 'leads_risky', 'converted'])->default('cold_lead')->index();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->dateTime('last_activity_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['owner_id', 'status'], 'leads_owner_status_idx');
            $table->index(['area_id', 'status'], 'leads_area_status_idx');
            $table->index(['business_unit_id', 'status'], 'leads_business_unit_status_idx');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique();
            $table->foreignId('converted_from_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('company_name');
            $table->string('brand_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('npwp')->nullable();
            $table->text('address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('business_type')->nullable();
            $table->string('product_interest')->nullable();
            $table->unsignedBigInteger('estimated_need')->nullable();
            $table->string('estimated_need_unit', 30)->default('pcs');
            $table->enum('status', ['pareto', 'active', 'inactive', 'risky'])->default('active')->index();
            $table->decimal('credit_limit', 16, 2)->nullable();
            $table->unsignedInteger('payment_term_days')->nullable();
            $table->decimal('estimated_monthly_purchase', 16, 2)->nullable();
            $table->json('tags')->nullable();
            $table->dateTime('last_order_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['sales_owner_id', 'status'], 'customers_sales_owner_status_idx');
            $table->index(['area_id', 'status'], 'customers_area_status_idx');
            $table->index(['business_unit_id', 'status'], 'customers_business_unit_status_idx');
        });

        Schema::create('customer_user', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('responsibility')->default('pic');
            $table->timestamps();
            $table->primary(['customer_id', 'user_id']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->default('pcs');
            $table->decimal('base_price', 16, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['products', 'contacts', 'customer_user', 'customers', 'leads'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
