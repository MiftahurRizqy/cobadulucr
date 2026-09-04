<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAccessManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanySelectionController extends Controller
{
    public function create(Request $request, TenantAccessManager $access)
    {
        $centralUser = User::on('central')->find($request->session()->get('platform_user_id'));
        abort_unless($centralUser && $centralUser->is_active, 403);

        return view('auth.company-select', [
            'centralUser' => $centralUser,
            'tenants' => $access->availableFor($centralUser),
        ]);
    }

    public function store(Request $request, TenantAccessManager $access)
    {
        $data = $request->validate(['tenant_id' => ['required', 'integer']]);
        $centralUser = User::on('central')->find($request->session()->get('platform_user_id'));
        $tenant = Tenant::query()->whereKey($data['tenant_id'])->where('is_active', true)->firstOrFail();

        if (! $centralUser || ! $access->activate($centralUser, $tenant, (bool) $request->session()->get('remember_company'))) {
            Auth::logout();
            $request->session()->forget(['tenant_id', 'platform_user_id']);
            return redirect()->route('login')->withErrors(['email' => 'Akses perusahaan tidak tersedia atau akun perusahaan tidak aktif.']);
        }

        $request->session()->regenerate();
        $request->user()->updateQuietly(['last_login_at' => now()]);
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
