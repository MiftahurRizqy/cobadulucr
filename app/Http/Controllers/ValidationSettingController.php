<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ValidationSettingController extends Controller
{
    public function index(Request $request): View
    {
        $selectedRoleId = $request->integer('role_id') ?: null;
        $selectedRole = $selectedRoleId ? Role::query()->findOrFail($selectedRoleId) : null;
        $overrides = $selectedRoleId
            ? SystemSetting::query()->where('role_id', $selectedRoleId)->pluck('value', 'key')
            : collect();

        $value = fn (string $key) => $selectedRoleId
            ? filter_var($overrides->get($key, SystemSetting::bool($key) ? '1' : '0'), FILTER_VALIDATE_BOOL)
            : SystemSetting::bool($key);

        return view('settings.validation', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'selectedRole' => $selectedRole,
            'selectedRoleId' => $selectedRoleId,
            'usesGlobalSettings' => $selectedRoleId && $overrides->isEmpty(),
            'productPhotoRequired' => $value('opportunity_product_photo_required'),
            'legalNameRequired' => $value('customer_legal_name_required'),
            'npwpRequired' => $value('customer_npwp_required'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['role_id' => ['nullable', 'integer', 'exists:roles,id']]);
        $roleId = isset($data['role_id']) ? (int) $data['role_id'] : null;

        if ($roleId && $request->boolean('use_global')) {
            SystemSetting::removeRoleOverrides($roleId);

            return back()->with('success', 'Role sekarang mengikuti pengaturan Semua role.');
        }

        SystemSetting::setBool('opportunity_product_photo_required', $request->boolean('opportunity_product_photo_required'), $roleId);
        SystemSetting::setBool('customer_legal_name_required', $request->boolean('customer_legal_name_required'), $roleId);
        SystemSetting::setBool('customer_npwp_required', $request->boolean('customer_npwp_required'), $roleId);

        return back()->with('success', 'Pengaturan validasi '.($roleId ? 'role' : 'semua role').' berhasil diperbarui.');
    }
}
