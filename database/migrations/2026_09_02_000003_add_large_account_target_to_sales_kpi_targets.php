<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_kpi_targets', function (Blueprint $table) {
            $table->unsignedInteger('large_account_target')->default(6)->after('custom_noo_target');
        });

        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->unsignedInteger('large_account_target')->default(6)->after('custom_noo_target');
        });
    }

    public function down(): void
    {
        Schema::table('sales_kpi_targets', function (Blueprint $table) {
            $table->dropColumn('large_account_target');
        });

        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn('large_account_target');
        });
    }
};
