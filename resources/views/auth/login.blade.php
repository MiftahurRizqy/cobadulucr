<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · CRM Perusahaan</title>
    <script>
        (() => {
            const saved = localStorage.getItem('crm-theme');
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
<style>
    .login-page{background:#f7f8fa;color:#20242c}
    .login-page main{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100svh;padding:48px 24px}
    .login-page .login-form{width:100%;max-width:440px;padding:36px;background:white;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 4px 24px #17203304}
    .login-page .login-form h2{font-size:24px;font-weight:600;letter-spacing:-.6px}
    .login-page .label{font-size:12px;text-transform:none;letter-spacing:0;font-weight:600;color:#435068;margin-bottom:9px}
    .login-page .field{height:46px;border-radius:7px;border-color:#dce1e9;font-size:13px}
    .login-page .btn-primary{height:46px;border-radius:7px;justify-content:center;padding:0 18px;font-size:13px;font-weight:600;background:#252a35;box-shadow:none}
    .login-page .btn-primary:hover{background:#10141d}
    .login-page .company-identity{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:26px;max-width:440px;width:100%}
    .login-page .company-logo{width:54px;height:54px;flex-shrink:0;border:1px solid #e1e5ed;background:white;border-radius:14px;display:grid;place-items:center;overflow:hidden;color:#4f46e5;font-size:22px;font-weight:650}
    .login-page .company-logo img{width:100%;height:100%;object-fit:contain;padding:6px}
    .login-page .company-name{font-size:14px;font-weight:600;line-height:1.5;overflow-wrap:anywhere}
    .login-page button:focus-visible{outline:3px solid #a5b4fc;outline-offset:3px}
    .login-page .password-field{padding-right:100px}
    .login-page .theme-toggle{position:fixed;right:24px;top:24px;z-index:10;display:grid;width:42px;height:42px;place-items:center;border:1px solid #dfe3ea;border-radius:10px;background:#fff;color:#667085;transition:.15s}
    .login-page .theme-toggle:hover{background:#f1f3f6;color:#252a35}
    html.dark .login-page{background:#0b1120;color:#e5e7eb}
    html.dark .login-page .login-form{background:#111827;border-color:#293449;box-shadow:0 8px 32px #0003}
    html.dark .login-page .company-logo{background:#111827;border-color:#334155;color:#a5b4fc}
    html.dark .login-page .company-name,html.dark .login-page .login-form h2{color:#f1f5f9}
    html.dark .login-page .label{color:#a8b4c5}
    html.dark .login-page .btn-primary{background:#4f46e5}
    html.dark .login-page .btn-primary:hover{background:#4338ca}
    html.dark .login-page .theme-toggle{background:#111827;border-color:#334155;color:#cbd5e1}
    html.dark .login-page .theme-toggle:hover{background:#1e293b;color:#fff}
    @media(max-width:480px){.login-page main{padding:32px 18px}.login-page .login-form{padding:28px 22px}.login-page .company-name{font-size:13px}}
</style>
@php
    $tenantOptions = $tenants->map(fn ($tenant) => ['id' => (string) $tenant->id, 'name' => $tenant->name, 'logo' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null]);
    $initialTenant = (string) old('tenant_id', $tenants->first()?->id ?? '');
@endphp
<main x-data="{ tenantId: @js($initialTenant), tenants: @js($tenantOptions), showPassword: false, theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light', get tenant() { return this.tenants.find(item => item.id === this.tenantId); }, toggleTheme() { this.theme = this.theme === 'dark' ? 'light' : 'dark'; document.documentElement.classList.toggle('dark', this.theme === 'dark'); localStorage.setItem('crm-theme', this.theme); } }">
    <button type="button" class="theme-toggle" @click="toggleTheme()" :aria-label="theme === 'dark' ? 'Gunakan mode terang' : 'Gunakan mode gelap'" :title="theme === 'dark' ? 'Mode terang' : 'Mode gelap'">
        <svg x-show="theme === 'light'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 3V1M12 23v-2M3 12H1M23 12h-2M4.22 4.22 2.8 2.8M21.2 21.2l-1.42-1.42M19.78 4.22 21.2 2.8M2.8 21.2l1.42-1.42"/><circle cx="12" cy="12" r="4"/></svg>
        <svg x-show="theme === 'dark'" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
    </button>
    <div class="company-identity"><div class="company-logo"><template x-if="tenant?.logo"><img :src="tenant.logo" :alt="'Logo ' + tenant.name"></template><span x-show="!tenant?.logo" x-text="tenant?.name?.charAt(0) || 'C'">C</span></div><div><div class="mb-1 text-[11px] font-semibold tracking-[.12em] text-slate-400">CRM</div><div class="company-name" x-text="tenant?.name || 'Company workspace'"></div></div></div>
    <section class="w-full max-w-[440px]" aria-labelledby="login-heading">
        <div class="login-form w-full">
            <div class="mb-7"><h2 id="login-heading" class="text-ink">Sign in</h2><p class="mt-2 text-[13px] text-slate-500">Gunakan akun perusahaan Anda.</p></div>
            @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
            @if($tenants->isEmpty())<div role="status" class="mb-5 text-sm text-slate-500">Belum ada perusahaan tersedia. Hubungi administrator.</div>@endif
            <form action="{{ route('login.store') }}" method="POST" class="space-y-5">@csrf
                <div><label class="label" for="tenant_id">Company</label><select class="field" id="tenant_id" name="tenant_id" x-model="tenantId" required><option value="">Pilih perusahaan</option>@foreach($tenants as $company)<option value="{{ $company->id }}" @selected($initialTenant === (string) $company->id)>{{ $company->name }}</option>@endforeach</select></div>
                <div><label class="label" for="email">Email</label><input class="field py-3.5" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" autocomplete="username" required></div>
                <div><label class="label" for="password">Password</label><div class="relative"><input class="password-field field" id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" placeholder="Masukkan password" autocomplete="current-password" required><button type="button" class="absolute right-0 top-0 grid size-[46px] place-items-center text-slate-500 hover:text-brand-600" @click="showPassword = !showPassword" :aria-pressed="showPassword" aria-controls="password" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"><svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/></svg><svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.3 15.3 0 0 1-2.1 2.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 4.1-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div></div>
                <label class="flex items-center gap-2.5 text-xs text-slate-500"><input class="size-4 accent-brand-600" type="checkbox" name="remember" @checked(old('remember'))> Remember me</label>
                <button class="btn-primary w-full disabled:opacity-50" @disabled($tenants->isEmpty())>Sign in</button>
            </form>
            <p class="mt-6 border-t border-slate-100 pt-5 text-center text-xs leading-relaxed text-slate-500">Kendala akses? Hubungi administrator perusahaan.</p>
        </div>
    </section>
    <p class="mt-7 text-[11px] text-slate-400">Customer Relationship Management</p>
</main>
</body>
</html>
