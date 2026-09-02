<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pipelines', fn (Blueprint $table) => $table->boolean('uses_pipeline_for_custom_progress')->default(false)->after('counts_as_custom_noo'));
        DB::table('pipelines')->where('counts_as_custom_noo', true)->update(['uses_pipeline_for_custom_progress' => true]);
    }
    public function down(): void { Schema::table('pipelines', fn (Blueprint $table) => $table->dropColumn('uses_pipeline_for_custom_progress')); }
};
