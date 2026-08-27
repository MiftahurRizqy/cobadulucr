<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · Unified CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body>
<main class="grid min-h-screen lg:grid-cols-[1.1fr_.9fr]">
    <section class="relative hidden overflow-hidden bg-[#111827] p-14 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="noise absolute inset-0"></div><div class="absolute -right-32 -top-32 size-[520px] rounded-full bg-indigo-500/20 blur-3xl"></div><div class="absolute -bottom-40 -left-24 size-[480px] rounded-full bg-violet-500/15 blur-3xl"></div>
        <div class="relative flex items-center gap-3"><div class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-600 text-xl font-black">U</div><div><div class="text-lg font-extrabold">Unified CRM</div><div class="text-[10px] uppercase tracking-[.22em] text-indigo-300/70">Customer Workspace</div></div></div>
        <div class="relative max-w-xl"><div class="mb-6 inline-flex rounded-full border border-indigo-300/15 bg-indigo-400/10 px-4 py-2 text-xs font-bold text-indigo-200">CRM · Pipeline · Collaboration</div><h1 class="text-5xl font-extrabold leading-[1.08] tracking-tight">Satu ruang kerja untuk <span class="text-indigo-300">seluruh perjalanan customer.</span></h1><p class="mt-6 max-w-lg text-lg leading-relaxed text-slate-400">Kelola customer, pipeline, task lintas divisi, persetujuan, dan aktivitas—dengan akses yang tetap aman dan relevan.</p></div>
        <div class="relative flex gap-8 text-xs text-slate-500"><span>Role-based access</span><span>Audit trail</span><span>Mobile ready</span></div>
    </section>
    <section class="flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <div class="mb-10 flex items-center gap-3 lg:hidden"><div class="grid size-11 place-items-center rounded-xl bg-brand-600 font-black text-white">U</div><div class="font-extrabold text-ink">Unified CRM</div></div>
            <div class="mb-8"><div class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-brand-600">Welcome back</div><h2 class="text-3xl font-extrabold tracking-tight text-ink">Masuk ke workspace</h2><p class="mt-2 text-sm text-slate-500">Gunakan akun internal yang dibuatkan Master Admin.</p></div>
            @if($errors->any())<div class="mb-5 rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
            <form action="{{ route('login.store') }}" method="POST" class="space-y-5">@csrf
                <div><label class="label">Email</label><input class="field py-3.5" type="email" name="email" value="{{ old('email','admin@unified.test') }}" required autofocus></div>
                <div><label class="label">Password</label><input class="field py-3.5" type="password" name="password" value="password" required></div>
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-500"><input class="size-4 accent-brand-600" type="checkbox" name="remember"> Ingat saya</label>
                <button class="btn-primary w-full py-3.5">Masuk ke Unified CRM →</button>
            </form>
            <p class="mt-8 text-center text-xs leading-relaxed text-slate-400">Registrasi publik dinonaktifkan.<br>Hubungi Master Admin untuk pembuatan akun.</p>
        </div>
    </section>
</main>
</body>
</html>
