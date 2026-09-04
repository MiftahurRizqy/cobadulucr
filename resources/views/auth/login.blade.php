<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · CRM Perusahaan</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <script>
        (() => {
            const saved = localStorage.getItem('crm-theme');
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page h-[100svh] overflow-hidden">
<style>
    .login-page{background:#111827;background-image:radial-gradient(circle at 14% 17%,rgba(124,58,237,.62),transparent 25rem),radial-gradient(circle at 88% 84%,rgba(37,99,235,.52),transparent 27rem);color:#20242c}
    .login-page .login-panel{box-shadow:0 30px 90px rgba(2,6,23,.38),0 3px 14px rgba(2,6,23,.18)}
    .login-page .login-orbit{position:absolute;border:1px solid rgba(255,255,255,.12);border-radius:999px;pointer-events:none}
    .login-page .login-form{width:100%;padding:34px;background:white}
    .login-page .login-form h2{font-size:28px;font-weight:800;letter-spacing:-.8px}
    .login-page .label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#64748b;margin-bottom:9px}
    .login-page .field{height:50px;border-radius:11px;border-color:#dce1e9;font-size:13px}
    .login-page .btn-primary{height:50px;border-radius:11px;justify-content:center;padding:0 18px;font-size:13px;font-weight:700;background:#4f46e5;box-shadow:0 10px 22px #4f46e533}
    .login-page .btn-primary:hover{background:#4338ca}
    .login-page button:focus-visible{outline:3px solid #a5b4fc;outline-offset:3px}
    .login-page .password-field{padding-right:100px}
    .login-page .theme-toggle{position:fixed;right:24px;top:24px;z-index:10;display:grid;width:42px;height:42px;place-items:center;border:1px solid rgba(255,255,255,.2);border-radius:12px;background:rgba(255,255,255,.12);color:#fff;backdrop-filter:blur(12px);transition:.15s}
    .login-page .theme-toggle:hover{background:rgba(255,255,255,.23);color:#fff}
    html.dark .login-page{background:#0b1120;color:#e5e7eb}
    html.dark .login-page .login-form{background:#111827;border-color:#293449;box-shadow:0 8px 32px #0003}
    html.dark .login-page .company-logo{background:#111827;border-color:#334155;color:#a5b4fc}
    html.dark .login-page .company-name,html.dark .login-page .login-form h2{color:#f1f5f9}
    html.dark .login-page .label{color:#a8b4c5}
    html.dark .login-page .btn-primary{background:#4f46e5}
    html.dark .login-page .btn-primary:hover{background:#4338ca}
    html.dark .login-page .theme-toggle{background:#111827;border-color:#334155;color:#cbd5e1}
    html.dark .login-page .theme-toggle:hover{background:#1e293b;color:#fff}
    @media(max-width:640px){.login-page .login-form{padding:28px 22px}}
</style>
<main class="relative mx-auto flex h-full w-full max-w-5xl items-center justify-center px-5 py-6 sm:px-7 sm:py-8" x-data="{ showPassword: false, theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light', toggleTheme() { this.theme = this.theme === 'dark' ? 'light' : 'dark'; document.documentElement.classList.toggle('dark', this.theme === 'dark'); localStorage.setItem('crm-theme', this.theme); } }">
    <button type="button" class="theme-toggle" @click="toggleTheme()" :aria-label="theme === 'dark' ? 'Gunakan mode terang' : 'Gunakan mode gelap'" :title="theme === 'dark' ? 'Mode terang' : 'Mode gelap'">
        <svg x-show="theme === 'light'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 3V1M12 23v-2M3 12H1M23 12h-2M4.22 4.22 2.8 2.8M21.2 21.2l-1.42-1.42M19.78 4.22 21.2 2.8M2.8 21.2l1.42-1.42"/><circle cx="12" cy="12" r="4"/></svg>
        <svg x-show="theme === 'dark'" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
    </button>
    <div aria-hidden="true" class="login-orbit size-[540px] -left-64 -top-40"></div><div aria-hidden="true" class="login-orbit size-[440px] -bottom-40 -right-48"></div>
    <section class="login-panel relative w-full max-w-[460px] overflow-hidden rounded-[28px] border border-white/80 bg-white" aria-labelledby="login-heading">
        <div class="login-form">
            <div class="mb-8 text-center"><div class="mx-auto grid size-16 place-items-center rounded-[22px] bg-gradient-to-br from-indigo-500 to-violet-600 text-2xl font-black text-white shadow-xl shadow-indigo-200">C</div><p class="mt-6 text-[10px] font-extrabold uppercase tracking-[.2em] text-indigo-600">CRM Workspace</p><h2 id="login-heading" class="mt-3 text-ink">Selamat datang</h2><p class="mt-2 text-[13px] text-slate-500">Masuk untuk melanjutkan ke workspace Anda.</p></div>
            @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
            <form action="{{ route('login.store') }}" method="POST" class="space-y-5">@csrf
                <div><label class="label" for="email">Email</label><input class="field py-3.5" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Masukkan email Anda" autocomplete="username" required></div>
                <div><label class="label" for="password">Password</label><div class="relative"><input class="password-field field" id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" placeholder="Masukkan password" autocomplete="current-password" required><button type="button" class="absolute right-0 top-0 grid size-[46px] place-items-center text-slate-500 hover:text-brand-600" @click="showPassword = !showPassword" :aria-pressed="showPassword" aria-controls="password" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"><svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/></svg><svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.3 15.3 0 0 1-2.1 2.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 4.1-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div></div>
                <label class="flex items-center gap-2.5 text-xs text-slate-500"><input class="size-4 accent-brand-600" type="checkbox" name="remember" @checked(old('remember'))> Remember me</label>
                <button class="btn-primary w-full">Sign in</button>
            </form>
            <p class="mt-7 border-t border-slate-100 pt-5 text-center text-xs leading-relaxed text-slate-500">Kendala akses? Hubungi administrator perusahaan.</p>
        </div><footer class="border-t border-slate-100 bg-slate-50/70 px-9 py-4 text-center text-[11px] font-medium text-slate-400">Customer Relationship Management</footer>
    </section>
</main>
</body>
</html>
