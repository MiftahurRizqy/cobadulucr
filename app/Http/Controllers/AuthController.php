<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TenantAccessManager;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, TenantAccessManager $access)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $centralUser = User::on('central')->where('email', $data['email'])->first();
        if (! $centralUser || ! Hash::check($data['password'], $centralUser->password)) {
            return back()->withErrors(['email' => 'Email atau password tidak sesuai.'])->onlyInput('email');
        }
        if (! $centralUser->is_active) return back()->withErrors(['email' => 'Akun sedang dinonaktifkan.']);

        $request->session()->regenerate();
        $request->session()->put('platform_user_id', $centralUser->id);
        $request->session()->put('remember_company', $request->boolean('remember'));
        // Selalu tampilkan pilihan perusahaan setelah login. Dengan demikian
        // akun berakses satu perusahaan hanya melihat satu pilihan, sedangkan
        // akun lintas perusahaan dapat memilih workspace yang ingin dibuka.
        $access->availableFor($centralUser);

        return redirect()->route('company.select');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if ($user) AuditLog::record('logout', 'authentication', $user);
        Auth::logout();
        $request->session()->forget(['tenant_id', 'platform_user_id', 'remember_company']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
