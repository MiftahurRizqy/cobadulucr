<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('is_large_account'));
        Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->dropColumn('large_account_target'));
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->boolean('is_large_account')->default(false)->after('needs_custom'));
        Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->unsignedInteger('large_account_target')->default(0)->after('custom_noo_target'));
    }
};
