<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('system_settings')->insert(collect([
            'opportunity_product_photo_required' => true,
            'customer_legal_name_required' => true,
            'customer_npwp_required' => true,
        ])->map(fn ($value, $key) => [
            'key' => $key,
            'value' => $value ? '1' : '0',
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
