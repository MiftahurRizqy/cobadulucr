<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->string('product_type', 20)->default('regular')->after('market_segment')->index();
            $table->text('custom_specification')->nullable()->after('product_type');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropColumn(['product_type', 'custom_specification']);
        });
    }
};
