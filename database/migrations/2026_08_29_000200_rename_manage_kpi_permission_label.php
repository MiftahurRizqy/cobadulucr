<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::where('key', 'kpi.manage')->update(['label' => 'Manage KPI']);
    }

    public function down(): void
    {
        Permission::where('key', 'kpi.manage')->update(['label' => 'Atur KPI']);
    }
};
