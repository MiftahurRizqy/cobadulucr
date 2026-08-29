<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropUnique('system_settings_key_unique');
            $table->foreignId('role_id')->nullable()->after('key')->constrained()->cascadeOnDelete();
            $table->unique(['key', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'role_id']);
            $table->dropConstrainedForeignId('role_id');
            $table->unique('key');
        });
    }
};
