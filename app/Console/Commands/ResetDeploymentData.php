<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDeploymentData extends Command
{
    protected $signature = 'crm:reset-deployment-data {--force : Jalankan pengosongan data tanpa pertanyaan}';
    protected $description = 'Kosongkan data operasional untuk deployment sambil mempertahankan konfigurasi inti perusahaan.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Data operasional akan dihapus permanen. Lanjutkan?')) {
            return self::SUCCESS;
        }

        $masterAdminIds = User::query()
            ->where('authority_level', 'master_admin')
            ->orderBy('id')
            ->pluck('id');

        if ($masterAdminIds->isEmpty()) {
            $this->error('Reset dibatalkan: tidak ditemukan akun Master Admin yang dapat dipertahankan.');

            return self::FAILURE;
        }

        $preservedTables = [
            'migrations', 'tenants', 'system_settings', 'roles', 'permissions',
            'permission_role', 'permission_role_denials', 'kpi_metrics', 'kpi_templates',
            'pipelines', 'pipeline_stages', 'stage_rules', 'business_units', 'departments',
        ];

        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->reject(fn (string $table) => in_array($table, $preservedTables, true))
            ->reject(fn (string $table) => in_array($table, ['users', 'role_user'], true))
            ->values();

        DB::transaction(function () use ($masterAdminIds, $tables) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                // Pipeline tetap ada; jika pembuatnya bukan Master Admin, alihkan ke Master Admin pertama.
                DB::table('pipelines')
                    ->whereNotIn('created_by', $masterAdminIds)
                    ->update(['created_by' => $masterAdminIds->first()]);

                // Relasi role Master Admin dipertahankan, relasi pengguna lain dibersihkan.
                DB::table('role_user')->whereNotIn('user_id', $masterAdminIds)->delete();
                DB::table('users')->whereNotIn('id', $masterAdminIds)->delete();

                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        $this->info('Data deployment berhasil dikosongkan. Pipeline, role/hak akses, KPI Metrics, pengaturan operasional, perusahaan/logo, dan Master Admin tetap dipertahankan.');

        return self::SUCCESS;
    }
}
