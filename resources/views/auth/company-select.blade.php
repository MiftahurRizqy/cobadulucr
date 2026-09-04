<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih perusahaan · CRM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .workspace-page{background:#f6f8fc;background-image:radial-gradient(circle at 10% 12%,rgba(79,70,229,.11),transparent 26rem),radial-gradient(circle at 91% 88%,rgba(56,189,248,.11),transparent 28rem)}
        .workspace-card{box-shadow:0 24px 70px rgba(15,23,42,.10),0 3px 10px rgba(15,23,42,.04)}
        .company-option{position:relative;overflow:hidden;isolation:isolate}
        .company-option:before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(100deg,rgba(79,70,229,.08),rgba(79,70,229,0));opacity:0;transition:opacity .2s ease}
        .company-option:hover:before,.company-option:focus-visible:before{opacity:1}
        .company-option:hover .company-arrow{transform:translateX(4px);background:#4f46e5;color:#fff}
        .workspace-hero{position:relative;overflow:hidden;background:linear-gradient(145deg,#4338ca 0%,#6d28d9 54%,#7c3aed 100%)}
        .workspace-hero:before,.workspace-hero:after{content:"";position:absolute;border:1px solid rgba(255,255,255,.22);border-radius:999px}
        .workspace-hero:before{width:330px;height:330px;right:-145px;top:-96px}.workspace-hero:after{width:220px;height:220px;left:-108px;bottom:-92px}
        .hero-grid{background-image:linear-gradient(rgba(255,255,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.08) 1px,transparent 1px);background-size:26px 26px}
        .hero-orb{box-shadow:0 18px 38px rgba(32,20,100,.30)}
    </style>
</head>
<body class="workspace-page min-h-screen text-slate-900">
    <main class="mx-auto flex min-h-screen w-full max-w-4xl items-center px-5 py-10 sm:px-7">
        <section class="workspace-card grid w-full overflow-hidden rounded-[28px] border border-white/90 bg-white md:grid-cols-[.88fr_1.12fr]">
            <aside class="relative min-h-60 overflow-hidden bg-gradient-to-br from-indigo-700 via-violet-600 to-fuchsia-600 px-8 py-9 text-white sm:px-10 md:min-h-[500px] md:px-11 md:py-12">
                <div aria-hidden="true" class="absolute -right-24 -top-24 size-72 rounded-full border border-white/25"></div><div aria-hidden="true" class="absolute -bottom-24 -left-24 size-64 rounded-full border border-white/20"></div><div aria-hidden="true" class="absolute left-0 top-0 h-full w-full opacity-20" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:20px 20px"></div>
                <div class="relative z-10 flex h-full flex-col justify-between"><div class="grid size-12 place-items-center rounded-2xl bg-white/15 text-base font-black ring-1 ring-white/30 backdrop-blur">C</div><div class="py-5"><div class="relative mb-8 h-36"><div class="absolute left-4 top-8 size-24 rounded-[30px] border border-white/30 bg-white/15 backdrop-blur-sm"></div><div class="absolute left-16 top-0 grid size-24 place-items-center rounded-[30px] bg-white text-3xl font-black text-violet-600 shadow-2xl shadow-indigo-950/30">C</div><div class="absolute right-2 top-16 grid size-12 place-items-center rounded-2xl bg-white/20 text-lg ring-1 ring-white/30">✦</div></div><p class="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-100">CRM Workspace</p><h2 class="mt-3 max-w-56 text-3xl font-black leading-[1.12] tracking-tight">Satu akun, banyak ruang kerja.</h2><p class="mt-4 max-w-60 text-sm leading-relaxed text-violet-100/90">Akses perusahaan yang Anda butuhkan dalam satu tempat.</p></div><div class="flex gap-2"><span class="size-2 rounded-full bg-white"></span><span class="size-2 rounded-full bg-white/60"></span><span class="size-2 rounded-full bg-white/30"></span></div></div>
            </aside>
            <div class="flex min-w-0 flex-col">
                <div class="px-7 pb-5 pt-8 sm:px-10 sm:pt-10"><p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-indigo-600">Pilih workspace</p><h1 class="mt-3 text-[27px] font-black tracking-tight text-slate-950 sm:text-[31px]">Pilih perusahaan</h1><p class="mt-2 text-sm leading-relaxed text-slate-500">Pilih perusahaan yang ingin digunakan.</p></div>
            <form method="POST" action="{{ route('company.select.store') }}" class="space-y-3 px-7 pb-7 sm:px-10 sm:pb-10 {{ $tenants->count() > 4 ? 'max-h-[360px] overflow-y-auto pr-5 sm:pr-8' : '' }}">@csrf
                @forelse($tenants as $tenant)
                    <button name="tenant_id" value="{{ $tenant->id }}" class="company-option flex w-full items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-lg hover:shadow-indigo-950/5 focus:outline-none focus:ring-4 focus:ring-indigo-100 sm:p-5">
                        <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-base font-black text-white shadow-sm shadow-indigo-200">@if($tenant->logo_path)<img src="{{ asset('storage/'.$tenant->logo_path) }}" alt="Logo {{ $tenant->name }}" class="size-full bg-white object-contain p-1.5">@else{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}@endif</span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-[15px] font-extrabold text-slate-900">{{ $tenant->name }}</span></span><span class="company-arrow grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-lg font-medium text-slate-500 transition duration-200" aria-hidden="true">→</span>
                    </button>
                @empty
                    <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Akun ini belum dihubungkan ke perusahaan. Hubungi administrator.</p>
                @endforelse
            </form>
            <footer class="mt-auto flex items-center justify-end border-t border-slate-100 bg-slate-50/70 px-7 py-4 sm:px-10"><form method="POST" action="{{ route('company.logout') }}">@csrf<button class="text-xs font-bold text-slate-500 transition hover:text-rose-600">Keluar</button></form></footer>
            </div>
        </section>
    </main>
</body>
</html>
