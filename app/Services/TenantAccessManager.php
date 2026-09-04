<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TenantAccessManager
{
    public function availableFor(User $centralUser): Collection
    {
        $this->ensureHomeCompanyAccess($centralUser);

        return Tenant::query()
            ->where('is_active', true)
            ->whereIn('id', TenantUserAccess::query()
                ->where('central_user_id', $centralUser->id)
                ->select('tenant_id'))
            ->orderBy('name')
            ->get();
    }

    public function activate(User $centralUser, Tenant $tenant, bool $remember = false): bool
    {
        $access = TenantUserAccess::query()
            ->where('central_user_id', $centralUser->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $access || ! $tenant->is_active) {
            return false;
        }

        app(TenantManager::class)->initialize($tenant);
        $tenantUser = User::on('tenant')->find($access->tenant_user_id);

        if (! $tenantUser || ! $tenantUser->is_active) {
            return false;
        }

        Auth::login($tenantUser, $remember);
        session([
            'tenant_id' => $tenant->id,
            'platform_user_id' => $centralUser->id,
        ]);

        return true;
    }

    public function grantByEmail(User $centralUser, Tenant $tenant, string $email): bool
    {
        $centralRoleSlugs = $centralUser->roles()->pluck('slug');
        app(TenantManager::class)->initialize($tenant);
        $tenantUser = User::on('tenant')->where('email', $email)->where('is_active', true)->first();

        if (! $tenantUser) {
            // Satu akun tetap memakai kredensial pusat. Record ini hanyalah
            // profil internal tenant agar role, audit trail, dan relasi data
            // di database perusahaan tetap terpisah dan konsisten.
            $tenantUser = User::on('tenant')->create([
                'employee_id' => 'USR-'.str_pad((string) (User::on('tenant')->max('id') + 1), 4, '0', STR_PAD_LEFT),
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'phone' => $centralUser->phone,
                'avatar_path' => $centralUser->avatar_path,
                'password' => Str::random(40),
                'authority_level' => $centralUser->authority_level,
                'user_type' => $centralUser->user_type,
                'is_approver' => $centralUser->is_approver,
                'is_active' => true,
                'settings' => $centralUser->settings,
            ]);

            $roleIds = Role::on('tenant')->whereIn('slug', $centralRoleSlugs)->pluck('id');
            $tenantUser->roles()->sync($roleIds);
        }

        TenantUserAccess::query()->updateOrCreate(
            ['central_user_id' => $centralUser->id, 'tenant_id' => $tenant->id],
            ['tenant_user_id' => $tenantUser->id],
        );

        return true;
    }

    public function sync(User $centralUser, Collection $tenants): void
    {
        $tenantIds = $tenants->pluck('id')->map(fn ($id) => (int) $id);
        TenantUserAccess::query()
            ->where('central_user_id', $centralUser->id)
            ->whereNotIn('tenant_id', $tenantIds)
            ->delete();

        foreach ($tenants as $tenant) {
            $this->grantByEmail($centralUser, $tenant, $centralUser->email);
        }
    }

    private function ensureHomeCompanyAccess(User $centralUser): void
    {
        $database = config('database.connections.central.database');
        $homeTenant = Tenant::query()->where('database_name', $database)->first();

        if ($homeTenant && ! TenantUserAccess::query()->where('central_user_id', $centralUser->id)->exists()) {
            TenantUserAccess::query()->firstOrCreate([
                'central_user_id' => $centralUser->id,
                'tenant_id' => $homeTenant->id,
            ], ['tenant_user_id' => $centralUser->id]);
        }
    }
}
