<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kpi_metrics')) Schema::create('kpi_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('source', 40);
            $table->json('filters')->nullable();
            $table->string('unit', 20)->default('count');
            $table->unsignedBigInteger('threshold')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('counts_in_achievement')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('legacy_key', 30)->nullable()->unique();
            $table->timestamps();
        });

        if (! Schema::hasColumn('sales_kpi_targets', 'metric_targets')) Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->json('metric_targets')->nullable()->after('food_volume_target'));
        if (! Schema::hasColumn('kpi_templates', 'metric_targets')) Schema::table('kpi_templates', fn (Blueprint $table) => $table->json('metric_targets')->nullable()->after('food_volume_target'));

        if (! DB::table('kpi_metrics')->exists()) DB::table('kpi_metrics')->insert([
            ['name'=>'Total NOO','source'=>'new_customer','filters'=>json_encode([]),'unit'=>'count','threshold'=>null,'is_active'=>true,'counts_in_achievement'=>true,'sort_order'=>1,'legacy_key'=>'noo','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'NOO Custom','source'=>'new_customer','filters'=>json_encode(['product_type'=>'custom']),'unit'=>'count','threshold'=>null,'is_active'=>true,'counts_in_achievement'=>true,'sort_order'=>2,'legacy_key'=>'custom_noo','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Akun Besar','source'=>'large_account','filters'=>json_encode([]),'unit'=>'count','threshold'=>50000000,'is_active'=>true,'counts_in_achievement'=>true,'sort_order'=>3,'legacy_key'=>'large_account','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Drink','source'=>'won_quantity','filters'=>json_encode(['market_segment'=>'drink']),'unit'=>'pcs','threshold'=>null,'is_active'=>true,'counts_in_achievement'=>true,'sort_order'=>4,'legacy_key'=>'drink','created_at'=>now(),'updated_at'=>now()],
            ['name'=>'Food','source'=>'won_quantity','filters'=>json_encode(['market_segment'=>'food']),'unit'=>'pcs','threshold'=>null,'is_active'=>true,'counts_in_achievement'=>true,'sort_order'=>5,'legacy_key'=>'food','created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('kpi_templates', fn (Blueprint $table) => $table->dropColumn('metric_targets'));
        Schema::table('sales_kpi_targets', fn (Blueprint $table) => $table->dropColumn('metric_targets'));
        Schema::dropIfExists('kpi_metrics');
    }
};
