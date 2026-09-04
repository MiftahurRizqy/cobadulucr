<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');
        $tenant = $tenantId ? Tenant::query()->whereKey($tenantId)->where('is_active', true)->first() : null;
        if (! $tenant && ! $tenantId) {
            $activeTenants = Tenant::query()->where('is_active', true)->limit(2)->get();
            if ($activeTenants->count() === 1) {
                $tenant = $activeTenants->first();
                $request->session()->put('tenant_id', $tenant->id);
            }
        }

        if (! $tenant) {
            auth()->logout();
            $request->session()->forget('tenant_id');
            return $request->session()->has('platform_user_id')
                ? redirect()->route('company.select')
                : redirect()->route('login')->withErrors(['email' => 'Silakan masuk untuk melanjutkan.']);
        }

        app(TenantManager::class)->initialize($tenant);
        return $next($request);
    }
}
