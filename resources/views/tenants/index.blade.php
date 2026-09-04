@extends('layouts.app')
@section('title', 'Perusahaan')
@section('eyebrow', 'Platform / White Label')
@section('content')
@if($errors->any())<div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="section-title">Daftar perusahaan</h3><p class="mt-1 text-xs text-slate-400">Kelola seluruh perusahaan yang tersedia di workspace Anda.</p></div>
        <div class="divide-y divide-slate-100">
            @foreach($tenants as $company)
            <div x-data="{ editing: false, completing: false }" class="flex flex-wrap items-center gap-4 px-5 py-4">
                <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-brand-600 text-sm font-black text-white">@if($company->logo_path)<img src="{{ asset('storage/'.$company->logo_path) }}" alt="" class="size-full bg-white object-contain">@else{{ mb_strtoupper(mb_substr($company->name,0,1)) }}@endif</div>
                <div class="min-w-0 flex-1"><div class="truncate text-sm font-extrabold text-ink">{{ $company->name }}</div><div class="mt-1 truncate text-[10px] text-slate-400">CRM perusahaan</div></div>
                <span class="badge {{ $company->setup_complete ? ($company->is_active?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-500') : 'bg-amber-50 text-amber-700' }}">{{ $company->setup_complete ? ($company->is_active?'Aktif':'Nonaktif') : 'Setup belum selesai' }}</span>
                <button type="button" class="btn-secondary h-9" @click="editing = true">Edit</button>
                @if(!$company->setup_complete)<button type="button" class="btn-primary h-9" @click="completing = true">Selesaikan setup</button>
                @endif

                <template x-teleport="body">
                    <div x-show="completing" x-cloak @keydown.escape.window="completing = false" class="fixed inset-0 z-[175] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm">
                        <form method="POST" action="{{ route('tenants.complete-setup', $company) }}" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="completing = false">
                            @csrf @method('PATCH')
                            <header class="border-b border-slate-100 px-6 py-5"><h3 class="text-lg font-black text-ink">Selesaikan setup</h3><p class="mt-1 text-xs text-slate-400">Buat Master Administrator untuk {{ $company->name }}.</p></header>
                            <div class="space-y-4 p-6"><input class="field" name="admin_name" required placeholder="Nama administrator"><input class="field" type="email" name="admin_email" required placeholder="Email administrator"><input class="field" type="password" name="admin_password" required placeholder="Password minimal 8 karakter"><input class="field" type="password" name="admin_password_confirmation" required placeholder="Ulangi password"></div>
                            <footer class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4"><button type="button" class="btn-secondary" @click="completing = false">Batal</button><button class="btn-primary">Selesaikan setup</button></footer>
                        </form>
                    </div>
                </template>

                <template x-teleport="body">
                    <div x-show="editing" x-cloak @keydown.escape.window="editing = false" class="fixed inset-0 z-[170] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm">
                        <form method="POST" action="{{ route('tenants.update', $company) }}" enctype="multipart/form-data" class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="editing = false">
                            @csrf @method('PATCH')
                            <header class="flex items-start justify-between border-b border-slate-100 px-6 py-5"><div><h3 class="text-lg font-black text-ink">Edit perusahaan</h3><p class="mt-1 text-xs text-slate-400">Perbarui identitas white-label perusahaan.</p></div><button type="button" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500" @click="editing = false">×</button></header>
                            <div class="space-y-4 p-6">
                                <div><label class="label">Nama perusahaan *</label><input class="field" name="name" value="{{ $company->name }}" required></div>
                                <div><label class="label">Logo perusahaan</label><input class="field file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-brand-700" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><p class="mt-1 text-[10px] text-slate-400">PNG, JPG, atau WebP. Maksimal 2 MB. Disarankan logo persegi dengan latar transparan.</p></div>
                                @if($company->logo_path)<label class="flex items-center gap-2 text-xs font-semibold text-rose-600"><input type="checkbox" name="remove_logo" value="1" class="size-4 accent-rose-600">Hapus logo saat ini</label>@endif
                            </div>
                            <footer class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4"><button type="button" class="btn-secondary" @click="editing = false">Batal</button><button class="btn-primary">Simpan perubahan</button></footer>
                        </form>
                    </div>
                </template>
            </div>
            @endforeach
        </div>
    </section>
    <form method="POST" action="{{ route('tenants.store') }}" enctype="multipart/form-data" class="card p-6">@csrf
        <h3 class="section-title">Tambah perusahaan</h3><p class="mt-1 text-xs text-slate-400">Sambungkan database perusahaan, lalu akun administrator dibuat otomatis.</p>
        <div class="mt-5 space-y-4">
            <div><label class="label">Nama perusahaan *</label><input class="field" name="name" value="{{ old('name') }}" required placeholder="PT Nama Perusahaan"></div>
            <div><label class="label">Logo perusahaan</label><input class="field file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-brand-700" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><p class="mt-1 text-[10px] text-slate-400">Opsional. PNG, JPG, atau WebP maksimal 2 MB. Disarankan berbentuk persegi.</p></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5"><div class="mb-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Database perusahaan</div><div class="space-y-3"><input class="field" name="database_name" value="{{ old('database_name') }}" required placeholder="Nama database"><input class="field" name="database_username" value="{{ old('database_username') }}" required placeholder="Username database"><input class="field" type="password" name="database_password" required placeholder="Password database"></div><p class="mt-2 text-[10px] leading-relaxed text-slate-400">Gunakan database yang telah disiapkan. Kredensial disimpan terenkripsi.</p></div>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-100 bg-brand-50/50 p-3.5 text-xs text-slate-600"><input class="mt-0.5 size-4 accent-brand-600" type="checkbox" name="duplicate_configuration" value="1" @checked(old('duplicate_configuration', 1))><span><span class="block font-extrabold text-ink">Duplikat konfigurasi perusahaan saat ini</span><span class="mt-1 block text-[10px] leading-relaxed text-slate-400">Salin jenis customer, pipeline, kebijakan bukti peran, validasi data, pengaturan operasional, dan KPI Metrics. Customer, opportunity, produk, serta pengguna tidak ikut disalin.</span></span></label>
            <div class="border-t border-slate-100 pt-4"><div class="mb-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Master Administrator pertama</div><div class="space-y-4"><input class="field" name="admin_name" value="{{ old('admin_name') }}" required placeholder="Nama administrator"><input class="field" type="email" name="admin_email" value="{{ old('admin_email') }}" required placeholder="admin@perusahaan.com"><input class="field" type="password" name="admin_password" required placeholder="Password minimal 8 karakter"><input class="field" type="password" name="admin_password_confirmation" required placeholder="Ulangi password"></div></div>
        </div>
        <button class="btn-primary mt-5 w-full">Hubungkan database dan buat perusahaan</button>
    </form>
</div>
@endsection
