<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipelines', fn (Blueprint $table) => $table->boolean('counts_as_custom_noo')->default(false)->after('business_type')->index());
        DB::table('pipelines')->where(fn ($query) => $query->where('name', 'like', '%custom%')->orWhere('name', 'like', '%sablon%'))->update(['counts_as_custom_noo' => true]);
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('needs_custom'));
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->boolean('needs_custom')->default(false)->after('business_type'));
        Schema::table('pipelines', fn (Blueprint $table) => $table->dropColumn('counts_as_custom_noo'));
    }
};
