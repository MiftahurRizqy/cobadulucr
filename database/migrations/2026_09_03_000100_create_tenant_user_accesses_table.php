<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table belongs to the central platform database. Tenant databases
        // still record this migration, so provisioning another company must not
        // try to create the same central table again.
        if (Schema::connection('central')->hasTable('tenant_user_accesses')) {
            return;
        }

        Schema::connection('central')->create('tenant_user_accesses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('tenant_user_id');
            $table->timestamps();
            $table->unique(['central_user_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        if (config('database.default') === 'central') {
            Schema::connection('central')->dropIfExists('tenant_user_accesses');
        }
    }
};
