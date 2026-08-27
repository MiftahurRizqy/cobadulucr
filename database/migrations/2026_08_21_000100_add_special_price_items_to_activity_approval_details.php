<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_approval_details', 'special_price_items')) {
            Schema::table('activity_approval_details', function (Blueprint $table) {
                $table->json('special_price_items')->nullable()->after('decision_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_approval_details', 'special_price_items')) {
            Schema::table('activity_approval_details', fn (Blueprint $table) => $table->dropColumn('special_price_items'));
        }
    }
};
