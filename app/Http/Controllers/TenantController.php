<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\TenantAccessManager;
use App\Services\TenantConfigurationCloner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class TenantController extends Controller
{
    public function index(Request $request, TenantManager $tenancy): View
    {
        $this->authorizePlatform($request, $tenancy);
        $tenants = Tenant::query()->orderBy('name')->get();
        foreach ($tenants as $tenant) {
            $tenant->setAttribute('setup_complete', $this->tenantHasAdmin($tenant, $tenancy));
        }

        return view('tenants.index', compact('tenants'));
    }

    public function store(Request $request, TenantManager $tenancy, TenantConfigurationCloner $configurationCloner): RedirectResponse
    {
        $this->authorizePlatform($request, $tenancy);
        $manualDatabaseProvisioning = env('TENANT_DATABASE_PROVISIONING') === 'manual';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'database_name' => [$manualDatabaseProvisioning ? 'required' : 'nullable', 'string', 'max:63'],
            'database_username' => [$manualDatabaseProvisioning ? 'required' : 'nullable', 'string', 'max:255'],
            'database_password' => [$manualDatabaseProvisioning ? 'required' : 'nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
            'duplicate_configuration' => ['nullable', 'boolean'],
        ]);
        [$slug, $generatedDatabaseName] = $this->tenantIdentifiers($data['name']);
        $data['slug'] = $slug;
        $data['database_name'] = $manualDatabaseProvisioning ? $data['database_name'] : $generatedDatabaseName;

        $originalTenant = $tenancy->current();
        $configuration = $request->boolean('duplicate_configuration') ? $configurationCloner->export() : null;
        $logoPath = null;
        $databaseCreated = false;
        try {
            if (! $manualDatabaseProvisioning) {
                DB::connection('central')->statement(sprintf(
                    'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    $data['database_name']
                ));
                $databaseCreated = true;
            }

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('tenant-logos', 'public');
            }

            $tenant = Tenant::create([
                'name' => $data['name'], 'slug' => Str::slug($data['slug']),
                'database_name' => $data['database_name'],
                'database_username' => $manualDatabaseProvisioning ? $data['database_username'] : null,
                'database_password' => $manualDatabaseProvisioning ? $data['database_password'] : null,
                'primary_color' => '#4f46e5',
                'logo_path' => $logoPath,
                'is_active' => false,
            ]);

            $tenancy->initialize($tenant);
            $this->migrateTenant();
            $this->bootstrapAdmin($data);
            if ($configuration) $configurationCloner->import($configuration);
            $tenant->update(['is_active' => true]);
        } catch (Throwable $exception) {
            report($exception);
            if (isset($tenant)) $tenant->delete();
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            if ($databaseCreated) {
                DB::connection('central')->statement(sprintf('DROP DATABASE IF EXISTS `%s`', $data['database_name']));
            }
            return back()->withInput($request->only(['name', 'admin_name', 'admin_email']))
                ->withErrors(['name' => 'Perusahaan belum berhasil dibuat. Periksa koneksi dan hak akses database server.']);
        } finally {
            if ($originalTenant) $tenancy->initialize($originalTenant);
        }

        AuditLog::record('created', 'tenants', $tenant, null, $tenant->only(['name', 'slug', 'database_name', 'primary_color', 'logo_path', 'is_active']));

        return back()->with('success', $manualDatabaseProvisioning
            ? 'Perusahaan berhasil dibuat menggunakan database yang telah disiapkan'.($configuration ? ' dengan konfigurasi yang diduplikat.' : '.')
            : 'Perusahaan dan database baru berhasil dibuat'.($configuration ? ' dengan konfigurasi yang diduplikat.' : '.'));
    }

    public function toggle(Request $request, Tenant $tenant, TenantManager $tenancy): RedirectResponse
    {
        $this->authorizePlatform($request, $tenancy);
        $this->authorizeOwnCompany($tenant, $tenancy);
        abort_if($tenant->id === $tenancy->current()?->id, 422, 'Perusahaan yang sedang digunakan tidak dapat dinonaktifkan.');
        if (! $tenant->is_active && ! $this->tenantHasAdmin($tenant, $tenancy)) {
            return back()->withErrors(['tenant' => 'Setup perusahaan belum selesai. Gunakan tombol Selesaikan setup untuk membuat Master Administrator.']);
        }
        $oldValues = ['is_active' => $tenant->is_active];
        $tenant->update(['is_active' => ! $tenant->is_active]);
        AuditLog::record('updated', 'tenants', $tenant, $oldValues, ['is_active' => $tenant->is_active]);
        return back()->with('success', 'Status perusahaan berhasil diperbarui.');
    }

    public function update(Request $request, Tenant $tenant, TenantManager $tenancy): RedirectResponse
    {
        $this->authorizePlatform($request, $tenancy);
        $this->authorizeOwnCompany($tenant, $tenancy);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $oldLogo = $tenant->logo_path;
        $oldValues = $tenant->only(['name', 'logo_path']);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('tenant-logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }
        unset($data['logo'], $data['remove_logo']);
        $tenant->update($data);
        $newValues = $tenant->only(['name', 'logo_path']);
        $changedKeys = collect($newValues)->filter(fn ($value, $key) => $value !== $oldValues[$key])->keys();
        if ($changedKeys->isNotEmpty()) {
            AuditLog::record('updated', 'tenants', $tenant, collect($oldValues)->only($changedKeys)->all(), collect($newValues)->only($changedKeys)->all());
        }

        if ($oldLogo && array_key_exists('logo_path', $data) && $oldLogo !== $data['logo_path']) {
            Storage::disk('public')->delete($oldLogo);
        }

        return back()->with('success', 'Identitas perusahaan berhasil diperbarui.');
    }

    public function completeSetup(Request $request, Tenant $tenant, TenantManager $tenancy): RedirectResponse
    {
        $this->authorizePlatform($request, $tenancy);
        $this->authorizeOwnCompany($tenant, $tenancy);
        abort_if($this->tenantHasAdmin($tenant, $tenancy), 422, 'Perusahaan sudah memiliki administrator.');
        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $originalTenant = $tenancy->current();

        try {
            $tenancy->initialize($tenant);
            $this->migrateTenant();
            $this->bootstrapAdmin($data);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['tenant' => 'Setup perusahaan belum berhasil diselesaikan. Periksa koneksi database lalu coba lagi.']);
        } finally {
            if ($originalTenant) $tenancy->initialize($originalTenant);
        }

        $tenant->update(['is_active' => true]);
        AuditLog::record('setup_completed', 'tenants', $tenant, ['is_active' => false], ['is_active' => true], 'Master Administrator berhasil dibuat.');

        return back()->with('success', 'Setup '.$tenant->name.' selesai. Master Administrator sekarang dapat login.');
    }

    private function authorizePlatform(Request $request, TenantManager $tenancy): void
    {
        abort_unless($request->user()->isMasterAdmin() && $tenancy->current(), 403);
    }

    private function migrateTenant(): void
    {
        $exitCode = Artisan::call('migrate', ['--database' => 'tenant', '--force' => true]);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Migration perusahaan gagal (exit code '.$exitCode.').');
        }
    }

    private function authorizeOwnCompany(Tenant $tenant, TenantManager $tenancy): void
    {
        abort_unless((int) $tenant->id === (int) $tenancy->current()?->id, 403);
    }

    private function tenantIdentifiers(string $name): array
    {
        $baseSlug = Str::limit(Str::slug($name) ?: 'perusahaan', 70, '');
        $baseDatabase = 'crm_'.Str::limit(str_replace('-', '_', $baseSlug), 54, '');
        $sequence = 1;

        do {
            $suffix = $sequence === 1 ? '' : '-'.$sequence;
            $databaseSuffix = $sequence === 1 ? '' : '_'.$sequence;
            $slug = Str::limit($baseSlug, 80 - strlen($suffix), '').$suffix;
            $databaseName = Str::limit($baseDatabase, 63 - strlen($databaseSuffix), '').$databaseSuffix;
            $sequence++;
        } while (Tenant::query()->where('slug', $slug)->orWhere('database_name', $databaseName)->exists());

        return [$slug, $databaseName];
    }

    private function tenantHasAdmin(Tenant $tenant, TenantManager $tenancy): bool
    {
        $originalTenant = $tenancy->current();

        try {
            $tenancy->initialize($tenant);
            return User::query()->where('is_active', true)
                ->whereHas('roles', fn ($roles) => $roles->where('slug', 'master_admin'))
                ->exists();
        } catch (Throwable) {
            return false;
        } finally {
            if ($originalTenant) $tenancy->initialize($originalTenant);
        }
    }

    private function bootstrapAdmin(array $data): void
    {
        app(\App\Services\DefaultRoleTemplates::class)->apply();
        $definitions = [
            'dashboard' => ['view'], 'leads' => ['view', 'create', 'edit', 'convert', 'invite'],
            'customers' => ['view', 'edit'], 'opportunities' => ['view', 'create', 'edit', 'move_stage'],
            'activities' => ['view', 'create'], 'tasks' => ['view', 'create', 'update'],
            'approvals' => ['view', 'create', 'decide'], 'reports' => ['view'],
            'kpi' => ['view', 'manage'], 'admin' => ['manage'],
        ];
        $permissions = collect($definitions)->flatMap(fn ($actions, $module) => collect($actions)->map(fn ($action) => Permission::updateOrCreate(
            ['key' => "$module.$action"],
            ['module' => $module, 'action' => $action, 'label' => Str::headline("$action $module")],
        )));
        $role = Role::updateOrCreate(
            ['slug' => 'master_admin'],
            ['name' => 'Master Admin', 'description' => 'Administrator perusahaan', 'is_system' => true],
        );
        $permissionIds = $permissions->pluck('id');
        $oldPermissionIds = $role->permissions()->pluck('permissions.id');
        $role->permissions()->sync($permissionIds);
        AuditLog::recordRelation($role, 'permissions', $oldPermissionIds, $permissionIds);
        $admin = User::query()->whereHas('roles', fn ($roles) => $roles->where('slug', 'master_admin'))->first()
            ?? User::query()->where('email', $data['admin_email'])->first()
            ?? new User;
        $oldRoleIds = $admin->exists ? $admin->roles()->pluck('roles.id') : collect();
        $admin->fill([
            'employee_id' => $admin->employee_id ?? 'USR-'.str_pad((string) (User::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'name' => $data['admin_name'], 'email' => $data['admin_email'],
            'password' => $data['admin_password'], 'authority_level' => 'master_admin',
            'user_type' => 'backliner', 'is_approver' => true, 'is_active' => true,
        ])->save();
        $admin->roles()->syncWithoutDetaching([$role->id]);
        AuditLog::recordRelation($admin, 'roles', $oldRoleIds, $admin->roles()->pluck('roles.id'));
    }
}
