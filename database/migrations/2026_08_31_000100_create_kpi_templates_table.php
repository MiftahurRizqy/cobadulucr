<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->string('role_slug')->unique();
            $table->decimal('sales_target', 16, 2)->default(0);
            $table->unsignedInteger('noo_target')->default(0);
            $table->unsignedInteger('custom_noo_target')->default(0);
            $table->unsignedBigInteger('drink_volume_target')->default(0);
            $table->unsignedBigInteger('food_volume_target')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_templates');
    }
};
