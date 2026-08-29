<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dateTime('became_customer_at')->nullable()->after('converted_from_lead_id')->index();
            $table->boolean('needs_custom')->default(false)->after('business_type');
            $table->boolean('is_large_account')->default(false)->after('needs_custom');
        });
        DB::table('customers')->whereNull('became_customer_at')->update(['became_customer_at' => DB::raw('created_at')]);

        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->string('market_segment', 20)->nullable()->after('product_name')->index();
        });

        Schema::table('sales_kpi_targets', function (Blueprint $table) {
            $table->unsignedInteger('noo_target')->default(0)->after('sales_target');
            $table->unsignedInteger('custom_noo_target')->default(0)->after('noo_target');
            $table->unsignedInteger('large_account_target')->default(0)->after('custom_noo_target');
            $table->unsignedBigInteger('drink_volume_target')->default(0)->after('large_account_target');
            $table->unsignedBigInteger('food_volume_target')->default(0)->after('drink_volume_target');
        });
    }

    public function down(): void
    {
        Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->dropColumn(['noo_target', 'custom_noo_target', 'large_account_target', 'drink_volume_target', 'food_volume_target']));
        Schema::table('opportunity_items', fn (Blueprint $table) => $table->dropColumn('market_segment'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['became_customer_at', 'needs_custom', 'is_large_account']));
    }
};
