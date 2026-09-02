<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentActivityPolicyController extends Controller
{
    public function index()
    {
        return view('settings.activity-evidence', [
            'roles' => Role::query()->where('slug', '!=', 'master_admin')->withCount('users')->orderBy('name')->get(),
            'requiredRoleIds' => SystemSetting::query()->where('key', 'activity_evidence_required')->whereNotNull('role_id')->where('value', '1')->pluck('role_id'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'required_role_ids' => ['nullable', 'array'],
            'required_role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($data) {
            $requiredRoleIds = collect($data['required_role_ids'] ?? [])->map(fn ($id) => (int) $id);
            Role::query()->where('slug', '!=', 'master_admin')->pluck('id')->each(function (int $roleId) use ($requiredRoleIds) {
                SystemSetting::setBool('activity_evidence_required', $requiredRoleIds->contains($roleId), $roleId);
            });
        });

        return back()->with('success', 'Kebijakan bukti aktivitas berhasil diperbarui.');
    }
}
