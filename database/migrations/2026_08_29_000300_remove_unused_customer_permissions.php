<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::whereIn('key', ['customers.create', 'customers.invite'])->delete();
    }

    public function down(): void
    {
        Permission::updateOrCreate(['key' => 'customers.create'], ['module' => 'customers', 'action' => 'create', 'label' => 'Create Customers']);
        Permission::updateOrCreate(['key' => 'customers.invite'], ['module' => 'customers', 'action' => 'invite', 'label' => 'Invite Customers']);
    }
};
