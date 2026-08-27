<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Activity;
use App\Models\Role;
use App\Models\User;
use App\Support\Crm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")))
            ->when($request->user_type, fn ($query, $type) => $query->where('user_type', $type))
            ->when($request->role_id, fn ($query, $roleId) => $query->whereHas('roles', fn ($roles) => $roles->whereKey($roleId)))
            ->when($request->status !== null && $request->status !== '', fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->when($request->approver !== null && $request->approver !== '', fn ($query) => $query->where('is_approver', $request->boolean('approver')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('users.form', $this->formData(new User));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['authority_level'] = $this->authorityLevelFor($request->input('role_ids', []));
        $data['is_approver'] = $request->has('is_approver')
            ? $request->boolean('is_approver')
            : $this->defaultApproverFor($request->input('role_ids', []));
        $data['employee_id'] = 'USR-'.str_pad((string) (User::max('id') + 1), 4, '0', STR_PAD_LEFT);
        $user = User::create($data);
        $this->sync($request, $user);

        return redirect()->route('users.index')->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('users.form', $this->formData($user->load('roles')));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $data['authority_level'] = $this->authorityLevelFor($request->input('role_ids', []));
        $willLoseApprovalAccess = $user->canApprove()
            && (! (bool) ($data['is_approver'] ?? false) || ! (bool) $data['is_active']);
        if ($willLoseApprovalAccess && $this->orphanedPendingApprovalCount($user) > 0) {
            throw ValidationException::withMessages([
                'is_approver' => 'Akun masih menjadi satu-satunya approver pada pengajuan pending. Tambahkan approver pengganti sebelum menonaktifkan hak approval atau akun.',
            ]);
        }
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);
        $this->sync($request, $user);

        return redirect()->route('users.index')->with('success', 'User diperbarui.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required'], 'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable'], 'password' => [$user ? 'nullable' : 'required', 'min:8', 'confirmed'],
            'user_type' => ['required', Rule::in(array_keys(Crm::USER_TYPES))],
            'is_approver' => ['sometimes', 'required', 'boolean'],
            'manager_id' => ['nullable', 'exists:users,id', ...($user ? [Rule::notIn([$user->id])] : [])],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'size:1'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
        ]);

        $this->validateRoleRules($data, $user);

        return $data;
    }

    private function sync(Request $request, User $user): void
    {
        $roleIds = collect($request->input('role_ids', []))->map(fn ($id) => (int) $id);
        $masterAdminRoleId = Role::where('slug', 'master_admin')->value('id');
        if ($masterAdminRoleId) {
            $roleIds = $user->authority_level === 'master_admin'
                ? collect([(int) $masterAdminRoleId])
                : $roleIds->reject(fn ($id) => $id === (int) $masterAdminRoleId);
        }
        $user->roles()->sync($roleIds->unique()->values()->all());
        $departmentNames = Role::whereKey($roleIds)->pluck('slug')->map(fn (string $slug) => match ($slug) {
            'sales', 'csa', 'sales_supervisor', 'sales_manager' => 'Sales',
            'finance' => 'Finance',
            'purchasing' => 'Purchasing',
            'warehouse' => 'Warehouse',
            default => null,
        })->filter()->unique();
        $user->departments()->sync(Department::whereIn('name', $departmentNames)->pluck('id')->all());
    }

    private function formData(User $user): array
    {
        return compact('user') + [
            'roles' => Role::orderByRaw("slug = 'master_admin' desc")->orderBy('name')->get(),
            'managers' => User::with('roles')->where('is_active', true)
                ->where('authority_level', '!=', 'master_admin')
                ->where(fn ($query) => $query
                    ->whereIn('authority_level', ['manager', 'supervisor'])
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('slug', ['csa', 'sales_supervisor', 'sales_manager'])))
                ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
                ->orderBy('name')
                ->get(),
            'userTypes' => Crm::USER_TYPES,
        ];
    }

    private function authorityLevelFor(array $roleIds): string
    {
        $slugs = Role::whereKey($roleIds)->pluck('slug');

        return match (true) {
            $slugs->contains('master_admin') => 'master_admin',
            $slugs->contains('sales_manager') => 'manager',
            $slugs->contains(fn (string $slug) => in_array($slug, ['csa', 'sales_supervisor'], true)) => 'supervisor',
            default => 'staff',
        };
    }

    private function defaultApproverFor(array $roleIds): bool
    {
        return Role::whereKey($roleIds)
            ->whereIn('slug', ['master_admin', 'sales_manager', 'sales_supervisor', 'csa'])
            ->exists();
    }

    private function validateRoleRules(array $data, ?User $user): void
    {
        $role = Role::find($data['role_ids'][0]);
        $manager = isset($data['manager_id']) ? User::with('roles')->find($data['manager_id']) : null;
        $errors = [];

        $allowedManagerRoles = match ($role->slug) {
            'sales' => ['csa', 'sales_supervisor'],
            'csa', 'sales_supervisor' => ['sales_manager'],
            default => null,
        };

        if ($allowedManagerRoles !== null && ! $manager) {
            $errors['manager_id'] = 'Koordinator wajib dipilih untuk role '.$role->name.'.';
        } elseif ($allowedManagerRoles !== null && ! $manager->roles->contains(fn (Role $managerRole) => in_array($managerRole->slug, $allowedManagerRoles, true))) {
            $errors['manager_id'] = 'Koordinator yang dipilih tidak sesuai dengan hierarki role '.$role->name.'.';
        }

        if (in_array($role->slug, ['sales_manager', 'master_admin'], true) && $manager) {
            $errors['manager_id'] = 'Role '.$role->name.' tidak memerlukan koordinator.';
        }

        if ($user && $manager && $this->createsCoordinatorCycle($user, $manager)) {
            $errors['manager_id'] = 'Koordinator tersebut akan membuat hierarki pengguna berputar.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function createsCoordinatorCycle(User $user, User $manager): bool
    {
        $visited = [];
        $current = $manager;

        while ($current) {
            if ($current->id === $user->id || isset($visited[$current->id])) {
                return true;
            }
            $visited[$current->id] = true;
            $current = $current->manager;
        }

        return false;
    }

    private function orphanedPendingApprovalCount(User $user): int
    {
        $activities = Activity::query()
            ->whereJsonContains('participants', $user->id)
            ->whereHas('approvalDetail', fn ($query) => $query->where('approval_status', 'pending'))
            ->get(['id', 'participants']);
        $participantIds = $activities->pluck('participants')->flatten()->map(fn ($id) => (int) $id)->unique();
        $availableApproverIds = User::query()
            ->whereIn('id', $participantIds)
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->where('is_approver', true)
            ->pluck('id');

        return $activities->filter(fn (Activity $activity) => collect($activity->participants)
            ->map(fn ($id) => (int) $id)
            ->intersect($availableApproverIds)
            ->isEmpty())->count();
    }

}
