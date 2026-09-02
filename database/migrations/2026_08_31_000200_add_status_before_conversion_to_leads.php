<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->string('status_before_conversion')->nullable());
        if (! Schema::hasTable('audit_logs')) return;

        DB::table('leads')->where('status', 'converted')->orderBy('id')->chunkById(100, function ($leads) {
            foreach ($leads as $lead) {
                $logs = DB::table('audit_logs')->where('auditable_type', 'App\\Models\\Lead')
                    ->where('auditable_id', $lead->id)->orderByDesc('id')->get();
                foreach ($logs as $log) {
                    $old = json_decode($log->old_values ?? '{}', true);
                    $new = json_decode($log->new_values ?? '{}', true);
                    if (($new['status'] ?? null) === 'converted' && in_array($old['status'] ?? null, ['leads_adds', 'cold_lead', 'warm_lead', 'leads_hold', 'leads_risky'], true)) {
                        DB::table('leads')->where('id', $lead->id)->update(['status_before_conversion' => $old['status']]);
                        break;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('status_before_conversion'));
    }
};
