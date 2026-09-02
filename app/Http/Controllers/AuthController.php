<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\AuditLog;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login', [
            'tenants' => Tenant::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, TenantManager $tenancy)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], ['tenant_id.required' => 'Perusahaan wajib dipilih.']);
        $tenant = Tenant::query()->whereKey($data['tenant_id'])->where('is_active', true)->first();
        if (! $tenant) {
            return back()->withErrors(['tenant_id' => 'Perusahaan tidak tersedia.'])->onlyInput('tenant_id', 'email');
        }
        $tenancy->initialize($tenant);
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::record('login_failed', 'authentication', null, null, [
                'email' => $data['email'], 'tenant' => $tenant->name,
            ], 'Kredensial tidak sesuai');
            return back()->withErrors(['email' => 'Email atau password tidak sesuai untuk perusahaan yang dipilih.'])->onlyInput('tenant_id', 'email');
        }

        if (! $request->user()->is_active) {
            AuditLog::record('login_failed', 'authentication', $request->user(), reason: 'Akun dinonaktifkan');
            Auth::logout();

            return back()->withErrors(['email' => 'Akun sedang dinonaktifkan.']);
        }

        $request->session()->regenerate();
        $request->session()->put('tenant_id', $tenant->id);
        $request->user()->updateQuietly(['last_login_at' => now()]);
        AuditLog::record('login', 'authentication', $request->user(), null, [
            'tenant' => $tenant->name, 'remember' => $request->boolean('remember'),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if ($user) AuditLog::record('logout', 'authentication', $user);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
