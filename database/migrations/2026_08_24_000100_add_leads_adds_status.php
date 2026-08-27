<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY status ENUM('leads_adds','cold_lead','warm_lead','leads_hold','leads_risky','converted') NOT NULL DEFAULT 'cold_lead'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('leads')->where('status', 'leads_adds')->update(['status' => 'cold_lead']);
            DB::statement("ALTER TABLE leads MODIFY status ENUM('cold_lead','warm_lead','leads_hold','leads_risky','converted') NOT NULL DEFAULT 'cold_lead'");
        }
    }
};
