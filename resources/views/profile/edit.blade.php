@extends('layouts.app')

@section('title', 'Profil Saya')
@section('eyebrow', 'Akun / Profil')

@section('content')
@if($errors->any())
    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4">
        <div class="text-xs font-extrabold text-rose-700">Data belum dapat disimpan</div>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-[11px] text-rose-600">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card p-6" x-data="{ preview: @js($user->avatar_path ? asset('storage/'.$user->avatar_path) : null), load(event) { const file = event.target.files[0]; if (file) this.preview = URL.createObjectURL(file); } }">
        @csrf
        @method('PUT')
        <div>
            <h3 class="section-title">Informasi profil</h3>
            <p class="mt-1 text-xs text-slate-400">Perbarui foto dan informasi kontak akun Anda.</p>
        </div>

        <div class="mt-6 flex flex-col gap-5 border-b border-slate-100 pb-6 sm:flex-row sm:items-center">
            <div class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-3xl font-extrabold text-white shadow-sm" :class="!preview && 'border-transparent bg-gradient-to-br from-indigo-500 to-violet-500'">
                <img x-show="preview" :src="preview" class="size-full object-cover" alt="Foto profil">
                <span x-show="!preview">{{ mb_substr($user->name, 0, 1) }}</span>
            </div>
            <div>
                <label class="btn-secondary cursor-pointer">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>
                    Pilih foto
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" @change="load($event)">
                </label>
                <p class="mt-2 text-[10px] leading-relaxed text-slate-400">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
            </div>
        </div>

        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <div><label class="label">Nama lengkap *</label><input class="field" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"></div>
            <div><label class="label">Email *</label><input type="email" class="field" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></div>
            <div><label class="label">Nomor telepon</label><input class="field" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="Masukkan nomor telepon"></div>
            <div><label class="label">ID pengguna</label><div class="field bg-slate-50 text-slate-500">{{ $user->employee_id ?: '-' }}</div></div>
            <div class="md:col-span-2"><label class="label">Role</label><div class="field bg-slate-50 text-slate-500">{{ $user->roleNames() ?: ucfirst(str_replace('_', ' ', $user->authority_level)) }}</div></div>
        </div>

        <div class="mt-6 flex justify-end"><button class="btn-primary px-6">Simpan profil</button></div>
    </form>

    <form method="POST" action="{{ route('profile.password.update') }}" class="card p-6">
        @csrf
        @method('PUT')
        <div>
            <h3 class="section-title">Ganti password</h3>
            <p class="mt-1 text-xs text-slate-400">Gunakan minimal 8 karakter dan jangan bagikan password Anda.</p>
        </div>
        <div class="mt-6 space-y-5">
            <div><label class="label">Password saat ini *</label><input type="password" class="field" name="current_password" required autocomplete="current-password" placeholder="Masukkan password saat ini"></div>
            <div><label class="label">Password baru *</label><input type="password" class="field" name="password" minlength="8" required autocomplete="new-password" placeholder="Minimal 8 karakter"></div>
            <div><label class="label">Konfirmasi password baru *</label><input type="password" class="field" name="password_confirmation" minlength="8" required autocomplete="new-password" placeholder="Ulangi password baru"></div>
        </div>
        <button class="btn-primary mt-6 w-full">Perbarui password</button>
    </form>
</div>
@endsection
