<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->string('custom_stage', 40)->nullable()->after('custom_specification')->index();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->dropIndex(['custom_stage']);
            $table->dropColumn('custom_stage');
        });
    }
};
