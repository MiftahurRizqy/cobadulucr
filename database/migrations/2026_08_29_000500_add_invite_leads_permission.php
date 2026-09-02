<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['key' => 'leads.invite'],
            ['module' => 'leads', 'action' => 'invite', 'label' => 'Invite Leads']
        );

        Role::whereIn('slug', ['sales', 'telesales', 'csa', 'sales_supervisor', 'sales_manager'])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permission->id));
    }

    public function down(): void
    {
        Permission::where('key', 'leads.invite')->delete();
    }
};
