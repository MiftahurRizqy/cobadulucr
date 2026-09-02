<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TenantManager
{
    private ?Tenant $current = null;

    public function initialize(Tenant $tenant): void
    {
        $central = config('database.connections.central');
        if (($central['driver'] ?? null) === 'sqlite') {
            $this->current = $tenant;
            return;
        }
        config([
            'database.connections.tenant' => array_merge($central, ['database' => $tenant->database_name]),
            'database.default' => 'tenant',
        ]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        // Guard session menyimpan instance user yang sudah pernah dimuat. Saat
        // database tenant berubah, buang instance tersebut agar ID yang sama
        // tidak membawa data user dari perusahaan sebelumnya.
        Auth::forgetGuards();
        $this->current = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }
}
