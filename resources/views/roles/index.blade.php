@extends('layouts.app')
@section('title','Roles & Permissions')
@section('eyebrow','Admin / Access control')
@section('page-actions')
<div class="flex items-center gap-2">
@if($hasMissingRoles)
<form method="POST" action="{{ route('roles.apply-templates') }}">@csrf<button class="btn-secondary" title="Tambahkan role bawaan yang belum ada tanpa mengubah role lama">Lengkapi role bawaan</button></form>
@endif
<a href="{{ route('roles.create') }}" class="btn-primary">＋ Role baru</a>
</div>
@endsection
@section('content')
<div x-data="{ usersRole: null }">
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach($roles as $role)
            @php($effectivePermissions = $role->effectivePermissions())
            <article class="card p-5">
                <div class="flex justify-between gap-3">
                    <div><h3 class="font-extrabold text-ink">{{ $role->name }}</h3><p class="mt-1 text-xs text-slate-400">{{ $role->description }}</p></div>
                    <button type="button" @click="usersRole={{ $role->id }}" class="badge h-fit bg-brand-50 text-brand-600 transition hover:bg-brand-100" title="Lihat pengguna {{ $role->name }}">{{ $role->users_count }} users</button>
                </div>
                <div class="mt-4 text-[10px] font-bold text-slate-400">{{ $role->permissions->count() }} langsung · {{ $effectivePermissions->count() }} efektif</div>
                <div class="mt-2 flex flex-wrap gap-1">@foreach($effectivePermissions->take(6) as $perm)<span class="badge {{ $role->permissions->contains('id',$perm->id) ? 'bg-slate-100 text-slate-500' : 'bg-brand-50 text-brand-600' }}">{{ $perm->key }}</span>@endforeach @if($effectivePermissions->count()>6)<span class="badge bg-slate-100 text-slate-400">+{{ $effectivePermissions->count()-6 }}</span>@endif</div>
                <a href="{{ route('roles.edit',$role) }}" class="btn-secondary mt-5 w-full">Configure access</a>
            </article>

            <div x-show="usersRole === {{ $role->id }}" x-cloak x-transition.opacity @keydown.escape.window="usersRole=null" @click.self="usersRole=null" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                <div class="flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Pengguna {{ $role->name }}</h3><p class="mt-1 text-[10px] text-slate-400">{{ $role->users_count }} pengguna menggunakan role ini.</p></div><button type="button" @click="usersRole=null" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button></div>
                    <div class="overflow-y-auto p-5"><div class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                        @forelse($role->users->sortBy('name') as $roleUser)
                            <div class="flex items-center gap-3 px-4 py-3"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-black text-brand-700">{{ collect(explode(' ', $roleUser->name))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part,0,1)))->join('') }}</span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-ink">{{ $roleUser->name }}</span><span class="block truncate text-[10px] text-slate-400">{{ $roleUser->email }} · {{ $roleUser->roleNames() ?: ucfirst(str_replace('_',' ',$roleUser->authority_level)) }}</span></span><span class="badge {{ $roleUser->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">{{ $roleUser->is_active ? 'Aktif' : 'Nonaktif' }}</span><a href="{{ route('users.edit',$roleUser) }}" class="text-[10px] font-bold text-brand-600">Edit</a></div>
                        @empty
                            <div class="p-8 text-center text-xs text-slate-400">Belum ada pengguna pada role ini.</div>
                        @endforelse
                    </div></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
