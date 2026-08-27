<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY status ENUM('leads_adds','cold_lead','warm_lead','leads_hold','leads_risky','converted') NOT NULL DEFAULT 'cold_lead'");
            DB::statement("ALTER TABLE customers MODIFY status ENUM('active','inactive','blocked','pareto','risky') NOT NULL DEFAULT 'active'");
            DB::statement("UPDATE customers SET status = 'risky' WHERE health = 'risk' OR status = 'blocked'");
            DB::statement("ALTER TABLE customers MODIFY status ENUM('pareto','active','inactive','risky') NOT NULL DEFAULT 'active'");
        }

        if (Schema::hasColumn('customers', 'health')) {
            Schema::table('customers', fn ($table) => $table->dropColumn('health'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'health')) {
            Schema::table('customers', fn ($table) => $table->enum('health', ['healthy', 'watch', 'risk'])->default('healthy')->index());
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE customers SET status = 'active' WHERE status IN ('pareto','risky')");
            DB::statement("ALTER TABLE customers MODIFY status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active'");
            DB::statement("UPDATE leads SET status = 'leads_hold' WHERE status = 'leads_risky'");
            DB::statement("ALTER TABLE leads MODIFY status ENUM('cold_lead','warm_lead','leads_hold','converted') NOT NULL DEFAULT 'cold_lead'");
        }
    }
};
