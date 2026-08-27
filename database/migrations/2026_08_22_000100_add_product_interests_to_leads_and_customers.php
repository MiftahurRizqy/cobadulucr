<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->json('product_interests')->nullable()->after('product_interest'));
        Schema::table('customers', fn (Blueprint $table) => $table->json('product_interests')->nullable()->after('product_interest'));

        foreach (['leads', 'customers'] as $table) {
            DB::table($table)
                ->whereNotNull('product_interest')
                ->orderBy('id')
                ->eachById(function ($record) use ($table) {
                    DB::table($table)->where('id', $record->id)->update([
                        'product_interests' => json_encode([[
                            'product_name' => $record->product_interest,
                            'estimated_need' => $record->estimated_need,
                            'estimated_need_unit' => $record->estimated_need_unit ?: 'pcs',
                        ]]),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('product_interests'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('product_interests'));
    }
};
