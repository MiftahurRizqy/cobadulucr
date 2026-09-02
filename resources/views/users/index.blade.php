@extends('layouts.app')
@section('title', 'Users')
@section('eyebrow', 'Admin / User management')
@section('page-actions')
<a href="{{ route('users.create') }}" class="btn-primary">＋ User baru</a>
@endsection

@section('content')
@php
    $filterCount = collect([request('user_type'), request('role_id'), request('status'), request('approver')])
        ->filter(fn ($value) => filled($value))
        ->count();
@endphp

<form class="card relative z-20 mb-4 overflow-visible p-3" method="GET" x-data="{ filterOpen: false }" @keydown.escape.window="filterOpen=false">
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="relative min-w-0 flex-1">
            <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input class="field h-10 pl-9 text-sm" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, atau ID user...">
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn-secondary relative h-10 min-w-28" @click="filterOpen=!filterOpen">
                <svg class="mr-1.5 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filter
                @if($filterCount)<span class="ml-1.5 grid size-5 place-items-center rounded-full bg-brand-600 text-[9px] font-black text-white">{{ $filterCount }}</span>@endif
            </button>
            <button class="btn-primary h-10 min-w-20">Cari</button>
        </div>
    </div>

    <div x-show="filterOpen" x-cloak x-transition.origin.top.right @click.outside="filterOpen=false" class="absolute right-3 top-[58px] z-50 w-[min(680px,calc(100%-24px))] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div><h3 class="text-sm font-extrabold text-ink">Filter user</h3><p class="mt-0.5 text-[10px] text-slate-400">Pilih filter yang diperlukan saja.</p></div>
            <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200" @click="filterOpen=false" aria-label="Tutup filter">
                <svg class="block size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/></svg>
            </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><label class="label">User type</label><select class="field" name="user_type"><option value="">Semua tipe</option>@foreach(\App\Support\Crm::USER_TYPES as $value => $label)<option value="{{ $value }}" @selected(request('user_type') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="label">Role</label><select class="field" name="role_id"><option value="">Semua role</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->name }}</option>@endforeach</select></div>
            <div><label class="label">Status</label><select class="field" name="status"><option value="">Semua status</option><option value="1" @selected(request('status') === '1')>Active</option><option value="0" @selected(request('status') === '0')>Inactive</option></select></div>
            <div><label class="label">Hak approval</label><select class="field" name="approver"><option value="">Semua akun</option><option value="1" @selected(request('approver') === '1')>Approver</option><option value="0" @selected(request('approver') === '0')>Bukan approver</option></select></div>
        </div>
        <div class="mt-4 flex justify-end gap-2"><a href="{{ route('users.index') }}" class="btn-secondary">Reset</a><button class="btn-primary">Terapkan filter</button></div>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-left">
            <thead class="table-head"><tr><th class="px-5 py-4">User</th><th class="px-4 py-4">User Type</th><th class="px-4 py-4">Role</th><th class="px-4 py-4">Hak Approval</th><th class="px-4 py-4">Status</th><th class="w-24 px-5 py-4 text-center">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                <tr class="hover:bg-slate-50/70">
                    <td class="px-5 py-4"><div class="flex items-center gap-3"><div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl {{ $user->avatar_path ? 'border border-slate-200 bg-white' : 'bg-brand-100 text-brand-700' }} text-xs font-extrabold">@if($user->avatar_path)<img src="{{ asset('storage/'.$user->avatar_path) }}" alt="Foto {{ $user->name }}" class="size-full object-cover">@else{{ mb_substr($user->name, 0, 1) }}@endif</div><div><div class="text-sm font-extrabold text-ink">{{ $user->name }}</div><div class="text-[11px] text-slate-400">{{ $user->email }} · {{ $user->employee_id }}</div></div></div></td>
                    <td class="px-4 py-4"><span class="badge bg-violet-50 capitalize text-violet-600">{{ $user->user_type }}</span></td>
                    <td class="px-4 py-4 text-xs font-semibold">{{ $user->roleNames() ?: '—' }}</td>
                    <td class="px-4 py-4"><span class="badge {{ $user->is_approver ? 'bg-violet-50 text-violet-600' : 'bg-slate-100 text-slate-400' }}">{{ $user->is_approver ? 'Approver' : 'Tidak' }}</span></td>
                    <td class="px-4 py-4"><span class="badge {{ $user->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-5 py-4 text-center"><a href="{{ route('users.edit', $user) }}" class="btn-secondary !h-8 !px-3 text-[10px]">Edit</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="p-14 text-center text-sm text-slate-400">User tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-5">{{ $users->links() }}</div>
@endsection
