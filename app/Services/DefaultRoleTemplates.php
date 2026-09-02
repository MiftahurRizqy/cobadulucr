<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DefaultRoleTemplates
{
    public function hasMissingRoles(): bool
    {
        $roles = Role::get(['slug', 'name']);
        foreach (['master_admin', 'sales_manager', 'sales_supervisor', 'sales', 'telesales', 'csa', 'finance', 'purchasing', 'warehouse'] as $slug) {
            $name = $slug === 'csa' ? 'CSA' : Str::headline($slug);
            if (! $roles->contains(fn ($role) => $role->slug === $slug || strcasecmp($role->name, $name) === 0)) return true;
        }
        return false;
    }

    public function apply(): int
    {
        return DB::transaction(function () {
            $definitions = [
                'dashboard' => ['view'], 'leads' => ['view', 'create', 'edit', 'convert', 'invite'],
                'customers' => ['view', 'edit'], 'opportunities' => ['view', 'create', 'edit', 'move_stage'],
                'activities' => ['view', 'create'], 'tasks' => ['view', 'create', 'update'],
                'approvals' => ['view', 'create', 'decide'], 'reports' => ['view'],
                'kpi' => ['view', 'manage'], 'admin' => ['manage'],
            ];
            $permissions = collect($definitions)->flatMap(fn ($actions, $module) => collect($actions)->map(
                fn ($action) => Permission::firstOrCreate(['key' => "$module.$action"], [
                    'module' => $module, 'action' => $action, 'label' => Str::headline("$action $module"),
                ])
            ));
            $frontline = ['dashboard', 'leads', 'customers', 'opportunities', 'activities', 'tasks', 'approvals', 'kpi'];
            $templates = [
                'master_admin' => array_keys($definitions),
                'sales_manager' => [...$frontline, 'reports'],
                'sales_supervisor' => [...$frontline, 'reports'],
                'sales' => $frontline,
                'telesales' => $frontline,
                'csa' => [...$frontline, 'reports'],
                'finance' => ['dashboard', 'customers', 'tasks', 'approvals', 'reports'],
                'purchasing' => ['dashboard', 'customers', 'tasks', 'approvals'],
                'warehouse' => ['dashboard', 'customers', 'tasks', 'activities'],
            ];
            $created = 0;
            foreach ($templates as $slug => $modules) {
                $name = $slug === 'csa' ? 'CSA' : Str::headline($slug);
                // Existing roles (including custom names/permissions) belong to the tenant.
                if (Role::where('slug', $slug)->orWhere('name', $name)->exists()) continue;
                $role = Role::create(['slug' => $slug, 'name' => $name, 'description' => 'Template '.$name, 'is_system' => true]);
                $ids = $permissions->whereIn('module', $modules)->reject(function ($permission) use ($slug) {
                    if (in_array($slug, ['sales', 'telesales'], true) && in_array($permission->key, ['kpi.manage', 'approvals.decide'], true)) return true;
                    return false;
                })->pluck('id');
                $role->permissions()->sync($ids);
                AuditLog::recordRelation($role, 'permissions', [], $ids);
                $created++;
            }
            return $created;
        });
    }
}
