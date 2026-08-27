@extends('layouts.app')
@section('title','Area & Cabang')
@section('eyebrow','Administrasi · Master data')
@section('page-actions')<a href="{{ route('areas.create') }}" class="btn-primary"><span class="text-base">+</span> Tambah area</a>@endsection
@section('content')
<form class="card mb-4 flex flex-col gap-2 p-3 sm:flex-row">
    <div class="relative min-w-0 flex-1"><svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input class="field pl-9" name="search" value="{{ request('search') }}" placeholder="Cari kode, area, atau cabang..."></div>
    <select class="field sm:max-w-44" name="status" onchange="this.form.submit()"><option value="">Semua status</option><option value="1" @selected(request('status')==='1')>Aktif</option><option value="0" @selected(request('status')==='0')>Nonaktif</option></select>
    <button class="btn-secondary">Cari</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left">
        <thead class="table-head"><tr><th class="px-5 py-3">Area</th><th class="px-4 py-3">Cabang utama</th><th class="px-4 py-3 text-center">Pengguna</th><th class="px-4 py-3 text-center">Customer</th><th class="px-4 py-3 text-center">Lead</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse($areas as $area)<tr class="hover:bg-slate-50/70"><td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-brand-50 text-[9px] font-black text-brand-600">{{ $area->code }}</span><div><div class="text-xs font-extrabold text-ink">{{ $area->name }}</div><div class="mt-1 text-[9px] text-slate-400">Kode {{ $area->code }}</div></div></div></td><td class="px-4 py-4 text-xs font-medium text-slate-600">{{ $area->branch ?: 'Belum ditentukan' }}</td><td class="px-4 py-4 text-center text-xs font-bold">{{ $area->users_count }}</td><td class="px-4 py-4 text-center text-xs font-bold">{{ $area->customers_count }}</td><td class="px-4 py-4 text-center text-xs font-bold">{{ $area->leads_count }}</td><td class="px-4 py-4"><span class="badge {{ $area->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $area->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="px-5 py-4 text-right"><a href="{{ route('areas.edit',$area) }}" class="text-[10px] font-bold text-brand-600 hover:text-brand-700">Edit area</a></td></tr>@empty<tr><td colspan="7"><div class="empty-state">Belum ada area. Klik “Tambah area” untuk membuatnya.</div></td></tr>@endforelse</tbody>
    </table></div>
</div>
<div class="mt-4">{{ $areas->links() }}</div>
@endsection
