<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('sales_target', 16, 2)->default(0);
            $table->enum('evaluation_status', ['on_track', 'attention', 'coaching'])->default('on_track');
            $table->text('evaluation_notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'period_start', 'period_end']);
        });

        $permissionId = DB::table('permissions')->insertGetId([
            'module' => 'kpi', 'action' => 'view', 'key' => 'kpi.view', 'label' => 'View KPI',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleIds = DB::table('roles')->whereIn('slug', ['sales', 'csa', 'sales_supervisor', 'sales_manager'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('key', 'kpi.view')->first();
        if ($permission) DB::table('permission_role')->where('permission_id', $permission->id)->delete();
        DB::table('permissions')->where('key', 'kpi.view')->delete();
        Schema::dropIfExists('sales_kpi_targets');
    }
};
