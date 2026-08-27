<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', ['roles' => Role::with(['permissions', 'deniedPermissions', 'parentRole.permissions', 'parentRole.deniedPermissions', 'parentRole.parentRole.permissions', 'users.roles'])->withCount('users')->get()]);
    }

    public function create()
    {
        return $this->form(new Role);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'unique:roles,name'], 'description' => ['nullable'], 'parent_role_id' => ['nullable', 'exists:roles,id'], 'permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']]);
        $role = Role::create($data + ['slug' => Str::slug($data['name'])]);
        $this->syncPermissions($request, $role);

        return redirect()->route('roles.index')->with('success', 'Role dibuat.');
    }

    public function edit(Role $role)
    {
        return $this->form($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate(['name' => ['required', Rule::unique('roles', 'name')->ignore($role)], 'description' => ['nullable'], 'parent_role_id' => ['nullable', 'exists:roles,id', Rule::notIn([$role->id])], 'permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']]);
        if (! empty($data['parent_role_id'])) {
            $parent = Role::findOrFail($data['parent_role_id']);
            abort_if($this->inheritsFrom($parent, $role->id), 422, 'Hierarki role tidak boleh membentuk siklus.');
        }
        $role->update($data);
        $this->syncPermissions($request, $role);

        return redirect()->route('roles.index')->with('success', 'Role diperbarui.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->slug === 'master_admin', 422, 'Role Master Admin tidak dapat dihapus.');

        $name = $role->name;
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role '.$name.' berhasil dihapus.');
    }

    private function form(Role $role)
    {
        $roles = Role::when($role->exists, fn ($query) => $query->where('id', '!=', $role->id))
            ->with(['permissions', 'deniedPermissions', 'parentRole.permissions', 'parentRole.deniedPermissions', 'parentRole.parentRole.permissions', 'parentRole.parentRole.deniedPermissions'])
            ->orderBy('name')
            ->get();

        return view('roles.form', [
            'role' => $role,
            'permissions' => Permission::orderBy('module')->get()->groupBy('module'),
            'roles' => $roles,
            'inheritedPermissionMap' => $roles->mapWithKeys(fn (Role $candidate) => [
                (string) $candidate->id => $candidate->effectivePermissions()->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            ]),
            'roleNameMap' => $roles->mapWithKeys(fn (Role $candidate) => [(string) $candidate->id => $candidate->name]),
            'permissionDetails' => Permission::orderBy('module')->get()->mapWithKeys(fn (Permission $permission) => [(string) $permission->id => ['label' => $permission->label, 'key' => $permission->key]]),
        ]);
    }

    private function syncPermissions(Request $request, Role $role): void
    {
        $selectedIds = collect($request->input('permission_ids', []))->map(fn ($id) => (int) $id)->unique();
        $inheritedIds = $role->parent_role_id
            ? Role::with(['permissions', 'deniedPermissions', 'parentRole.permissions', 'parentRole.deniedPermissions'])->findOrFail($role->parent_role_id)->effectivePermissions()->pluck('id')
            : collect();

        $role->permissions()->sync($selectedIds->diff($inheritedIds)->values()->all());
        $role->deniedPermissions()->sync($inheritedIds->diff($selectedIds)->values()->all());
    }

    private function inheritsFrom(Role $role, int $targetId): bool
    {
        $visited = [];
        while ($role && ! isset($visited[$role->id])) {
            if ((int) $role->id === $targetId) return true;
            $visited[$role->id] = true;
            $role = $role->parentRole;
        }
        return false;
    }
}
