<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales_kpi_targets', 'evaluation_status')) {
            Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->dropColumn('evaluation_status'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales_kpi_targets', 'evaluation_status')) {
            Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->enum('evaluation_status', ['on_track', 'attention', 'coaching'])->default('on_track')->after('sales_target'));
        }
    }
};
