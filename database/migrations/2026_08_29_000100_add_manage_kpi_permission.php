<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['key' => 'kpi.manage'],
            ['module' => 'kpi', 'action' => 'manage', 'label' => 'Manage KPI']
        );

        Role::query()
            ->whereIn('slug', ['csa', 'sales_supervisor', 'sales_manager'])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permission->id));
    }

    public function down(): void
    {
        Permission::where('key', 'kpi.manage')->delete();
    }
};
